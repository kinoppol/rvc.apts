<?php

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                // Align MySQL NOW() with the app's Asia/Bangkok timezone (Thailand is a fixed +07:00, no DST),
                // so SQL time comparisons match PHP's DateTimeImmutable comparisons.
                self::$pdo->exec("SET time_zone = '+07:00'");
            } catch (PDOException $e) {
                // DB unreachable or not yet created — send the browser to the web installer.
                // APP_BASE is already defined by bootstrap.php before any page calls pdo().
                $base = defined('APP_BASE') ? APP_BASE : '';
                header('Location: ' . $base . '/install.php');
                exit;
            }
        }
        return self::$pdo;
    }
}
