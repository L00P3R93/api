<?php
return [
    'key' => env('OPENSSL_KEY'),
    'method' => env('OPENSSL_METHOD', 'AES-256-CBC'), // default fallback
    'cipher' => 'aes-256-gcm',
];
