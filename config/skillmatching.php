<?php

return [
    'seed' => [
        'owner_email' => env('OWNER_EMAIL', 'admin@skillmatching.local'),
        'owner_password' => env('OWNER_PASSWORD'),
        'demo_enabled' => (bool) env('DEMO_SEED', false),
        'demo_password' => env('DEMO_PASSWORD'),
        'allow_demo_in_production' => (bool) env('ALLOW_DEMO_IN_PRODUCTION', false),
    ],
];
