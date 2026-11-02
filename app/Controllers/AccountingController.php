<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Services\AccountingService;

/**
 * Phần mềm kế toán nhỏ lẻ (MISA-style) — fullscreen, thoát bằng ESC.
 */
class AccountingController
{
    public function index(): void
    {
        $month = date('Y-m');
        [$from, $to] = array_pad(explode('-', $month), 2, date('Y-m-d'));

        $pl = AccountingService::profitLoss($from . '-01', $to . '-31');
        $bs = AccountingService::balanceSheet(date('Y-m-d'));

        view('accounting/dashboard', [
            'title'   => 'Tổng quan kế toán',
            'section' => 'dashboard',
            'pl'      => $pl,
            'bs'      => $bs,
            'docs'    => AccountingService::documents('', 8),
        ], 'layouts/accounting');
    }

    public function documents(): void
    {
        $type = (string)($_GET['type'] ?? '');

        view('accounting/documents', [
            'title'   => 'Phiếu kế toán',
            'section' => 'documents',
            'types'   => AccountingService::DOC_TYPES,
            'docs'    => AccountingService::documents($type, 200),
            'currentType' => $type,
        ], 'layouts/accounting');
    }

    public function create(): void
    {
        view('accounting/create', [
            'title'    => 'Lập phiếu kế toán',
            'section'  => 'create',
            'types'    => AccountingService::DOC_TYPES,
            'accounts' => AccountingService::accounts(),
        ], 'layouts/accounting');
    }

    public function store(): void
    {
        CSRF::verifyOrFail();

        $lines = $_POST['lines'] ?? [];
        if (!is_array($lines)) {
            $lines = [];
        }

        $res = AccountingService::createDocument([
            'doc_type' => (string)($_POST['doc_type'] ?? 'journal'),
            'doc_date' => (string)($_POST['doc_date'] ?? date('Y-m-d')),
            'partner'  => (string)($_POST['partner'] ?? ''),
            'note'     => (string)($_POST['note'] ?? ''),
            'lines'    => $lines,
        ]);

        if ($res['ok']) {
            flash('success', $res['message']);
            redirect(url('/services/accounting/documents'));
        }

        flash('error', $res['message']);
        redirect(url('/services/accounting/create'));
    }

    public function ledger(): void
    {
        $account = (string)($_GET['account'] ?? '');
        $from    = (string)($_GET['from'] ?? date('Y-01-01'));
        $to      = (string)($_GET['to'] ?? date('Y-m-d'));

        view('accounting/ledger', [
            'title'    => 'Sổ cái',
            'section'  => 'ledger',
            'accounts' => AccountingService::accounts(),
            'rows'     => AccountingService::ledger($account, $from, $to),
            'account'  => $account,
            'from'     => $from,
            'to'       => $to,
        ], 'layouts/accounting');
    }

    public function reports(): void
    {
        $from = (string)($_GET['from'] ?? date('Y-01-01'));
        $to   = (string)($_GET['to'] ?? date('Y-m-d'));

        view('accounting/reports', [
            'title'   => 'Báo cáo tài chính',
            'section' => 'reports',
            'pl'      => AccountingService::profitLoss($from, $to),
            'bs'      => AccountingService::balanceSheet($to),
            'from'    => $from,
            'to'      => $to,
        ], 'layouts/accounting');
    }

    public function backup(): void
    {
        view('accounting/backup', [
            'title'   => 'Sao lưu dữ liệu',
            'section' => 'backup',
            'backups' => AccountingService::backups(50),
            'drivers' => \App\Core\Database::fetchAll('SELECT * FROM storage_drivers WHERE is_active = 1 ORDER BY sort_order'),
        ], 'layouts/accounting');
    }

    public function runBackup(): void
    {
        CSRF::verifyOrFail();

        $res = AccountingService::backup();
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/accounting/backup'));
    }

    public function searchBackup(): void
    {
        $docNo = trim((string)($_GET['doc_no'] ?? ''));

        view('accounting/backup', [
            'title'   => 'Sao lưu dữ liệu',
            'section' => 'backup',
            'backups' => AccountingService::backups(50),
            'drivers' => \App\Core\Database::fetchAll('SELECT * FROM storage_drivers WHERE is_active = 1 ORDER BY sort_order'),
            'search'  => AccountingService::restoreSearch($docNo),
            'docNo'   => $docNo,
        ], 'layouts/accounting');
    }
}
