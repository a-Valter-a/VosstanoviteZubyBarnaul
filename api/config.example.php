<?php

/**
 * Можно загрузить как api/config.php или оставить как config.example.php —
 * send-lead.php подхватит этот файл, если config.php нет.
 */
return [
    'albato_webhook_url' => 'https://h.albato.ru/wh/38/1lfdph4/AECrkBkmbrVhEpLQAa7Ijui9Rz76ZRcCHKYvKurb18o/',

    // Домен лендинга. Пустой массив = без ограничения.
    'allowed_hosts' => [
        'stomprotez.pw',
        'www.stomprotez.pw',
    ],

    'rate_limit_hour' => 5,
    'rate_limit_day' => 20,
];
