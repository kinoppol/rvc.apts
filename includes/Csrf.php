<?php

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function check(): void
    {
        $sent = $_POST['csrf'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '' || !hash_equals($expected, $sent)) {
            http_response_code(400);
            exit('Invalid CSRF token.');
        }
    }
}
