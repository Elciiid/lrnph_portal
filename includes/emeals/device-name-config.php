<?php
declare(strict_types=1);

function getEmealsDeviceDefaults(): array {
    return [
        'emeals1' => 'Emeals 1',
        'emeals2' => 'Emeals 2',
    ];
}

function getEmealsDeviceConfigPath(): string {
    return __DIR__ . '/emeals-device-names.json';
}

function sanitizeEmealsDeviceName(string $value, int $maxLength = 64): string {
    $clean = preg_replace('/[^A-Za-z0-9\\s\\-_.]/', '', $value);
    if ($clean === null) {
        $clean = '';
    }

    if (function_exists('mb_substr')) {
        $clean = mb_substr($clean, 0, $maxLength);
    } else {
        $clean = substr($clean, 0, $maxLength);
    }

    return trim($clean);
}

function readEmealsDeviceNames(bool $refresh = false): array {
    static $cache = null;
    if (!$refresh && $cache !== null) {
        return $cache;
    }

    $defaults = getEmealsDeviceDefaults();
    $path = getEmealsDeviceConfigPath();

    if (!is_file($path)) {
        $cache = $defaults;
        return $cache;
    }

    $content = @file_get_contents($path);
    if ($content === false) {
        $cache = $defaults;
        return $cache;
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        $cache = $defaults;
        return $cache;
    }

    $names = $defaults;
    foreach ($defaults as $slot => $fallback) {
        if (isset($decoded[$slot]) && is_string($decoded[$slot])) {
            $value = trim($decoded[$slot]);
            if ($value !== '') {
                $names[$slot] = $value;
            }
        }
    }

    $cache = $names;
    return $cache;
}

function persistEmealsDeviceNames(array $names): bool {
    $defaults = getEmealsDeviceDefaults();
    $payload = [];
    foreach ($defaults as $slot => $fallback) {
        $candidate = isset($names[$slot]) ? (string)$names[$slot] : '';
        $sanitized = sanitizeEmealsDeviceName($candidate);
        $payload[$slot] = $sanitized === '' ? $fallback : $sanitized;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    $path = getEmealsDeviceConfigPath();
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return false;
    }

    if (file_put_contents($path, $json, LOCK_EX) === false) {
        return false;
    }

    readEmealsDeviceNames(true);
    return true;
}
