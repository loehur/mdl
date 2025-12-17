<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // Additional MySQL connections for multi-database setup
        'laundry' => [
            'driver' => 'mysql',
            'url' => env('LAUNDRY_DB_URL'),
            'host' => env('LAUNDRY_DB_HOST', '127.0.0.1'),
            'port' => env('LAUNDRY_DB_PORT', '3306'),
            'database' => env('LAUNDRY_DB_DATABASE', 'mdl_laundry'),
            'username' => env('LAUNDRY_DB_USERNAME', 'root'),
            'password' => env('LAUNDRY_DB_PASSWORD', ''),
            'unix_socket' => env('LAUNDRY_DB_SOCKET', ''),
            'charset' => env('LAUNDRY_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('LAUNDRY_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('LAUNDRY_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'resto' => [
            'driver' => 'mysql',
            'url' => env('RESTO_DB_URL'),
            'host' => env('RESTO_DB_HOST', '127.0.0.1'),
            'port' => env('RESTO_DB_PORT', '3306'),
            'database' => env('RESTO_DB_DATABASE', 'mdl_resto'),
            'username' => env('RESTO_DB_USERNAME', 'root'),
            'password' => env('RESTO_DB_PASSWORD', ''),
            'unix_socket' => env('RESTO_DB_SOCKET', ''),
            'charset' => env('RESTO_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('RESTO_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('RESTO_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'depot' => [
            'driver' => 'mysql',
            'url' => env('DEPOT_DB_URL'),
            'host' => env('DEPOT_DB_HOST', '127.0.0.1'),
            'port' => env('DEPOT_DB_PORT', '3306'),
            'database' => env('DEPOT_DB_DATABASE', 'mdl_depot'),
            'username' => env('DEPOT_DB_USERNAME', 'root'),
            'password' => env('DEPOT_DB_PASSWORD', ''),
            'unix_socket' => env('DEPOT_DB_SOCKET', ''),
            'charset' => env('DEPOT_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('DEPOT_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DEPOT_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'salon' => [
            'driver' => 'mysql',
            'url' => env('SALON_DB_URL'),
            'host' => env('SALON_DB_HOST', '127.0.0.1'),
            'port' => env('SALON_DB_PORT', '3306'),
            'database' => env('SALON_DB_DATABASE', 'mdl_beauty_salon'),
            'username' => env('SALON_DB_USERNAME', 'root'),
            'password' => env('SALON_DB_PASSWORD', ''),
            'unix_socket' => env('SALON_DB_SOCKET', ''),
            'charset' => env('SALON_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('SALON_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('SALON_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'mdl_main' => [
            'driver' => 'mysql',
            'url' => env('MDL_MAIN_DB_URL'),
            'host' => env('MDL_MAIN_DB_HOST', '127.0.0.1'),
            'port' => env('MDL_MAIN_DB_PORT', '3306'),
            'database' => env('MDL_MAIN_DB_DATABASE', 'mdl_main'),
            'username' => env('MDL_MAIN_DB_USERNAME', 'root'),
            'password' => env('MDL_MAIN_DB_PASSWORD', ''),
            'unix_socket' => env('MDL_MAIN_DB_SOCKET', ''),
            'charset' => env('MDL_MAIN_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('MDL_MAIN_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MDL_MAIN_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
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
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')) . '-database-'),
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
