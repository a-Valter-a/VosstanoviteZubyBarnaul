<?php

declare(strict_types=1);

final class AmoCRMClient
{
    private array $config;
    private string $tokensPath;

    public function __construct(array $config, string $tokensPath)
    {
        $this->config = $config;
        $this->tokensPath = $tokensPath;
    }

    public function createLead(array $payload): array
    {
        $name = trim($payload['name'] ?? '');
        $phone = trim($payload['phone'] ?? '');
        $teeth = trim($payload['teeth'] ?? '');
        $city = trim($payload['city'] ?? '');

        if ($name === '' || $phone === '') {
            throw new InvalidArgumentException('Укажите имя и телефон');
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phoneDigits) < 11) {
            throw new InvalidArgumentException('Некорректный телефон');
        }

        $leadName = trim(($this->config['lead_name_prefix'] ?? 'Заявка с сайта') . ' — ' . $name);
        $noteLines = array_filter([
            'Имя: ' . $name,
            'Телефон: ' . $phone,
            $teeth !== '' ? 'Зубы: ' . $teeth : null,
            $city !== '' ? 'Город: ' . $city : null,
            'Страница: ' . ($payload['page_url'] ?? ''),
            'UTM: ' . ($payload['utm'] ?? ''),
        ]);

        $lead = [
            'name' => $leadName,
            '_embedded' => [
                'contacts' => [[
                    'name' => $name,
                    'custom_fields_values' => [[
                        'field_code' => 'PHONE',
                        'values' => [[
                            'value' => $phone,
                            'enum_code' => 'WORK',
                        ]],
                    ]],
                ]],
            ],
        ];

        if (!empty($this->config['pipeline_id'])) {
            $lead['pipeline_id'] = (int) $this->config['pipeline_id'];
        }

        if (!empty($this->config['status_id'])) {
            $lead['status_id'] = (int) $this->config['status_id'];
        }

        if (!empty($this->config['tag'])) {
            $lead['_embedded']['tags'] = [['name' => (string) $this->config['tag']]];
        }

        $response = $this->request('POST', '/api/v4/leads/complex', [$lead]);
        $created = $response[0] ?? null;

        if (!is_array($created) || empty($created['id'])) {
            throw new RuntimeException('Не удалось создать сделку в amoCRM');
        }

        $leadId = (int) $created['id'];

        try {
            $this->addNote($leadId, implode("\n", $noteLines));
        } catch (Throwable $e) {
            // Сделка уже создана — не отменяем успешную отправку из‑за примечания.
        }

        return [
            'lead_id' => $leadId,
        ];
    }

    private function addNote(int $leadId, string $text): void
    {
        if ($text === '') {
            return;
        }

        $this->request('POST', '/api/v4/leads/notes', [[
            'entity_id' => $leadId,
            'note_type' => 'common',
            'params' => [
                'text' => $text,
            ],
        ]]);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $token = $this->getAccessToken();
        $subdomain = trim($this->config['subdomain'] ?? '');

        if ($subdomain === '') {
            throw new RuntimeException('Не указан subdomain amoCRM');
        }

        $url = 'https://' . $subdomain . '.amocrm.ru' . $path;
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Ошибка соединения с amoCRM: ' . $error);
        }

        $decoded = json_decode($raw, true);

        if ($status === 401 && !empty($this->config['refresh_token'])) {
            $this->refreshAccessToken();
            return $this->request($method, $path, $body);
        }

        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded)
                ? ($decoded['title'] ?? $decoded['detail'] ?? $raw)
                : $raw;
            throw new RuntimeException('amoCRM HTTP ' . $status . ': ' . $message);
        }

        if ($decoded === null && $raw !== '' && $raw !== 'null') {
            throw new RuntimeException('Некорректный ответ amoCRM');
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function getAccessToken(): string
    {
        if (is_file($this->tokensPath)) {
            $stored = json_decode((string) file_get_contents($this->tokensPath), true);
            if (is_array($stored) && !empty($stored['access_token'])) {
                return (string) $stored['access_token'];
            }
        }

        $token = trim($this->config['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('amoCRM не настроен: укажите access_token в api/config.php');
        }

        return $token;
    }

    private function refreshAccessToken(): void
    {
        $clientId = trim($this->config['client_id'] ?? '');
        $clientSecret = trim($this->config['client_secret'] ?? '');
        $refreshToken = trim($this->config['refresh_token'] ?? '');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException('Истёк access_token, настройте refresh_token в config.php');
        }

        $subdomain = trim($this->config['subdomain'] ?? '');
        $redirectUri = trim($this->config['redirect_uri'] ?? '');
        $url = 'https://' . $subdomain . '.amocrm.ru/oauth2/access_token';

        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || empty($decoded['access_token'])) {
            throw new RuntimeException('Не удалось обновить access_token amoCRM');
        }

        file_put_contents($this->tokensPath, json_encode([
            'access_token' => $decoded['access_token'],
            'refresh_token' => $decoded['refresh_token'] ?? $refreshToken,
            'expires_in' => $decoded['expires_in'] ?? null,
            'updated_at' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->config['access_token'] = $decoded['access_token'];
        if (!empty($decoded['refresh_token'])) {
            $this->config['refresh_token'] = $decoded['refresh_token'];
        }
    }
}
