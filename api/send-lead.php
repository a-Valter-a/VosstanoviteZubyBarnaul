<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond(bool $ok, int $code = 200, ?string $error = null): never
{
    http_response_code($code);
    $body = ['ok' => $ok];
    if ($error !== null) {
        $body['error'] = $error;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Тихий отказ — бот думает, что заявка принята */
function respondSilentReject(): never
{
    respond(true);
}

function clientIp(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($header === 'HTTP_X_FORWARDED_FOR') {
            $value = trim(explode(',', $value)[0]);
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return '0.0.0.0';
}

function checkRateLimit(string $ip, int $maxPerHour, int $maxPerDay): bool
{
    $dir = __DIR__ . '/data/rate-limit';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return true;
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $entries = [];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $entries = array_values(array_filter(
                $decoded,
                static fn ($ts) => is_int($ts) && $ts > $now - 86400
            ));
        }
    }

    $lastHour = array_filter($entries, static fn ($ts) => $ts > $now - 3600);
    if (count($lastHour) >= $maxPerHour || count($entries) >= $maxPerDay) {
        return false;
    }

    $entries[] = $now;
    file_put_contents($file, json_encode($entries), LOCK_EX);

    return true;
}

function isAllowedOrigin(array $config): bool
{
    $hosts = $config['allowed_hosts'] ?? [];
    if (!is_array($hosts) || $hosts === []) {
        return true;
    }

    $allowed = array_map(
        static fn ($host) => strtolower(trim((string) $host)),
        $hosts
    );

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value === '') {
            continue;
        }
        $host = parse_url($value, PHP_URL_HOST);
        if (is_string($host) && in_array(strtolower($host), $allowed, true)) {
            return true;
        }
    }

    return false;
}

function isValidTiming(array $data, int $minPageMs, int $minFormMs, int $maxAgeMs): bool
{
    $now = (int) round(microtime(true) * 1000);
    $loaded = (int) ($data['_loaded'] ?? 0);
    $formAt = (int) ($data['_ts'] ?? 0);

    if ($loaded <= 0 || $formAt <= 0) {
        return false;
    }

    if ($loaded > $now + 60000 || $formAt > $now + 60000) {
        return false;
    }

    if ($now - $loaded < $minPageMs || $now - $loaded > $maxAgeMs) {
        return false;
    }

    if ($now - $formAt < $minFormMs) {
        return false;
    }

    return true;
}

function isValidName(string $name): bool
{
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        return false;
    }

    return (bool) preg_match("/^[\p{L}\s\-'.]+$/u", $name);
}

function isValidPhone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        return false;
    }

    return (bool) preg_match('/^7[3489]\d{9}$/', $digits);
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    respond(false, 503, 'Сервер не настроен: создайте api/config.php из api/config.example.php');
}

$config = require $configPath;

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    respond(false, 400, 'Некорректные данные');
}

if (trim((string) ($data['_hp'] ?? '')) !== '') {
    respondSilentReject();
}

$minPageMs = (int) ($config['min_page_ms'] ?? 8000);
$minFormMs = (int) ($config['min_form_ms'] ?? 2000);
$maxAgeMs = (int) ($config['max_form_age_ms'] ?? 86400000);

if (!isValidTiming($data, $minPageMs, $minFormMs, $maxAgeMs)) {
    respondSilentReject();
}

if (!isAllowedOrigin($config)) {
    respondSilentReject();
}

$maxPerHour = (int) ($config['rate_limit_hour'] ?? 5);
$maxPerDay = (int) ($config['rate_limit_day'] ?? 20);

if (!checkRateLimit(clientIp(), $maxPerHour, $maxPerDay)) {
    respond(false, 429, 'Слишком много заявок. Попробуйте позже.');
}

$name = trim((string) ($data['name'] ?? ''));
$phone = trim((string) ($data['phone'] ?? ''));
$utm = trim((string) ($data['utm'] ?? ''));

if ($name === '' || $phone === '') {
    respond(false, 422, 'Укажите имя и телефон');
}

if (!isValidName($name)) {
    respond(false, 422, 'Укажите корректное имя');
}

if (!isValidPhone($phone)) {
    respond(false, 422, 'Некорректный телефон');
}

if (mb_strlen($utm) > 500) {
    $utm = mb_substr($utm, 0, 500);
}

try {
    $webhookUrl = trim((string) ($config['albato_webhook_url'] ?? ''));

    if ($webhookUrl === '') {
        throw new RuntimeException('Не указан albato_webhook_url');
    }

    $payload = [
        'name' => $name,
        'phone' => $phone,
        'utm' => $utm,
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Ошибка соединения: ' . $error);
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Albato HTTP ' . $status);
    }

    respond(true);
} catch (Throwable $e) {
    respond(false, 500, 'Не удалось отправить заявку');
}
