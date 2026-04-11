<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:4200',              // for local dev
        'https://your-angular-app.com',       // production frontend
    ],
    'allowed_headers' => ['*'],
    'supports_credentials' => false,
];
