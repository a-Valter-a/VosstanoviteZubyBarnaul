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

require_once __DIR__ . '/lib/AmoCRMClient.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректные данные'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $config = require $configPath;
    $client = new AmoCRMClient($config, __DIR__ . '/tokens.json');
    $result = $client->createLead([
        'name' => (string) ($data['name'] ?? ''),
        'phone' => (string) ($data['phone'] ?? ''),
        'teeth' => (string) ($data['teeth'] ?? ''),
        'city' => (string) ($data['city'] ?? ''),
        'page_url' => (string) ($data['page_url'] ?? ''),
        'utm' => (string) ($data['utm'] ?? ''),
    ]);

    echo json_encode([
        'ok' => true,
        'lead_id' => $result['lead_id'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Не удалось отправить заявку'], JSON_UNESCAPED_UNICODE);
}
