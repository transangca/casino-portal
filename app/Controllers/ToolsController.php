<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\AccountingService;
use App\Services\ToolsService;

/**
 * Công cụ tiện ích: hash, JSON, mật khẩu, đổi đơn vị, UUID, base64, IP, WHOIS, SEO, speed test.
 */
class ToolsController
{
    public function index(): void
    {
        view('tools/index', [
            'title'  => 'Công cụ tiện ích',
            'groups' => ToolsService::groups(),
        ], 'layouts/tools');
    }

    // ================= HASH =================

    public function hash(): void
    {
        $input = trim((string)($_POST['input'] ?? ''));
        $result = $input !== '' ? ToolsService::hash($input) : [];

        view('tools/hash', [
            'title'   => 'Hash chuỗi',
            'toolKey' => 'hash',
            'input'   => $input,
            'result'  => $result,
        ], 'layouts/tools');
    }

    // ================= JSON =================

    public function json(): void
    {
        $input = trim((string)($_POST['input'] ?? ''));
        $result = $input !== '' ? ToolsService::formatJson($input) : ['ok' => false];

        view('tools/json', [
            'title'   => 'Format JSON',
            'toolKey' => 'json',
            'input'   => $input,
            'result'  => $result,
        ], 'layouts/tools');
    }

    // ================= MẬT KHẨU =================

    public function password(): void
    {
        $length = (int)($_POST['length'] ?? 16);
        $passwords = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            for ($i = 0; $i < 10; $i++) {
                $passwords[] = ToolsService::generatePassword(
                    $length,
                    isset($_POST['symbols']),
                    isset($_POST['numbers'])
                );
            }
        }

        view('tools/password', [
            'title'     => 'Sinh mật khẩu mạnh',
            'toolKey'   => 'password',
            'length'    => max(8, min(64, $length)),
            'symbols'   => isset($_POST['symbols']) || $_SERVER['REQUEST_METHOD'] !== 'POST',
            'numbers'   => isset($_POST['numbers']) || $_SERVER['REQUEST_METHOD'] !== 'POST',
            'passwords' => $passwords,
        ], 'layouts/tools');
    }

    // ================= ĐỔI ĐƠN VỊ =================

    public function convert(): void
    {
        $group = (string)($_POST['group'] ?? 'length');
        $from  = (string)($_POST['from'] ?? '');
        $to    = (string)($_POST['to'] ?? '');
        $value = (float)($_POST['value'] ?? 0);

        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $from !== '' && $to !== '') {
            $result = ToolsService::convert($group, $from, $to, $value);
        }

        view('tools/convert', [
            'title'   => 'Đổi đơn vị',
            'toolKey' => 'convert',
            'groups'  => ToolsService::UNITS,
            'group'  => $group,
            'from'   => $from,
            'to'     => $to,
            'value'  => $value,
            'result' => $result,
        ], 'layouts/tools');
    }

    // ================= UUID =================

    public function uuid(): void
    {
        $count  = (int)($_POST['count'] ?? 5);
        $uuids  = ($_SERVER['REQUEST_METHOD'] === 'POST')
            ? ToolsService::uuid($count)
            : ToolsService::uuid(5);

        view('tools/uuid', [
            'title'   => 'Sinh UUID',
            'toolKey' => 'uuid',
            'count'   => max(1, min(50, $count)),
            'uuids' => $uuids,
        ], 'layouts/tools');
    }

    // ================= BASE64 =================

    public function base64(): void
    {
        $input  = trim((string)($_POST['input'] ?? ''));
        $mode   = (string)($_POST['mode'] ?? 'encode');
        $result = $input !== '' ? ToolsService::base64($input, $mode) : ['ok' => false];

        view('tools/base64', [
            'title'   => 'Encode / Decode Base64',
            'toolKey' => 'base64',
            'input'   => $input,
            'mode'   => $mode,
            'result' => $result,
        ], 'layouts/tools');
    }

    // ================= IP =================

    public function ip(): void
    {
        $ip     = trim((string)($_POST['ip'] ?? ''));
        $result = $ip !== '' ? ToolsService::ipInfo($ip) : ['ok' => false];

        view('tools/ip', [
            'title'   => 'Tra cứu IP',
            'toolKey' => 'ip',
            'ip'      => $ip,
            'result' => $result,
        ], 'layouts/tools');
    }

    // ================= WHOIS =================

    public function whois(): void
    {
        $domain = trim((string)($_POST['domain'] ?? ''));
        $result = $domain !== '' ? ToolsService::whois($domain) : ['ok' => false];

        view('tools/whois', [
            'title'   => 'Tra cứu WHOIS',
            'toolKey' => 'whois',
            'domain'  => $domain,
            'result' => $result,
        ], 'layouts/tools');
    }

    // ================= SEO =================

    public function seo(): void
    {
        $url    = trim((string)($_POST['url'] ?? ''));
        $result = $url !== '' ? ToolsService::seoCheck($url) : ['ok' => false];

        view('tools/seo', [
            'title'   => 'Kiểm tra SEO',
            'toolKey' => 'seo',
            'url'     => $url,
            'result' => $result,
        ], 'layouts/tools');
    }

    // ================= SPEED =================

    public function speed(): void
    {
        $url    = trim((string)($_POST['url'] ?? ''));
        $result = $url !== '' ? ToolsService::speedTest($url) : ['ok' => false];

        view('tools/speed', [
            'title'   => 'Đo tốc độ website',
            'toolKey' => 'speed',
            'url'     => $url,
            'result' => $result,
        ]);
    }
}


