<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Wallet;
use App\Services\BackgroundJob;
use App\Services\ServicePriceService;
use App\Services\SiteAutoBuilderService;
use App\Services\SiteCloneService;

/**
 * Dịch vụ copy 1-1 trang web + tạo website tự động A-Z.
 */
class SiteToolController
{
    // =================================================================
    //  COPY 1-1 TRANG WEB
    // =================================================================

    public function cloneIndex(): void
    {
        view('services/site-clone', [
            'title'    => 'Copy 1-1 trang web',
            'projects' => SiteCloneService::myProjects((int)Auth::id()),
            'price'    => ServicePriceService::price('site-clone', 15000.0),
            'balance'  => Wallet::balance((int)Auth::id()),
        ]);
    }

    /**
     * Tạo dự án sao chép.
     */
    public function cloneCreate(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $url    = trim((string)($_POST['url'] ?? ''));

        if ($url === '') {
            flash('error', 'Vui lòng nhập đường dẫn website cần sao chép.');
            redirect(url('/services/site-clone'));
        }

        $price = ServicePriceService::price('site-clone', 15000.0);
        if (Wallet::balance($userId) < $price) {
            flash('error', 'Số dư không đủ. Cần ' . money($price) . ' cho dịch vụ này.');
            redirect(url('/services/site-clone'));
        }

        $res = SiteCloneService::create($userId, $url);
        if (!$res['ok']) {
            flash('error', $res['message']);
            redirect(url('/services/site-clone'));
        }

        // Chạy sao chép ở chế độ nền để trang hiện tiến trình
        $spawned = BackgroundJob::spawn(
            BASE_PATH . '/database/worker_site.php',
            ['clone', (string)$res['id']]
        );
        if (!$spawned) {
            SiteCloneService::run((int)$res['id']);
        }

        $after = Wallet::apply(
            $userId,
            'adjustment',
            -$price,
            'Copy 1-1 trang web',
            'site_clone',
            (string)$res['id']
        );

        flash('success', $spawned
            ? 'Đã bắt đầu sao chép. Trang này sẽ tự cập nhật trạng thái.'
            : 'Đã sao chép xong. Bạn có thể tải file ZIP về.');
        redirect(url('/services/site-clone/project/' . $res['id']));
    }

    /**
     * Chi tiết dự án sao chép.
     */
    public function cloneProject(int $id): void
    {
        $project = SiteCloneService::find($id);
        if (!$project || ((int)$project['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy dự án');
        }

        $meta = json_decode((string)($project['result_json'] ?? ''), true);

        view('services/site-clone-project', [
            'title'   => (string)($project['title'] ?: 'Dự án sao chép'),
            'project' => $project,
            'meta'    => is_array($meta) ? $meta : [],
        ]);
    }

    /**
     * Tải ZIP dự án sao chép.
     */
    public function cloneDownload(int $id): void
    {
        $project = SiteCloneService::find($id);
        if (!$project || ((int)$project['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy dự án');
        }

        $zip = SiteCloneService::zipOf($id);
        if ($zip === null) {
            flash('error', 'File ZIP chưa sẵn sàng. Vui lòng chạy lại sao chép.');
            redirect(url('/services/site-clone/project/' . $id));
        }

        $name = 'clone-' . preg_replace('/[^a-z0-9]+/i', '-', (string)($project['host'] ?? 'site')) . '.zip';

        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . filesize($zip));
            header('Cache-Control: private, max-age=0, must-revalidate');
            readfile($zip);
            @unlink($zip);
            exit;
        }

        redirect(url('/services/site-clone/project/' . $id));
    }

    /**
     * Xoá dự án sao chép.
     */
    public function cloneDelete(int $id): void
    {
        CSRF::verifyOrFail();
        SiteCloneService::delete($id, (int)Auth::id());
        flash('success', 'Đã xoá dự án.');
        redirect(url('/services/site-clone'));
    }

    // =================================================================
    //  TẠO WEBSITE TỰ ĐỘNG A-Z
    // =================================================================

    public function autoIndex(): void
    {
        view('services/site-auto', [
            'title'     => 'Tạo website tự động A-Z',
            'projects'  => SiteAutoBuilderService::myProjects((int)Auth::id()),
            'categories'=> SiteAutoBuilderService::CATEGORIES,
            'price'     => ServicePriceService::price('site-auto', 99000.0),
            'balance'   => Wallet::balance((int)Auth::id()),
        ]);
    }

    /**
     * Tạo website.
     */
    public function autoCreate(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();

        $res = SiteAutoBuilderService::create($userId, $_POST);
        if (!$res['ok']) {
            flash('error', $res['message']);
            redirect(url('/services/site-auto'));
        }

        $spawned = BackgroundJob::spawn(
            BASE_PATH . '/database/worker_site.php',
            ['auto', (string)$res['id']]
        );
        if (!$spawned) {
            SiteAutoBuilderService::run((int)$res['id']);
        }

        $price = ServicePriceService::price('site-auto', 99000.0);
        if (Wallet::balance($userId) >= $price) {
            try {
                Wallet::apply(
                    $userId,
                    'adjustment',
                    -$price,
                    'Tạo website tự động A-Z',
                    'site_auto',
                    (string)$res['id']
                );
            } catch (\Throwable $e) {
                flash('error', 'Lỗi trừ phí: ' . $e->getMessage());
            }
        }

        flash('success', $spawned
            ? 'Đã bắt đầu tạo website. Trang này sẽ tự cập nhật trạng thái.'
            : 'Đã tạo website xong. Tải file ZIP về giải nén là chạy được ngay.');
        redirect(url('/services/site-auto/project/' . $res['id']));
    }

    /**
     * Chi tiết dự án.
     */
    public function autoProject(int $id): void
    {
        $project = SiteAutoBuilderService::find($id);
        if (!$project || ((int)$project['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy dự án');
        }

        $pages = json_decode((string)($project['pages'] ?? ''), true);

        view('services/site-auto-project', [
            'title'   => (string)$project['name'],
            'project' => $project,
            'pages'   => is_array($pages) ? $pages : [],
        ]);
    }

    /**
     * Tải ZIP website.
     */
    public function autoDownload(int $id): void
    {
        $project = SiteAutoBuilderService::find($id);
        if (!$project || ((int)$project['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy dự án');
        }

        $zip = SiteAutoBuilderService::zipOf($id);
        if ($zip === null) {
            flash('error', 'File ZIP chưa sẵn sàng.');
            redirect(url('/services/site-auto/project/' . $id));
        }

        $name = 'site-' . slugify((string)$project['name']) . '.zip';

        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . filesize($zip));
            header('Cache-Control: private, max-age=0, must-revalidate');
            readfile($zip);
            @unlink($zip);
            exit;
        }

        redirect(url('/services/site-auto/project/' . $id));
    }

    /**
     * Xoá dự án.
     */
    public function autoDelete(int $id): void
    {
        CSRF::verifyOrFail();
        SiteAutoBuilderService::delete($id, (int)Auth::id());
        flash('success', 'Đã xoá dự án.');
        redirect(url('/services/site-auto'));
    }
}
