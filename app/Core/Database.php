<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Lớp truy cập CSDL duy nhất (PDO singleton).
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = config('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode='STRICT_TRANS_TABLES'",
            ]);

            // Đồng bộ múi giờ MySQL với PHP để NOW() khớp với date().
            // Nếu không, gói chơi / phiên sẽ bị tính lệch (VD: MySQL SYSTEM=+7, PHP=UTC).
            self::$pdo->exec(self::sessionTimeZoneSql());
        } catch (PDOException $e) {
            logger('DB connection failed: ' . $e->getMessage(), 'error');
            if (config('app.debug')) {
                throw $e;
            }
            abort(500, 'Không thể kết nối cơ sở dữ liệu');
        }

        return self::$pdo;
    }

    /**
     * Câu lệnh đặt múi giờ session của MySQL khớp với timezone của PHP.
     *
     * MySQL mặc định dùng SYSTEM (= múi giờ máy chủ) trong khi PHP có thể
     * đặt Asia/Ho_Chi_Minh. Nếu lệch, NOW() trong SQL và date() trong PHP sẽ
     * khác nhau → gói chơi/phiên bị tính sai (mất 7 tiếng so với thực tế).
     */
    private static function sessionTimeZoneSql(): string
    {
        $tz = (string)date_default_timezone_get();

        // Ánh xạ timezone PHP → offset MySQL
        $offset = (new \DateTime('now'))->getOffset();
        $sign   = $offset < 0 ? '-' : '+';
        $abs    = abs($offset);
        $h      = intdiv($abs, 3600);
        $m      = intdiv($abs % 3600, 60);
        $value  = sprintf('%s%02d:%02d', $sign, $h, $m);

        return "SET time_zone = '{$value}'";
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $ph   = array_map(static fn($c) => ':' . $c, $cols);
        $sql  = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', $ph)
        );
        self::query($sql, $data);
        return (int)self::conn()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = "`$c` = :s_$c";
        }
        $params = [];
        foreach ($data as $k => $v) {
            $params["s_$k"] = $v;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(',', $sets), $where);
        return self::query($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    /**
     * Chạy closure trong transaction, tự rollback khi có exception.
     *
     * Hỗ trợ transaction lồng nhau bằng SAVEPOINT: nếu đã có transaction đang
     * mở, closure được chạy bên trong transaction đó và chỉ rollback tới
     * savepoint khi có lỗi — transaction ngoài cùng vẫn được commit.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::conn();

        // Đã có transaction đang mở → dùng savepoint
        if ($pdo->inTransaction()) {
            $sp = 'sp_' . substr(bin2hex(random_bytes(4)), 0, 8);
            $pdo->exec('SAVEPOINT ' . $sp);
            try {
                $result = $fn($pdo);
                $pdo->exec('RELEASE SAVEPOINT ' . $sp);
                return $result;
            } catch (\Throwable $e) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $sp);
                throw $e;
            }
        }

        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
