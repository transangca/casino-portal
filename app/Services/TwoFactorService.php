<?php
namespace App\Services;

use App\Core\Database;

/**
 * Mã hoá TOTP (RFC 6238) + sinh QR code — không cần thư viện ngoài.
 *
 * QR được dựng bằng bộ sinh tối giản (byte mode, ECC level M, tự chọn
 * version) và render thành SVG để nhúng trực tiếp — không phụ thuộc API
 * ngoài (tránh bị chặn mạng trên hosting VN).
 */
class TwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const OTP_LENGTH = 6;
    private const TOTP_PERIOD = 30;
    private const DIGITS = 6;

    // ------------------------------------------------------------------
    // Secret
    // ------------------------------------------------------------------

    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    public static function base32Decode(string $b32): string
    {
        $b32    = strtoupper(preg_replace('/\s+/', '', $b32) ?? '');
        $binary = '';
        $buffer = 0;
        $bits   = 0;
        foreach (str_split($b32) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bits  += 5;
            if ($bits >= 8) {
                $bits   -= 8;
                $binary .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $binary;
    }

    // ------------------------------------------------------------------
    // TOTP
    // ------------------------------------------------------------------

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter   = intdiv($timestamp, self::TOTP_PERIOD);

        $binary = self::base32Decode($secret);
        if ($binary === '') {
            return str_repeat('0', self::DIGITS);
        }

        $msg  = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $msg, $binary, true);

        $offset = ord($hash[19]) & 0x0F;
        $value  = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, time() + $i * self::TOTP_PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Recovery codes
    // ------------------------------------------------------------------

    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)));
        }
        return $codes;
    }

    // ------------------------------------------------------------------
    // otpauth:// URI
    // ------------------------------------------------------------------

    public static function otpauthUri(string $secret, string $account, string $issuer = 'Casino Portal'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::TOTP_PERIOD;
    }

    // ==================================================================
    // QR Code
    // ==================================================================

    public static function qrSvg(string $text, int $moduleSize = 6, int $margin = 4): string
    {
        $qr = self::qrMatrix($text);
        if ($qr === null) {
            return '';
        }

        $count = count($qr);
        $size  = ($count + $margin * 2) * $moduleSize;

        $paths = '';
        for ($y = 0; $y < $count; $y++) {
            $x = 0;
            while ($x < $count) {
                if ($qr[$y][$x] !== 1) {
                    $x++;
                    continue;
                }
                $len = 1;
                while ($x + $len < $count && $qr[$y][$x + $len] === 1) {
                    $len++;
                }
                $paths .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d"/>',
                    ($x + $margin) * $moduleSize,
                    ($y + $margin) * $moduleSize,
                    $len * $moduleSize,
                    $moduleSize
                );
                $x += $len;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" '
            . 'shape-rendering="crispEdges" role="img" aria-label="QR">'
            . '<rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%2$s</g></svg>',
            $size,
            $paths
        );
    }

    public static function qrDataUri(string $text): string
    {
        $svg = self::qrSvg($text);
        if ($svg === '') {
            return '';
        }
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Ma trận QR (m[row][col] = 0|1). Byte mode + ECC level M.
     */
    public static function qrMatrix(string $text): ?array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len   = count($bytes);
        if ($len === 0) {
            $bytes = [0];
            $len   = 1;
        }

        $spec = self::pickVersion($len);
        if ($spec === null) {
            return null;
        }

        $version  = $spec['version'];
        $countBits = $version <= 9 ? 8 : 16;
        $totalDataBytes = $spec['data_codewords'];

        // ---- 1. Bitstream -------------------------------------------------
        $bits = '0100';                                     // byte mode
        $bits .= str_pad(decbin($len), $countBits, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $capacity = $totalDataBytes * 8;
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));   // terminator
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }
        $pad = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacity) {
            $bits .= $pad[$i % 2];
            $i++;
        }

        // ---- 2. Chia block + Reed-Solomon --------------------------------
        $codewords = self::buildCodewords($bits, $spec);

        // ---- 3. Dựng ma trận ---------------------------------------------
        $size = 17 + 4 * $version;
        $m    = array_fill(0, $size, array_fill(0, $size, 0));
        $fn   = array_fill(0, $size, array_fill(0, $size, false));

        self::drawFunctionPatterns($m, $fn, $size, $version);

        // ---- 4. Đặt dữ liệu ----------------------------------------------
        self::drawCodewords($m, $fn, $size, $codewords);

        // ---- 5. Chọn mask tốt nhất ---------------------------------------
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $test = $m;
            self::applyMask($test, $fn, $size, $mask);
            self::drawFormatBits($test, $fn, $size, $mask);
            $score = self::penalty($test, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $test;
            }
        }

        return $best;
    }

    /**
     * Chọn version nhỏ nhất chứa được payload (ECC M).
     */
    private static function pickVersion(int $len): ?array
    {
        foreach (self::ecTable() as $version => $spec) {
            $countBits = $version <= 9 ? 8 : 16;
            $need = 4 + $countBits + $len * 8;
            if ($need <= $spec['data_codewords'] * 8) {
                $spec['version'] = $version;
                return $spec;
            }
        }
        return null;
    }

    /**
     * Tách data codewords thành block, tính ECC, rồi xen kẽ.
     */
    private static function buildCodewords(string $bits, array $spec): array
    {
        $dataBytes = [];
        for ($i = 0; $i < $spec['data_codewords']; $i++) {
            $chunk = substr($bits, $i * 8, 8);
            $dataBytes[] = bindec(str_pad($chunk, 8, '0', STR_PAD_LEFT));
        }

        $g1 = $spec['blocks1'];
        $g2 = $spec['blocks2'];
        $d1 = $spec['data1'];
        $d2 = $spec['data2'];
        $ecLen = $spec['ec'];

        // Tách block
        $dataBlocks = [];
        $offset = 0;
        for ($i = 0; $i < $g1; $i++) {
            $dataBlocks[] = array_slice($dataBytes, $offset, $d1);
            $offset += $d1;
        }
        for ($i = 0; $i < $g2; $i++) {
            $dataBlocks[] = array_slice($dataBytes, $offset, $d2);
            $offset += $d2;
        }

        // ECC từng block
        $ecBlocks = [];
        foreach ($dataBlocks as $block) {
            $ecBlocks[] = self::reedSolomon($block, $ecLen);
        }

        // Xen kẽ
        $out = [];
        $maxData = max($d1, $d2);
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $bi => $block) {
                $len = ($bi < $g1) ? $d1 : $d2;
                if ($i < $len) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    /** Reed-Solomon over GF(256), primitive poly 0x11D */
    private static function reedSolomon(array $data, int $ecLen): array
    {
        $exp = [];
        $log = [];
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        // Generator polynomial
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j]     ^= $gen[$j];
                $next[$j + 1] ^= $exp[($log[$gen[$j]] + $i) % 255];
            }
            $gen = $next;
        }

        $rem = array_fill(0, $ecLen, 0);
        foreach ($data as $b) {
            $factor = $b ^ $rem[0];
            array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                $logF = $log[$factor];
                for ($j = 0; $j < $ecLen; $j++) {
                    if ($gen[$j + 1] !== 0) {
                        $rem[$j] ^= $exp[($log[$gen[$j + 1]] + $logF) % 255];
                    }
                }
            }
        }
        return $rem;
    }

    /**
     * Vẽ các pattern chức năng: finder, separator, timing, alignment, dark, version.
     */
    private static function drawFunctionPatterns(array &$m, array &$fn, int $size, int $version): void
    {
        // Timing pattern
        for ($i = 0; $i < $size; $i++) {
            self::setModule($m, $fn, $i, 6, $i % 2 === 0);
            self::setModule($m, $fn, 6, $i, $i % 2 === 0);
        }

        // Finder patterns (3 góc) + separator
        self::drawFinder($m, $fn, $size, 3, 3);
        self::drawFinder($m, $fn, $size, $size - 4, 3);
        self::drawFinder($m, $fn, $size, 3, $size - 4);

        // Alignment patterns
        $centers = self::alignmentCenters($version);
        $n = count($centers);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $isCorner = ($i === 0 && $j === 0)
                    || ($i === 0 && $j === $n - 1)
                    || ($i === $n - 1 && $j === 0);
                if ($isCorner) {
                    continue;
                }
                self::drawAlignment($m, $fn, $centers[$i], $centers[$j]);
            }
        }

        // Dark module
        self::setModule($m, $fn, 8, $size - 8, true);

        // Reserve format info positions (giá trị sẽ vẽ lại khi chọn mask)
        self::drawFormatBits($m, $fn, $size, 0);

        // Version info (version >= 7)
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
            }
            $vbits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = ($vbits >> $i) & 1;
                $a = $size - 11 + ($i % 3);
                $b = intdiv($i, 3);
                self::setModule($m, $fn, $a, $b, (bool)$bit);
                self::setModule($m, $fn, $b, $a, (bool)$bit);
            }
        }
    }

    private static function drawFinder(array &$m, array &$fn, int $size, int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $x >= $size || $y < 0 || $y >= $size) {
                    continue;
                }
                $dist = max(abs($dx), abs($dy));
                self::setModule($m, $fn, $x, $y, $dist !== 2 && $dist !== 4);
            }
        }
    }

    private static function drawAlignment(array &$m, array &$fn, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                self::setModule($m, $fn, $cx + $dx, $cy + $dy, $dist !== 1);
            }
        }
    }

    private static function setModule(array &$m, ?array &$fn, int $x, int $y, bool $dark): void
    {
        $m[$y][$x] = $dark ? 1 : 0;
        if ($fn !== null) {
            $fn[$y][$x] = true;
        }
    }

    /**
     * Đặt codewords vào ma trận (zigzag từ phải sang trái, dưới lên trên).
     */
    private static function drawCodewords(array &$m, array $fn, int $size, array $codewords): void
    {
        $bitIdx = 0;
        $total  = count($codewords) * 8;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) === 0);
                    $y = $upward ? $size - 1 - $vert : $vert;

                    if ($fn[$y][$x]) {
                        continue;
                    }
                    $bit = 0;
                    if ($bitIdx < $total) {
                        $byte = $codewords[$bitIdx >> 3];
                        $bit  = ($byte >> (7 - ($bitIdx & 7))) & 1;
                        $bitIdx++;
                    }
                    $m[$y][$x] = $bit;
                }
            }
        }
    }

    private static function applyMask(array &$m, array $fn, int $size, int $mask): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($fn[$y][$x]) {
                    continue;
                }
                $invert = false;
                switch ($mask) {
                    case 0: $invert = (($x + $y) % 2 === 0); break;
                    case 1: $invert = ($y % 2 === 0); break;
                    case 2: $invert = ($x % 3 === 0); break;
                    case 3: $invert = (($x + $y) % 3 === 0); break;
                    case 4: $invert = ((intdiv($x, 3) + intdiv($y, 2)) % 2 === 0); break;
                    case 5: $invert = (((($x * $y) % 2) + (($x * $y) % 3)) === 0); break;
                    case 6: $invert = (((($x * $y) % 2) + (($x * $y) % 3)) % 2 === 0); break;
                    case 7: $invert = (((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0); break;
                }
                if ($invert) {
                    $m[$y][$x] ^= 1;
                }
            }
        }
    }

    private static function drawFormatBits(array &$m, ?array &$fn, int $size, int $mask): void
    {
        // ECC level M = 0b00
        $data = (0b00 << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        $get = static fn(int $i): int => ($bits >> $i) & 1;

        // Copy 1
        for ($i = 0; $i <= 5; $i++) {
            self::setModule($m, $fn, 8, $i, (bool)$get($i));
        }
        self::setModule($m, $fn, 8, 7, (bool)$get(6));
        self::setModule($m, $fn, 8, 8, (bool)$get(7));
        self::setModule($m, $fn, 7, 8, (bool)$get(8));
        for ($i = 9; $i < 15; $i++) {
            self::setModule($m, $fn, 14 - $i, 8, (bool)$get($i));
        }

        // Copy 2
        for ($i = 0; $i < 8; $i++) {
            self::setModule($m, $fn, $size - 1 - $i, 8, (bool)$get($i));
        }
        for ($i = 8; $i < 15; $i++) {
            self::setModule($m, $fn, 8, $size - 15 + $i, (bool)$get($i));
        }

        // Dark module
        self::setModule($m, $fn, 8, $size - 8, true);
    }

    private static function alignmentCenters(int $version): array
    {
        static $table = [
            1  => [],
            2  => [6, 18],
            3  => [6, 22],
            4  => [6, 26],
            5  => [6, 30],
            6  => [6, 34],
            7  => [6, 22, 38],
            8  => [6, 24, 42],
            9  => [6, 26, 46],
            10 => [6, 28, 50],
            11 => [6, 30, 54],
            12 => [6, 32, 58],
            13 => [6, 34, 62],
            14 => [6, 26, 46, 66],
            15 => [6, 26, 48, 70],
            16 => [6, 26, 50, 74],
            17 => [6, 30, 54, 78],
            18 => [6, 30, 56, 82],
            19 => [6, 30, 58, 86],
            20 => [6, 34, 62, 90],
        ];
        return $table[$version] ?? [6, ($version * 4 + 10)];
    }

    /** Chấm điểm penalty (N1..N4) */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;

        // N1: 5+ module liên tiếp cùng màu
        for ($y = 0; $y < $size; $y++) {
            $run = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($m[$y][$x] === $m[$y][$x - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }
                    $run = 1;
                }
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $run = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($m[$y][$x] === $m[$y - 1][$x]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }
                    $run = 1;
                }
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }

        // N2: khối 2x2 cùng màu
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // N3: finder-like pattern 1:1:3:1:1
        $p1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $p2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        $run = 11;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - $run; $x++) {
                $slice = array_slice($m[$y], $x, $run);
                if ($slice === $p1 || $slice === $p2) {
                    $score += 40;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y <= $size - $run; $y++) {
                $slice = [];
                for ($k = 0; $k < $run; $k++) {
                    $slice[] = $m[$y + $k][$x];
                }
                if ($slice === $p1 || $slice === $p2) {
                    $score += 40;
                }
            }
        }

        // N4: tỷ lệ module đen
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $total = $size * $size;
        $k = (int)(abs($dark * 100 / $total - 50) / 5);
        $score += $k * 10;

        return $score;
    }

    /**
     * Bảng ECC level M: version => [data_codewords, ec_per_block, blocks1, data1, blocks2, data2]
     */
    private static function ecTable(): array
    {
        return [
            1  => ['data_codewords' => 16,  'ec' => 10, 'blocks1' => 1, 'data1' => 16, 'blocks2' => 0, 'data2' => 0],
            2  => ['data_codewords' => 28,  'ec' => 16, 'blocks1' => 1, 'data1' => 28, 'blocks2' => 0, 'data2' => 0],
            3  => ['data_codewords' => 44,  'ec' => 26, 'blocks1' => 1, 'data1' => 44, 'blocks2' => 0, 'data2' => 0],
            4  => ['data_codewords' => 64,  'ec' => 18, 'blocks1' => 2, 'data1' => 32, 'blocks2' => 0, 'data2' => 0],
            5  => ['data_codewords' => 86,  'ec' => 24, 'blocks1' => 2, 'data1' => 43, 'blocks2' => 0, 'data2' => 0],
            6  => ['data_codewords' => 108, 'ec' => 16, 'blocks1' => 4, 'data1' => 27, 'blocks2' => 0, 'data2' => 0],
            7  => ['data_codewords' => 124, 'ec' => 18, 'blocks1' => 4, 'data1' => 31, 'blocks2' => 0, 'data2' => 0],
            8  => ['data_codewords' => 154, 'ec' => 22, 'blocks1' => 2, 'data1' => 38, 'blocks2' => 2, 'data2' => 39],
            9  => ['data_codewords' => 182, 'ec' => 22, 'blocks1' => 3, 'data1' => 36, 'blocks2' => 2, 'data2' => 37],
            10 => ['data_codewords' => 216, 'ec' => 26, 'blocks1' => 4, 'data1' => 43, 'blocks2' => 1, 'data2' => 44],
            11 => ['data_codewords' => 254, 'ec' => 30, 'blocks1' => 1, 'data1' => 50, 'blocks2' => 4, 'data2' => 51],
            12 => ['data_codewords' => 290, 'ec' => 22, 'blocks1' => 6, 'data1' => 36, 'blocks2' => 2, 'data2' => 37],
            13 => ['data_codewords' => 334, 'ec' => 22, 'blocks1' => 8, 'data1' => 37, 'blocks2' => 1, 'data2' => 38],
            14 => ['data_codewords' => 365, 'ec' => 24, 'blocks1' => 4, 'data1' => 40, 'blocks2' => 5, 'data2' => 41],
            15 => ['data_codewords' => 415, 'ec' => 24, 'blocks1' => 5, 'data1' => 41, 'blocks2' => 5, 'data2' => 42],
            16 => ['data_codewords' => 453, 'ec' => 28, 'blocks1' => 7, 'data1' => 45, 'blocks2' => 3, 'data2' => 46],
            17 => ['data_codewords' => 507, 'ec' => 28, 'blocks1' => 10, 'data1' => 46, 'blocks2' => 1, 'data2' => 47],
            18 => ['data_codewords' => 563, 'ec' => 26, 'blocks1' => 9, 'data1' => 43, 'blocks2' => 4, 'data2' => 44],
            19 => ['data_codewords' => 627, 'ec' => 26, 'blocks1' => 3, 'data1' => 44, 'blocks2' => 11, 'data2' => 45],
            20 => ['data_codewords' => 669, 'ec' => 30, 'blocks1' => 3, 'data1' => 41, 'blocks2' => 13, 'data2' => 42],
        ];
    }

    // ------------------------------------------------------------------
    // Lưu trữ / trạng thái
    // ------------------------------------------------------------------

    public static function status(int $userId): array
    {
        $row = Database::fetch('SELECT * FROM user_twofa WHERE user_id = ?', [$userId]);
        return [
            'enabled'  => (bool)($row['is_enabled'] ?? false),
            'secret'   => (string)($row['secret'] ?? ''),
            'has_pending' => (bool)$row && empty($row['confirmed_at']),
            'recovery' => $row ? (json_decode((string)$row['recovery_codes'], true) ?: []) : [],
        ];
    }

    public static function enable(int $userId, string $secret, array $recoveryCodes): void
    {
        Database::query(
            'INSERT INTO user_twofa (user_id, secret, recovery_codes, is_enabled, confirmed_at)
             VALUES (?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE secret = VALUES(secret), recovery_codes = VALUES(recovery_codes),
                                     is_enabled = 1, confirmed_at = NOW()',
            [$userId, $secret, json_encode($recoveryCodes)]
        );
    }

    public static function disable(int $userId): void
    {
        Database::delete('user_twofa', 'user_id = ?', [$userId]);
    }

    public static function isRequired(int $userId): bool
    {
        return (bool)Database::fetchColumn(
            'SELECT is_enabled FROM user_twofa WHERE user_id = ?',
            [$userId]
        );
    }

    public static function consumeRecovery(int $userId, string $code): bool
    {
        $row = Database::fetch('SELECT recovery_codes FROM user_twofa WHERE user_id = ?', [$userId]);
        if (!$row) {
            return false;
        }
        $codes = json_decode((string)$row['recovery_codes'], true) ?: [];
        $code  = strtoupper(trim($code));
        $idx   = array_search($code, $codes, true);
        if ($idx === false) {
            return false;
        }
        unset($codes[$idx]);
        Database::update(
            'user_twofa',
            ['recovery_codes' => json_encode(array_values($codes))],
            'user_id = ?',
            [$userId]
        );
        return true;
    }
}
