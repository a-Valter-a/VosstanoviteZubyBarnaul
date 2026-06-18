<?php

/**
 * Скопируйте в config.php и заполните данными интеграции amoCRM.
 *
 * 1. amoCRM → Настройки → Интеграции → Создать интеграцию (внешняя).
 * 2. Redirect URI: https://ваш-домен.ru/api/oauth-callback.php (если используете OAuth).
 * 3. Скопируйте ID интеграции, секретный ключ, поддомен аккаунта.
 * 4. Получите access_token (долгоживущий или через OAuth) и укажите ниже.
 *
 * pipeline_id / status_id — необязательны; если не указаны, сделка создаётся в воронке по умолчанию.
 */
return [
    'subdomain' => 'your-subdomain',
    'access_token' => '',
    'refresh_token' => '',
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => 'https://your-domain.ru/api/oauth-callback.php',
    'pipeline_id' => null,
    'status_id' => null,
    'lead_name_prefix' => 'Лендинг — Восстановите зубы',
    'tag' => 'Лендинг Барнаул',
];
