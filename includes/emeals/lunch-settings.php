<?php
declare(strict_types=1);

function getEmealsLunchSettingsDefaults(): array {
    return [
        'lunch_gap_hours' => 3,
    ];
}

function getEmealsLunchSettingsPath(): string {
    return __DIR__ . '/emeals-lunch-settings.json';
}

function sanitizeLunchGapHours($value): int {
    if (is_int($value)) {
        $hours = $value;
    } elseif (is_numeric($value)) {
        $hours = (int)round((float)$value);
    } else {
        $hours = 0;
    }

    if ($hours < 1) {
        $hours = 1;
    }
    if ($hours > 12) {
        $hours = 12;
    }

    return $hours;
}

function readEmealsLunchSettings(bool $refresh = false): array {
    static $cache = null;
    if (!$refresh && $cache !== null) {
        return $cache;
    }

    $defaults = getEmealsLunchSettingsDefaults();
    $path = getEmealsLunchSettingsPath();
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

    $settings = $defaults;
    if (array_key_exists('lunch_gap_hours', $decoded)) {
        $settings['lunch_gap_hours'] = sanitizeLunchGapHours($decoded['lunch_gap_hours']);
    }

    $cache = $settings;
    return $cache;
}

function persistEmealsLunchSettings(array $values): bool {
    $defaults = getEmealsLunchSettingsDefaults();
    $payload = [
        'lunch_gap_hours' => $defaults['lunch_gap_hours'],
    ];

    if (array_key_exists('lunch_gap_hours', $values)) {
        $payload['lunch_gap_hours'] = sanitizeLunchGapHours($values['lunch_gap_hours']);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    $path = getEmealsLunchSettingsPath();
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return false;
    }

    if (file_put_contents($path, $json, LOCK_EX) === false) {
        return false;
    }

    readEmealsLunchSettings(true);
    return true;
}

function getEmealsLunchGapSeconds(): int {
    $settings = readEmealsLunchSettings();
    $hours = isset($settings['lunch_gap_hours']) ? (int)$settings['lunch_gap_hours'] : 3;
    if ($hours < 1) {
        $hours = 1;
    }
    return $hours * 3600;
}
