<?php

/**
 * Скопируйте на сервер как api/config.php (файл не в git — загрузите вручную).
 *
 * После смены webhook в Albato обновите albato_webhook_url здесь.
 */
return [
    'albato_webhook_url' => 'https://h.albato.ru/wh/38/1lfdph4/AECrkBkmbrVhEpLQAa7Ijui9Rz76ZRcCHKYvKurb18o/',

    // Укажите домен лендинга. Пустой массив = принимать с любого домена.
    'allowed_hosts' => [],

    'rate_limit_hour' => 5,
    'rate_limit_day' => 20,
];
