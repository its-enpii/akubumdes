<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
        'redis' => ['driver' => 'redis', 'connection' => env('CACHE_REDIS_CONNECTION', 'cache'), 'lock_connection' => env('CACHE_REDIS_LOCK_CONNECTION', 'default')],
        // Shared one-time SSO token bus owned by the holding portal. Coordinates are
        // 100% env driven (docs/SSO-CONTRACT.md) and both prefixes stay empty so the
        // Redis key is exactly "sso:{sha256(token)}" for every application on the bus.
        'sso' => ['driver' => 'redis', 'connection' => env('SSO_CACHE_REDIS_CONNECTION', 'sso'), 'lock_connection' => env('SSO_CACHE_LOCK_CONNECTION', 'sso'), 'prefix' => env('SSO_CACHE_PREFIX', '')],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel'), '_').'_cache_'),
];
