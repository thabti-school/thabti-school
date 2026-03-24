<?php

return [
    'db' => [
        'host' => getenv('MYSQLHOST'),
        'port' => getenv('MYSQLPORT'),
        'name' => getenv('MYSQLDATABASE'),
        'user' => getenv('MYSQLUSER'),
        'pass' => getenv('MYSQLPASSWORD'),
        'charset' => 'utf8mb4',
    ],

    'admin_password' => 'Thabti@2023',

    'whatsapp' => [
        'enabled' => false,
        'access_token' => '',
        'phone_number_id' => '',
        'approved_template' => '',
        'rejected_template' => '',
        'language_code' => 'ar',
    ],
];
