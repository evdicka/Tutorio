<?php
declare(strict_types=1);

/**
 * Minimaler .env-Loader ohne externe Abhängigkeiten (vgl. ADR-003:
 * bewusster Verzicht auf zusätzliche Build-/Dependency-Toolchain).
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException("Env-Datei nicht gefunden: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

function env(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException("Fehlende Umgebungsvariable: {$key}");
    }
    return $value;
}
