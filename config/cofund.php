<?php

return [
    'organisations' => [
        'default_currency_code' => env('COFUND_DEFAULT_CURRENCY_CODE', 'GHS'),
        'default_timezone' => env('COFUND_DEFAULT_TIMEZONE', 'Africa/Accra'),
    ],
    'backups' => [
        'disk' => env('COFUND_BACKUP_DISK', 'local'),
        'password' => env('COFUND_BACKUP_PASSWORD'),
        'mysqldump_binary' => env('COFUND_MYSQLDUMP_BINARY', 'mysqldump'),
        'scheduled' => (bool) env('COFUND_BACKUP_SCHEDULED', false),
        'retention_days' => (int) env('COFUND_BACKUP_RETENTION_DAYS', 30),
    ],
];
