<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // PRODUCCIÓN
        'https://app.brandingcol.com',
        'https://api.brandingcol.com',

        // DESARROLLO LOCAL
        'http://127.0.0.1:5500',
        'http://localhost:5500',
        'http://127.0.0.1:5173',
        'http://localhost:5173',

        // si aún usas netlify en pruebas
        'https://sys-comercial.netlify.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // NECESARIO para Sanctum + cookies
    'supports_credentials' => true,

];