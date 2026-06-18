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

function respond($ok, $code = 200, $error = null)
{
    http_response_code($code);
    $body = ['ok' => (bool) $ok];
    if ($error !== null) {
        $body['error'] = $error;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function respondSilentReject()
{
    respond(true);
}

function textLen($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }

    return strlen($value);
}

function textCut($value, $max)
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }

    return substr($value, 0, $max);
}

function clientIp()
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

function checkRateLimit($ip, $maxPerHour, $maxPerDay)
{
    $dir = __DIR__ . '/data/rate-limit';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
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
                function ($ts) use ($now) {
                    return is_int($ts) && $ts > $now - 86400;
                }
            ));
        }
    }

    $lastHour = array_filter($entries, function ($ts) use ($now) {
        return $ts > $now - 3600;
    });

    if (count($lastHour) >= $maxPerHour || count($entries) >= $maxPerDay) {
        return false;
    }

    $entries[] = $now;
    @file_put_contents($file, json_encode($entries), LOCK_EX);

    return true;
}

function isAllowedOrigin(array $config)
{
    $hosts = $config['allowed_hosts'] ?? [];
    if (!is_array($hosts) || count($hosts) === 0) {
        return true;
    }

    $allowed = array_map(function ($host) {
        return strtolower(trim((string) $host));
    }, $hosts);

    $requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($requestHost !== '') {
        $requestHost = explode(':', $requestHost)[0];
        if (in_array($requestHost, $allowed, true)) {
            return true;
        }
    }

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

function isValidName($name)
{
    if (textLen($name) < 2 || textLen($name) > 80) {
        return false;
    }

    return (bool) preg_match("/^[\p{L}\s\-'.]+$/u", $name);
}

function isValidPhone($phone)
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    return strlen($digits) === 11 && $digits[0] === '7';
}

function sendToAlbato($webhookUrl, array $payload)
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('curl: ' . $error);
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Albato HTTP ' . $status);
        }

        return true;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($webhookUrl, false, $context);
    if ($response === false) {
        throw new RuntimeException('stream request failed');
    }

    if (isset($http_response_header[0]) && !preg_match('/\s200\s/', $http_response_header[0])) {
        throw new RuntimeException('Albato bad status');
    }

    return true;
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/config.example.php';
}

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

if (!isAllowedOrigin($config)) {
    respond(false, 403, 'Не удалось отправить заявку');
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

if (textLen($utm) > 500) {
    $utm = textCut($utm, 500);
}

try {
    $webhookUrl = trim((string) ($config['albato_webhook_url'] ?? ''));

    if ($webhookUrl === '') {
        throw new RuntimeException('Не указан albato_webhook_url');
    }

    sendToAlbato($webhookUrl, [
        'name' => $name,
        'phone' => $phone,
        'utm' => $utm,
    ]);

    respond(true);
} catch (Throwable $e) {
    respond(false, 500, 'Не удалось отправить заявку');
}
