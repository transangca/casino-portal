<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\PhoneCheckService;
use App\Services\ServicePriceService;

/**
 * Dịch vụ check thông tin qua số điện thoại.
 *
 * GET  /services/phone-check           → form + kết quả
 * POST /services/phone-check/check     → tra cứu 1 số (trừ phí theo bảng giá)
 * POST /services/phone-check/batch     → tra cứu nhiều số
 * GET  /services/phone-check/history   → lịch sử tra cứu của tôi
 */
class PhoneCheckController
{
    /** Số tiền còn lại trong gói (không trừ ví nếu đủ) */
    private const PRICE_CODE = 'phone-check';

    public function index(): void
    {
        view('services/phone-check', [
            'title'   => 'Check thông tin số điện thoại',
            'history' => PhoneCheckService::history((int)Auth::id(), 30),
            'price'   => ServicePriceService::price(self::PRICE_CODE, 3000.0),
            'balance' => Wallet::balance((int)Auth::id()),
        ]);
    }

    /**
     * Tra cứu 1 số.
     */
    public function check(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $phone  = trim((string)($_POST['phone'] ?? ''));

        if ($phone === '') {
            flash('error', 'Vui lòng nhập số điện thoại cần tra cứu.');
            redirect(url('/services/phone-check'));
        }

        $price = ServicePriceService::price(self::PRICE_CODE, 3000.0);
        $balance = Wallet::balance($userId);

        if ($balance < $price) {
            flash('error', 'Số dư không đủ. Cần ' . money($price) . ' để tra cứu 1 số. Vui lòng nạp thêm.');
            redirect(url('/services/phone-check'));
        }

        $res = PhoneCheckService::lookup($phone);
        if (!$res['ok']) {
            flash('error', $res['message']);
            redirect(url('/services/phone-check'));
        }

        $data = $res['data'];

        // Trừ tiền sau khi tra cứu thành công
        try {
            $after = Wallet::apply(
                $userId,
                'adjustment',
                -$price,
                'Tra cứu thông tin số điện thoại ' . $data['phone'],
                'phone_check',
                $data['phone'],
                ['carrier' => $data['carrier']]
            );
        } catch (\Throwable $e) {
            flash('error', 'Lỗi trừ phí: ' . $e->getMessage());
            redirect(url('/services/phone-check'));
        }

        PhoneCheckService::log($userId, $data['phone'], $data, $price, $after);

        $_SESSION['phone_check_result'] = $data;

        flash('success', $res['message']);
        redirect(url('/services/phone-check'));
    }

    /**
     * Tra cứu nhiều số (tối đa 100).
     */
    public function batch(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $raw    = trim((string)($_POST['target_list'] ?? ''));

        $phones = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $p = PhoneCheckService::normalize($chunk);
            if ($p !== '') {
                $phones[] = $p;
            }
        }
        $phones = array_slice(array_values(array_unique($phones)), 0, 100);

        if (!$phones) {
            flash('error', 'Vui lòng nhập ít nhất 1 số điện thoại hợp lệ.');
            redirect(url('/services/phone-check'));
        }

        $price = ServicePriceService::price('phone-check-batch', 2500.0);
        $total = $price * count($phones);

        if (Wallet::balance($userId) < $total) {
            flash('error', 'Số dư không đủ. Cần ' . money($total) . ' cho ' . count($phones) . ' số.');
            redirect(url('/services/phone-check'));
        }

        $rows = [];
        foreach ($phones as $p) {
            $res = PhoneCheckService::lookup($p);
            if (!$res['ok']) {
                $rows[] = ['phone' => $p, 'status' => 'error', 'message' => $res['message']];
                continue;
            }
            $d = $res['data'];
            $rows[] = [
                'phone'     => $d['phone'],
                'status'    => $d['status'],
                'carrier'   => $d['carrier'],
                'full_name' => $d['full_name'],
                'id_card'   => $d['id_card'],
                'address'   => $d['address'],
                'fb_url'    => $d['fb_url'],
            ];
        }

        try {
            $after = Wallet::apply(
                $userId,
                'adjustment',
                -$total,
                'Tra cứu hàng loạt ' . count($phones) . ' số điện thoại',
                'phone_check_batch',
                null,
                ['count' => count($phones)]
            );
        } catch (\Throwable $e) {
            flash('error', 'Lỗi trừ phí: ' . $e->getMessage());
            redirect(url('/services/phone-check'));
        }

        // Ghi nhật ký từng số
        foreach ($rows as $r) {
            if ($r['status'] === 'error') {
                continue;
            }
            PhoneCheckService::log($userId, $r['phone'], [
                'phone'     => $r['phone'],
                'carrier'   => $r['carrier'],
                'full_name' => $r['full_name'],
                'id_card'   => $r['id_card'],
                'address'   => $r['address'],
                'fb_url'    => $r['fb_url'],
                'status'    => $r['status'],
                'found'     => $r['status'] === 'found',
            ], $price, $after);
        }

        $_SESSION['phone_check_batch'] = $rows;

        flash('success', 'Đã tra cứu ' . count($rows) . ' số.');
        redirect(url('/services/phone-check'));
    }

    /**
     * Lịch sử tra cứu.
     */
    public function history(): void
    {
        view('services/phone-check-history', [
            'title'   => 'Lịch sử tra cứu số điện thoại',
            'history' => PhoneCheckService::history((int)Auth::id(), 100),
        ]);
    }
}
