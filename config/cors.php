<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // PON AQUÍ TU FRONT (Live Server)
  'allowed_origins' => [
    'http://127.0.0.1:5500',
    'http://localhost:5500',
    'http://127.0.0.1:5173',
    'http://localhost:5173',
    'https://sys-comercial.netlify.app',
],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // IMPORTANTÍSIMO PARA SANCTUM SPA
    'supports_credentials' => true,
];