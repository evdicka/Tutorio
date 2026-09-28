<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../src/Middleware/RequireAuth.php';

loadEnv(__DIR__ . '/../.env');

$user = RequireAuth::check();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'id' => $user['sub'],
    'name' => $user['name'],
    'role' => $user['role'],
]);
