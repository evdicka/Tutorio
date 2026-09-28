<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

setcookie('tutorio_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

echo json_encode(['success' => true]);
