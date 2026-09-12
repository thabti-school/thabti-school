<?php

return [
    'database_url' => getenv('DATABASE_URL') ?: '',

    'admin_password' => getenv('ADMIN_PASSWORD') ?: '',

    'sms' => [
        'enabled' => filter_var(
            getenv('SMS_ENABLED') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        ),
        'api_url' => getenv('SMS_API_URL') ?: '',
        'api_key' => getenv('SMS_API_KEY') ?: '',
        'sender_id' => getenv('SMS_SENDER_ID') ?: '',
    ],
];
