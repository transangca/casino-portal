<?php
namespace App\Controllers;

use App\Core\CSRF;
use App\Core\Database;
use App\Services\PhoneCheckService;
use App\Services\ServicePriceService;
use App\Services\SiteAutoBuilderService;
use App\Services\SiteCloneService;
use App\Services\SmsService;

/**
 * Quản trị cho các dịch vụ mới (V16).
 *
 * - Bảng giá dịch vụ (ăn chênh so với đối tác)
 * - Nhật ký check số điện thoại
 * - Quản lý dự án copy web / tạo web A-Z
 * - Quản lý chiến dịch SMS
 */
class ServiceAdminController
{
    // =================================================================
    //  BẢNG GIÁ DỊCH VỤ
    // =================================================================

    public function prices(): void
    {
        view('admin/service-prices', [
            'title'  => 'Bảng giá dịch vụ',
            'prices' => ServicePriceService::all(false),
            'groups' => ServicePriceService::groups(),
        ]);
    }

    public function savePrice(): void
    {
        CSRF::verifyOrFail();

        $res = ServicePriceService::save($_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/service-prices'));
    }

    public function deletePrice(): void
    {
        CSRF::verifyOrFail();

        $res = ServicePriceService::delete((int)($_POST['id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/admin/service-prices'));
    }

    // =================================================================
    //  CHECK SỐ ĐIỆN THOẠI
    // =================================================================

    public function phoneChecks(): void
    {
        $phone = trim((string)($_GET['phone'] ?? ''));

        view('admin/phone-checks', [
            'title'  => 'Nhật ký check số điện thoại',
            'checks' => PhoneCheckService::allChecks(300, $phone),
            'stats'  => PhoneCheckService::stats(),
            'phone'  => $phone,
        ]);
    }

    // =================================================================
    //  DỰ ÁN WEBSITE
    // =================================================================

    public function sites(): void
    {
        view('admin/site-jobs', [
            'title'  => 'Dự án website',
            'clones' => SiteCloneService::allProjects(100),
            'builds' => SiteAutoBuilderService::allProjects(100),
        ]);
    }

    // =================================================================
    //  CHIẾN DỊCH SMS
    // =================================================================

    public function smsCampaigns(): void
    {
        view('admin/sms-campaigns', [
            'title'    => 'Chiến dịch tin nhắn',
            'campaigns'=> SmsService::allCampaigns(150),
            'stats'    => SmsService::stats(),
        ]);
    }

    /**
     * Đổi trạng thái chiến dịch (admin).
     */
    public function smsAction(): void
    {
        CSRF::verifyOrFail();

        $id     = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');

        $status = match ($action) {
            'start' => 'running',
            'pause' => 'paused',
            'stop'  => 'stopped',
            default => '',
        };

        if ($status === '') {
            flash('error', 'Hành động không hợp lệ.');
            redirect(url('/admin/sms-campaigns'));
        }

        $campaign = SmsService::find($id);
        if (!$campaign) {
            flash('error', 'Không tìm thấy chiến dịch.');
            redirect(url('/admin/sms-campaigns'));
        }

        Database::update('sms_campaigns', ['status' => $status], 'id = :id', ['id' => $id]);
        flash('success', 'Đã cập nhật trạng thái.');
        redirect(url('/admin/sms-campaigns'));
    }

    // =================================================================
    //  THỐNG KÊ TỔNG HỢP
    // =================================================================

    public function overview(): void
    {
        view('admin/service-overview', [
            'title'       => 'Tổng quan dịch vụ mới',
            'phone_stats' => PhoneCheckService::stats(),
            'sms_stats'   => SmsService::stats(),
            'clone_count' => (int)Database::fetchColumn('SELECT COUNT(*) FROM site_clones'),
            'build_count' => (int)Database::fetchColumn('SELECT COUNT(*) FROM site_auto_builds'),
            'scrap_count' => (int)Database::fetchColumn('SELECT COUNT(*) FROM scraper_jobs'),
            'scrap_done'  => (int)Database::fetchColumn('SELECT COUNT(*) FROM scraper_jobs WHERE status = "done"'),
        ]);
    }
}
