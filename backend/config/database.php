<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    'default' => env('DB_CONNECTION', 'sqlite'),

'connections' => [

    // Conexión 1: Por defecto (Mapea las variables clásicas del .env)
    'sqlsrv' => [
        'driver' => 'sqlsrv',
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '1433'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
    ],

    // Conexión 2: Asignaciones
    'asignaciones' => [
        'driver' => 'sqlsrv',
        'host' => env('ASIG_DB_HOST', '192.168.1.247'),
        'port' => env('ASIG_DB_PORT', '1433'),
        'database' => env('ASIG_DB_DATABASE', ''),
        'username' => env('ASIG_DB_USERNAME', ''),
        'password' => env('ASIG_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
    ],

    // Conexión 3: Actualizaciones
    'actualizacion' => [
        'driver' => 'sqlsrv',
        'host' => env('ACT_DB_HOST', '192.168.1.247'),
        'port' => env('ACT_DB_PORT', '1433'),
        'database' => env('ACT_DB_DATABASE', ''),
        'username' => env('ACT_DB_USERNAME', ''),
        'password' => env('ACT_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
    ],

    // Conexión 4: Metas
    'metas' => [
        'driver' => 'sqlsrv',
        'host' => env('MET_DB_HOST', '192.168.1.247'),
        'port' => env('MET_DB_PORT', '1433'),
        'database' => env('MET_DB_DATABASE', ''),
        'username' => env('MET_DB_USERNAME', ''),
        'password' => env('MET_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
    ],

],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
