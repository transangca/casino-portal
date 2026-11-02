<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\CardService;
use App\Services\CsoService;
use App\Services\VietQrService;

/**
 * Ví: nạp tiền, rút tiền, lịch sử giao dịch, giftcode.
 */
class WalletController
{
    public function index(): void
    {
        $userId  = (int)Auth::id();
        $balance = Wallet::balance($userId);

        $history = Wallet::history($userId, 50);

        $deposits = Database::fetchAll(
            'SELECT * FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 20',
            [$userId]
        );

        $withdrawals = Database::fetchAll(
            'SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 20',
            [$userId]
        );

        view('wallet/index', compact('balance', 'history', 'deposits', 'withdrawals'));
    }

    public function showDeposit(): void
    {
        $userId  = (int)Auth::id();
        $balance = Wallet::balance($userId);

        $methods = Database::fetchAll(
            'SELECT * FROM settings WHERE name LIKE "deposit_method_%"'
        );

        // Tài khoản ngân hàng nhận tiền (cấu hình trong admin)
        $banks = Database::fetchAll(
            'SELECT * FROM payment_banks WHERE is_active = 1 ORDER BY sort_order, bank_name'
        );

        // Mệnh giá thẻ cào đang bán
        $cardDenoms = CardService::denominations();

        view('wallet/deposit', compact('balance', 'methods', 'banks', 'cardDenoms'));
    }

    /**
     * Quy đổi thẻ cào → cộng tiền thẳng vào ví.
     *
     * POST /wallet/deposit/card { denomination, serial, pin }
     */
    public function redeemCard(): void
    {
        CSRF::verifyOrFail();

        $result = CardService::redeem(
            (int)Auth::id(),
            trim((string)($_POST['denomination'] ?? '')),
            trim((string)($_POST['serial'] ?? '')),
            trim((string)($_POST['pin'] ?? ''))
        );

        flash($result['ok'] ? 'success' : 'error', (string)$result['message']);
        redirect(url('/wallet/deposit'));
    }

    /**
     * Tạo mã QR VietQR theo số tiền người dùng nhập.
     *
     * POST /wallet/deposit/qr { amount, bank }
     */
    public function createQrCode(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $amount = (float)($_POST['amount'] ?? 0);
        $bankCode = trim((string)($_POST['bank'] ?? ''));

        // Không giới hạn số tiền — chỉ chặn số không hợp lệ
        if ($amount <= 0 || $amount > 100000000000) {
            json_error('Số tiền không hợp lệ. Vui lòng nhập số tiền muốn nạp.', 422);
        }

        $bank = Database::fetch(
            'SELECT * FROM payment_banks WHERE bank_code = ? AND is_active = 1',
            [$bankCode]
        );

        if (!$bank) {
            json_error('Ngân hàng không hợp lệ hoặc đã ngừng hoạt động.', 422);
        }

        // Mã nội dung chuyển khoản riêng cho từng lần nạp
        $code = 'NAP' . strtoupper(bin2hex(random_bytes(4)));

        $qr = VietQrService::create(
            (string)$bank['account_no'],
            (string)$bank['account_name'],
            (string)$bank['acq_id'],
            (string)$bank['bank_code'],
            $amount,
            $code,
            'compact2'
        );

        if (!$qr) {
            json_error('Không kết nối được máy chủ VietQR. Vui lòng thử lại sau.', 502);
        }

        // Lưu phiếu nạp ở trạng thái chờ để admin đối soát
        $bonusRate = (float)setting('deposit_bonus_rate', '0.10');
        $bonus     = round($amount * $bonusRate, 2);

        $depositId = Database::insert('deposits', [
            'user_id'         => $userId,
            'amount'          => $amount,
            'bonus_amount'    => $bonus,
            'method'          => 'bank',
            'transaction_code'=> $code,
            'status'          => 'pending',
        ]);

        Database::insert('deposit_codes', [
            'deposit_id'  => $depositId,
            'user_id'     => $userId,
            'code'        => $code,
            'amount'      => $amount,
            'qr_data'     => $qr['qrCode'],
            'qr_image'    => $qr['qrDataURL'],
            'qr_url'      => $qr['imgURL'],
            'bank_code'   => $bankCode,
            'status'      => 'active',
            'expires_at'  => date('Y-m-d H:i:s', time() + 86400),
        ]);

        audit('deposit.qr.create', 'deposits', (string)$depositId, [
            'amount' => $amount,
            'bank'   => $bankCode,
            'code'   => $code,
        ]);

        json_success([
            'amount'     => $amount,
            'bonus'      => $bonus,
            'code'       => $code,
            'bank_name'  => $bank['bank_name'],
            'account_no' => $bank['account_no'],
            'account_name'=> $bank['account_name'],
            'qr_image'   => $qr['qrDataURL'] ?: $qr['imgURL'],
            'qr_code'    => $qr['qrCode'],
            'deposit_id' => (string)$depositId,
        ]);
    }

