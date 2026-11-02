<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Services\BackgroundJob;
use App\Services\UiScraperService;

/**
 * Dịch vụ đục UI/UX website (casino / game).
 *
 * Luồng:
 *   GET  /services/ui-scraper             → form nhập link
 *   POST /services/ui-scraper/scan        → tạo job quét (nền)
 *   GET  /services/ui-scraper/status/{id} → tiến trình (JSON, AJAX polling)
 *   POST /services/ui-scraper/run/{id}    → chạy quét đồng bộ
 *   POST /services/ui-scraper/download/{id} → tải ZIP (chỉ khi đã xong)
 *   POST /services/ui-scraper/clear       → xoá lịch sử
 */
class UiScraperController
{
    /** Số lần quét tối đa mỗi ngày (chống lạm dụng) */
    private const DAILY_LIMIT = 30;

    public function index(): void
    {
        view('services/ui-scraper', [
            'title' => 'Dịch vụ đục UI/UX web',
            'job'   => UiScraperService::lastJob((int)Auth::id()),
            'jobs'  => UiScraperService::jobs((int)Auth::id(), 12),
        ]);
    }

    /**
     * Tạo job quét.
     */
    public function scan(): void
    {
        CSRF::verifyOrFail();

        $url = trim((string)($_POST['url'] ?? ''));
        $check = UiScraperService::validateUrl($url);
        if (!$check['ok']) {
            flash('error', $check['message']);
            redirect(url('/services/ui-scraper'));
        }

        if (!$this->canScan()) {
            flash('error', 'Bạn đã quét quá nhiều lần trong ngày. Vui lòng thử lại sau.');
            redirect(url('/services/ui-scraper'));
        }

        $jobId = UiScraperService::createJob((int)Auth::id(), $url);

        // Chạy nền để trang hiện được tiến trình tải tài nguyên
        $spawned = BackgroundJob::spawn(
            BASE_PATH . '/database/worker_scraper.php',
            [$jobId]
        );

        if ($spawned) {
            flash('success', 'Đã bắt đầu quét. Trang này sẽ tự cập nhật tiến trình tải CSS, ảnh, script...');
        } else {
            // Không spawn được (hosting cấm exec) → chạy đồng bộ
            UiScraperService::runJob($jobId);
            flash('success', 'Đã quét xong.');
        }

        redirect(url('/services/ui-scraper/job/' . $jobId));
    }

    /**
     * Trang chi tiết job (hiển thị tiến trình + nút tải ZIP).
     */
    public function job(int $id): void
    {
        $job = UiScraperService::job($id);
        if (!$job || (int)$job['user_id'] !== (int)Auth::id()) {
            abort(404, 'Không tìm thấy job');
        }

        $manifest = json_decode((string)($job['result_json'] ?? ''), true);

        view('services/ui-scraper-job', [
            'title'    => 'Quét ' . (string)($job['host'] ?? ''),
            'job'      => $job,
            'manifest' => is_array($manifest) ? $manifest : [],
        ]);
    }

    /**
     * Trạng thái job (JSON) — dùng cho AJAX polling.
     */
    public function status(int $id): void
    {
        $job = UiScraperService::job($id);
        if (!$job || (int)$job['user_id'] !== (int)Auth::id()) {
            json_error('Không tìm thấy job.', 404);
        }

        $counts = json_decode((string)($job['counts'] ?? ''), true);

        json_success([
            'id'       => (int)$job['id'],
            'status'   => (string)$job['status'],
            'step'     => (string)$job['step'],
            'progress' => (int)$job['progress'],
            'done'     => (int)$job['done_assets'],
            'total'    => (int)$job['total_assets'],
            'counts'   => is_array($counts) ? $counts : [],
            'error'    => (string)($job['error_msg'] ?? ''),
            'has_zip'  => UiScraperService::zipOf($id) !== null,
        ]);
    }

    /**
     * Chạy quét đồng bộ (dùng khi bấm "Quét ngay" hoặc cron gọi).
     */
    public function run(int $id): void
    {
        CSRF::verifyOrFail();

        $job = UiScraperService::job($id);
        if (!$job || (int)$job['user_id'] !== (int)Auth::id()) {
            abort(404, 'Không tìm thấy job');
        }

        $res = UiScraperService::runJob($id);
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/ui-scraper/job/' . $id));
    }

    /**
     * Chạy quét ở chế độ nền (nút "Quét ngay" trên trang job).
     */
    public function start(int $id): void
    {
        CSRF::verifyOrFail();

        $job = UiScraperService::job($id);
        if (!$job || (int)$job['user_id'] !== (int)Auth::id()) {
            abort(404, 'Không tìm thấy job');
        }
        if ($job['status'] === 'running') {
            flash('error', 'Job đang chạy.');
            redirect(url('/services/ui-scraper/job/' . $id));
        }

        $spawned = BackgroundJob::spawn(
            BASE_PATH . '/database/worker_scraper.php',
            [$id]
        );

        if (!$spawned) {
            UiScraperService::runJob($id);
            flash('success', 'Đã quét xong.');
        } else {
            flash('success', 'Đã bắt đầu quét nền. Trang sẽ tự cập nhật tiến trình.');
        }

        redirect(url('/services/ui-scraper/job/' . $id));
    }

    /**
     * Tải ZIP — chỉ cho phép khi job đã hoàn tất.
     */
    public function download(int $id): void
    {
        CSRF::verifyOrFail();

        $job = UiScraperService::job($id);
        if (!$job || (int)$job['user_id'] !== (int)Auth::id()) {
            abort(404, 'Không tìm thấy job');
        }

        $zip = UiScraperService::zipOf($id);
        if ($zip === null) {
            flash('error', 'Chưa quét xong nên chưa có file ZIP. Vui lòng đợi quét hoàn tất.');
            redirect(url('/services/ui-scraper/job/' . $id));
        }

        $name = 'ui-' . preg_replace('/[^a-z0-9]+/i', '-', (string)($job['host'] ?? 'site')) . '.zip';

        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . filesize($zip));
            header('Cache-Control: private, max-age=0, must-revalidate');
            readfile($zip);
            @unlink($zip);
            exit;
        }

        redirect(url('/services/ui-scraper/job/' . $id));
    }

    /**
     * Xoá job.
     */
    public function delete(int $id): void
    {
        CSRF::verifyOrFail();
        UiScraperService::deleteJob($id, (int)Auth::id());
        flash('success', 'Đã xoá job.');
        redirect(url('/services/ui-scraper'));
    }

    /**
     * Xoá toàn bộ lịch sử.
     */
    public function clear(): void
    {
        CSRF::verifyOrFail();

        foreach (UiScraperService::jobs((int)Auth::id(), 200) as $j) {
            UiScraperService::deleteJob((int)$j['id'], (int)Auth::id());
        }

        flash('success', 'Đã xoá lịch sử quét.');
        redirect(url('/services/ui-scraper'));
    }

    /**
     * Giới hạn số lần quét mỗi ngày.
     */
    private function canScan(): bool
    {
        $file  = BASE_PATH . '/storage/ui-scraper/quota-' . (int)Auth::id() . '.json';
        $today = date('Y-m-d');
        $count = 0;

        if (is_file($file)) {
            $d = json_decode((string)@file_get_contents($file), true);
            if (is_array($d) && (string)($d['day'] ?? '') === $today) {
                $count = (int)($d['count'] ?? 0);
            }
        }

        if ($count >= self::DAILY_LIMIT) {
            return false;
        }

        $dir = BASE_PATH . '/storage/ui-scraper';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode(['day' => $today, 'count' => $count + 1]));
        return true;
    }
}
