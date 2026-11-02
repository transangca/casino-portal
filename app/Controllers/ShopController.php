<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\PackageService;

/**
 * Gói chơi: mua gói trải nghiệm, gói thuê theo thời gian.
 */
class ShopController
{
    public function index(): void
    {
        $packages = PackageService::listActive();

        $userId   = Auth::id();
        $active   = $userId ? PackageService::activePackageFor($userId) : null;
        $balance  = $userId ? Wallet::balance($userId) : 0.0;
        $remaining = $userId ? PackageService::remainingSeconds($userId) : 0;

        view('shop/packages', compact('packages', 'active', 'balance', 'remaining'));
    }

    public function purchase(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $pkgId  = (int)($_POST['package_id'] ?? 0);

        $result = PackageService::purchase($userId, $pkgId);

        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        redirect(url('/packages'));
    }

    /**
     * Gói của tôi.
     */
    public function myPackages(): void
    {
        $userId = (int)Auth::id();

        $list = Database::fetchAll(
            'SELECT up.*, p.name AS package_name, p.slug, p.duration_minutes, p.price
             FROM user_packages up JOIN packages p ON p.id = up.package_id
             WHERE up.user_id = ? ORDER BY up.id DESC LIMIT 50',
            [$userId]
        );

        $active = PackageService::activePackageFor($userId);

        view('shop/my-packages', compact('list', 'active'));
    }
}