    public function submitDeposit(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $amount = (float)($_POST['amount'] ?? 0);
        $method = (string)($_POST['method'] ?? 'bank');
        $code   = trim((string)($_POST['transaction_code'] ?? ''));

        // Không áp đặt mức tối thiểu / tối đa cứng — người dùng nhập bao nhiêu cũng được.
        // Admin đối soát theo mã giao dịch thực tế.
        if ($amount <= 0) {
            flash('error', 'Vui lòng nhập số tiền muốn nạp.');
            redirect(url('/wallet/deposit'));
        }
        if ($code === '') {
            flash('error', 'Vui lòng nhập mã giao dịch.');
            redirect(url('/wallet/deposit'));
        }

        // Trùng mã giao dịch
        $dup = Database::fetchColumn(
            'SELECT id FROM deposits WHERE transaction_code = ? AND status = "pending"',
            [$code]
        );
        if ($dup) {
            flash('error', 'Mã giao dịch này đang được xử lý.');
            redirect(url('/wallet/deposit'));
        }

        // Ảnh bill chuyển khoản (không bắt buộc)
        $billImage = self::saveBillImage($userId);
        $billNote  = trim((string)($_POST['bill_note'] ?? ''));
        if (strlen($billNote) > 500) {
            $billNote = mb_substr($billNote, 0, 500);
        }

        $bonusRate = (float)setting('deposit_bonus_rate', '0.10');
        $bonus     = round($amount * $bonusRate, 2);

        Database::insert('deposits', [
            'user_id'         => $userId,
            'amount'          => $amount,
            'bonus_amount'    => $bonus,
            'method'          => $method,
            'transaction_code'=> $code,
            'bill_image'      => $billImage,
            'bill_note'       => $billNote !== '' ? $billNote : null,
            'status'          => 'pending',
        ]);

        audit('deposit.create', 'deposits', $code, ['amount' => $amount]);
        flash('success', 'Đã gửi yêu cầu nạp tiền. Chúng tôi sẽ xác nhận trong thời gian ngắn.');
        redirect(url('/wallet'));
    }

