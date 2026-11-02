<?php
namespace App\Services\Provider;

use App\Core\Database;

/**
 * Factory: tạo driver theo mã provider trong DB.
 *
 * Mỗi dòng trong bảng `providers` có:
 *   code       : mã sảnh dùng trong URL (vd: pgsoft)
 *   driver     : tên lớp driver (pgsoft | generic-lobby | ...)
 *   config     : JSON override cho config mặc định trong config/app.php
 */
class ProviderFactory
{
    private static array $instances = [];

    public static function make(string $code): ?ProviderInterface
    {
        if (isset(self::$instances[$code])) {
            return self::$instances[$code];
        }

        $row = Database::fetch(
            'SELECT * FROM providers WHERE code = ? AND status = "active" LIMIT 1',
            [$code]
        );

        if (!$row) {
            return null;
        }

        $driver = (string)($row['driver'] ?? $code);

        // Config gốc từ config/app.php, ghi đè bằng JSON trong DB nếu có.
        $base = config('providers.' . $code, []);
        if (!is_array($base)) {
            $base = [];
        }
        $base['code'] = $code;
        $base['name'] = (string)($row['name'] ?? $base['name'] ?? $code);

        $override = $row['config'] ?? null;
        if (is_string($override) && $override !== '') {
            $decoded = json_decode($override, true);
            if (is_array($decoded)) {
                $base = array_merge($base, $decoded);
            }
        }

        $instance = match ($driver) {
            'pgsoft'       => new PgSoftProvider($base),
            'deno-casino',
            'deno'         => new DenoCasinoProvider($base),
            'generic-lobby',
            'generic'      => new GenericLobbyProvider($base),
            default        => null,
        };

        if ($instance === null) {
            return null;
        }

        return self::$instances[$code] = $instance;
    }

    /** Tất cả provider đang active */
    public static function all(): array
    {
        $rows = Database::fetchAll('SELECT * FROM providers WHERE status = "active" ORDER BY sort_order, name');
        $out  = [];
        foreach ($rows as $row) {
            $p = self::make((string)$row['code']);
            if ($p) {
                $out[] = $p;
            }
        }
        return $out;
    }

    public static function drivers(): array
    {
        return [
            'pgsoft'       => PgSoftProvider::class,
            'deno-casino'  => DenoCasinoProvider::class,
            'generic-lobby' => GenericLobbyProvider::class,
        ];
    }
}
