<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\ChildSiteService;
use App\Services\VipService;

/**
 * VIP 60 cấp + website con.
 */
class VipController
{
    /**
     * Trang hiển thị 60 cấp VIP.
     */
    public function index(): void
    {
        $userId = (int)Auth::id();
        $tiers  = VipService::all();
        $level  = VipService::levelOf($userId);

        // Tổng nạp để tính % tiến trình
        $total = (float)Database::fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type IN ("deposit","giftcode")',
            [$userId]
        );

        $nextTier = null;
        foreach ($tiers as $t) {
            if ((int)$t['level'] > $level) {
                $nextTier = $t;
                break;
            }
        }

        $progress = 0.0;
        if ($nextTier) {
            $cur = 0.0;
            foreach ($tiers as $t) {
                if ((int)$t['level'] === $level) {
                    $cur = (float)$t['deposit_required'];
                    break;
                }
            }
            $span = max(1.0, (float)$nextTier['deposit_required'] - $cur);
            $progress = min(100.0, max(0.0, (($total - $cur) / $span) * 100));
        }

        view('vip/index', [
            'title'     => 'VIP 60 cấp',
            'tiers'     => $tiers,
            'level'     => $level,
            'total'     => $total,
            'nextTier'  => $nextTier,
            'progress'  => $progress,
            'minVip'    => ChildSiteService::minVipLevel(),
            'maxShare'  => ChildSiteService::maxRevShare(),
        ]);
    }

    /**
     * Danh sách + tạo website con.
     */
    public function childSites(): void
    {
        $userId = (int)Auth::id();
        $sites  = ChildSiteService::listByUser($userId);
        $level  = VipService::levelOf($userId);

        $tier = VipService::find($level);

        view('vip/child-sites', [
            'title'    => 'Website con',
            'sites'    => $sites,
            'level'    => $level,
            'minVip'   => ChildSiteService::minVipLevel(),
            'maxShare' => ChildSiteService::maxRevShare(),
            'myShare'  => (float)($tier['rev_share_max'] ?? 0),
        ]);
    }

    /**
     * Tạo website con.
     */
    public function createChildSite(): void
    {
        CSRF::verifyOrFail();

        $result = ChildSiteService::create((int)Auth::id(), [
            'name'            => $_POST['name'] ?? '',
            'slug'            => $_POST['slug'] ?? '',
            'domain'          => $_POST['domain'] ?? '',
            'logo'            => $_POST['logo'] ?? '',
            'banner'          => $_POST['banner'] ?? '',
            'theme'           => $_POST['theme'] ?? 'dark',
            'rev_share'       => $_POST['rev_share'] ?? 0,
            'require_deposit' => $_POST['require_deposit'] ?? 0,
            'min_deposit'     => $_POST['min_deposit'] ?? 0,
        ]);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect(url('/child-sites'));
    }

    /**
     * Cập nhật website con.
     */
    public function updateChildSite(): void
    {
        CSRF::verifyOrFail();

        $id = (int)($_POST['id'] ?? 0);
        $ok = ChildSiteService::update($id, (int)Auth::id(), [
            'name'   => $_POST['name'] ?? '',
            'domain' => $_POST['domain'] ?? '',
            'logo'   => $_POST['logo'] ?? '',
            'banner' => $_POST['banner'] ?? '',
            'theme'  => $_POST['theme'] ?? '',
        ]);

        flash($ok ? 'success' : 'error', $ok ? 'Đã cập nhật website con.' : 'Không cập nhật được.');
        redirect(url('/child-sites'));
    }
}
