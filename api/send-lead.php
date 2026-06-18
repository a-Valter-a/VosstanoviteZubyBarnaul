<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Сервер не настроен: создайте api/config.php из api/config.example.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректные данные'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string) ($data['name'] ?? ''));
$phone = trim((string) ($data['phone'] ?? ''));

if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Укажите имя и телефон'], JSON_UNESCAPED_UNICODE);
    exit;
}

$phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
if (strlen($phoneDigits) < 11) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Некорректный телефон'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $config = require $configPath;
    $webhookUrl = trim((string) ($config['albato_webhook_url'] ?? ''));

    if ($webhookUrl === '') {
        throw new RuntimeException('Не указан albato_webhook_url в config.php');
    }

    $payload = [
        'name' => $name,
        'phone' => $phone,
        'utm' => trim((string) ($data['utm'] ?? '')),
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

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Не удалось отправить заявку'], JSON_UNESCAPED_UNICODE);
}
