<?php

/**
 * Скопируйте на сервер как api/config.php (файл не в git — загрузите вручную).
 *
 * После смены webhook в Albato обновите albato_webhook_url здесь.
 */
return [
    'albato_webhook_url' => 'https://h.albato.ru/wh/38/1lfdph4/AECrkBkmbrVhEpLQAa7Ijui9Rz76ZRcCHKYvKurb18o/',

    // Укажите домен лендинга — заявки с других сайтов будут отклонены (тихо).
    // Пример: 'stoma128.ru', 'www.stoma128.ru'
    'allowed_hosts' => [],

    // Не более N заявок с одного IP за час / сутки
    'rate_limit_hour' => 5,
    'rate_limit_day' => 20,

    // Антибот: минимум времени на странице и на шаге с телефоном (мс)
    'min_page_ms' => 8000,
    'min_form_ms' => 2000,
    'max_form_age_ms' => 86400000,
];
