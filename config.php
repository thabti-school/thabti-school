<?php

return [
    // رابط PostgreSQL يُضاف في Render / Railway كمتغير بيئة
    'database_url' => getenv('DATABASE_URL') ?: '',

    // كلمة مرور الإدارة لا تُكتب داخل GitHub
    'admin_password' => getenv('ADMIN_PASSWORD') ?: '',

    'whatsapp' => [
        'enabled' => false,
        'access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
        'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
        'approved_template' => '',
        'rejected_template' => '',
        'language_code' => 'ar',
    ],
];
