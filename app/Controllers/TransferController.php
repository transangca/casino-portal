<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Wallet;
use App\Services\TransferService;

/**
 * Chuyển tiền nội bộ giữa các thành viên.
 */
class TransferController
{
    public function index(): void
    {
        $userId  = (int)Auth::id();
        $balance = Wallet::balance($userId);
        $history = TransferService::history($userId, 50);

        view('wallet/transfer', [
            'title'   => 'Chuyển tiền',
            'balance' => $balance,
            'history' => $history,
            'feeRate' => TransferService::feeRate(),
            'min'     => (float)setting('transfer_min', 10000),
        ]);
    }

    public function send(): void
    {
        CSRF::verifyOrFail();

        $userId    = (int)Auth::id();
        $recipient = trim((string)($_POST['recipient'] ?? ''));
        $amount    = (float)($_POST['amount'] ?? 0);
        $note      = trim((string)($_POST['note'] ?? ''));

        $result = TransferService::send($userId, $recipient, $amount, $note);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect(url('/wallet/transfer'));
    }

    /**
     * API: tìm người nhận theo username/email (gợi ý nhanh).
     */
    public function lookup(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            json_success([]);
        }

        $rows = Database::fetchAll(
            'SELECT id, username, email, full_name, is_verified FROM users
             WHERE status = "active" AND id != ? AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)
             LIMIT 10',
            [(int)Auth::id(), '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'username' => $r['username'],
                'email'    => $r['email'],
                'full_name'=> $r['full_name'],
                'verified' => (bool)$r['is_verified'],
            ];
        }

        json_success($out);
    }
}
