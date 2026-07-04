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
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Align MySQL NOW() with the app's Asia/Bangkok timezone (Thailand is a fixed +07:00, no DST),
            // so SQL time comparisons match PHP's DateTimeImmutable comparisons.
            self::$pdo->exec("SET time_zone = '+07:00'");
        }
        return self::$pdo;
    }
}