    /**
     * Lưu ảnh bill chuyển khoản vào /public/uploads/bills.
     */
    private static function saveBillImage(int $userId): ?string
    {
        if (empty($_FILES['bill_image']) || !is_array($_FILES['bill_image'])) {
            return null;
        }
        $file = $_FILES['bill_image'];
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            flash('error', 'Không tải lên được ảnh bill. Vui lòng thử lại.');
            return null;
        }
        if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
            flash('error', 'Ảnh bill không được vượt quá 5MB.');
            return null;
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            flash('error', 'Ảnh bill phải là JPG, PNG, WEBP hoặc GIF.');
            return null;
        }

        $mime = (string)(@mime_content_type((string)$file['tmp_name']) ?: '');
        if ($mime !== '' && !in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            flash('error', 'Tệp đính kèm không phải hình ảnh hợp lệ.');
            return null;
        }

        $dir = BASE_PATH . '/public/uploads/bills';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = 'bill_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!@move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $filename)) {
            flash('error', 'Không lưu được ảnh bill. Vui lòng thử lại.');
            return null;
        }

        return '/uploads/bills/' . $filename;
    }

    public function showWithdraw(): void
    {
        $userId  = (int)Auth::id();
        $balance = Wallet::balance($userId);

        view('wallet/withdraw', compact('balance'));
    }

    public function submitWithdraw(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $amount = (float)($_POST['amount'] ?? 0);
        $bank   = trim((string)($_POST['bank_name'] ?? ''));
        $accNo  = trim((string)($_POST['account_number'] ?? ''));
        $accNm  = trim((string)($_POST['account_name'] ?? ''));

        $balance = Wallet::balance($userId);
        $minWd   = (float)setting('withdraw_min', '100000');

        if ($amount < $minWd) {
            flash('error', 'Rút tối thiểu ' . money($minWd) . '.');
            redirect(url('/wallet/withdraw'));
        }
        if ($amount > $balance) {
            flash('error', 'Số dư không đủ để rút.');
            redirect(url('/wallet/withdraw'));
        }
        if ($bank === '' || $accNo === '' || $accNm === '') {
            flash('error', 'Vui lòng điền đầy đủ thông tin ngân hàng.');
            redirect(url('/wallet/withdraw'));
        }

        $fee    = round($amount * (float)setting('withdraw_fee_rate', '0.02'), 2);
        $net    = $amount - $fee;

        Database::insert('withdrawals', [
            'user_id'        => $userId,
            'amount'         => $amount,
            'fee'            => $fee,
            'net_amount'     => $net,
            'bank_name'      => $bank,
            'account_number' => $accNo,
            'account_name'   => $accNm,
            'status'         => 'pending',
        ]);

        audit('withdraw.create', 'withdrawals', null, ['amount' => $amount]);
        flash('success', 'Đã gửi yêu cầu rút tiền. Admin sẽ xử lý.');
        redirect(url('/wallet'));
    }

    /**
     * Đổi giftcode.
     */
    public function redeemGiftcode(): void
    {
        CSRF::verifyOrFail();

        $userId = (int)Auth::id();
        $code   = trim((string)($_POST['code'] ?? ''));

        if ($code === '') {
            flash('error', 'Vui lòng nhập mã giftcode.');
            redirect(url('/wallet'));
        }

        $gift = Database::fetch('SELECT * FROM giftcodes WHERE code = ? AND is_active = 1', [$code]);
        if (!$gift) {
            flash('error', 'Mã giftcode không tồn tại hoặc đã hết hạn.');
            redirect(url('/wallet'));
        }
        if ((int)$gift['used_count'] >= (int)$gift['max_uses']) {
            flash('error', 'Mã giftcode đã hết lượt sử dụng.');
            redirect(url('/wallet'));
        }
        if ($gift['expires_at'] && strtotime($gift['expires_at']) < time()) {
            flash('error', 'Mã giftcode đã hết hạn.');
            redirect(url('/wallet'));
        }

        $balance = Wallet::balance($userId);
        if ((float)$gift['min_deposit'] > 0 && $balance < (float)$gift['min_deposit']) {
            flash('error', 'Cần nạp tối thiểu ' . money((float)$gift['min_deposit']) . ' để dùng mã này.');
            redirect(url('/wallet'));
        }

        $used = Database::fetchColumn(
            'SELECT id FROM wallet_transactions WHERE user_id = ? AND type = "giftcode" AND ref_id = ?',
            [$userId, (string)$gift['id']]
        );
        if ($used) {
            flash('error', 'Bạn đã sử dụng mã giftcode này rồi.');
            redirect(url('/wallet'));
        }

        try {
            Wallet::apply(
                $userId,
                'giftcode',
                (float)$gift['amount'],
                'Giftcode ' . $code,
                'giftcode',
                (string)$gift['id'],
                ['code' => $code]
            );
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect(url('/wallet'));
        }

        Database::update('giftcodes', ['used_count' => (int)$gift['used_count'] + 1], 'id = :id', ['id' => $gift['id']]);

        // Hoa hồng CSO cho người giới thiệu
        CsoService::accrue($userId, (float)$gift['amount'], 'deposit');

        flash('success', 'Đổi giftcode thành công: +' . money((float)$gift['amount']));
        redirect(url('/wallet'));
    }

    /**
     * Lịch sử cược.
     */
    public function history(): void
    {
        $userId = (int)Auth::id();

        $rounds = Database::fetchAll(
            'SELECT gr.*, g.name AS game_name, g.icon
             FROM game_rounds gr
             JOIN game_sessions gs ON gs.id = gr.session_id
             JOIN games g ON g.id = gs.game_id
             WHERE gs.user_id = ? ORDER BY gr.id DESC LIMIT 100',
            [$userId]
        );

        $transactions = Wallet::history($userId, 100);

        view('wallet/history', compact('rounds', 'transactions'));
    }
}
