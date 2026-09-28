<?php

require_once __DIR__ . '/../Jwt.php';

final class RequireAuth
{
    public static function check(): array
    {
        $token = $_COOKIE['tutorio_token'] ?? null;

        if ($token === null) {
            self::reject();
        }

        try {
            return Jwt::verifyAndDecode($token, getenv('TUTORIO_JWT_SECRET'));
        } catch (Throwable $e) {
            self::reject();
        }
    }

    private static function reject(): never
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Nicht angemeldet.']);
        exit;
    }
}
