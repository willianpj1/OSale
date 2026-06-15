<?php

declare(strict_types=1);

// Carrega o .env antes de tudo — Phinx roda via CLI fora do bootstrap do Slim
$root   = __DIR__;
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->load();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/App/Database/Migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/App/Database/Seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinx_migrations',
        'default_environment'     => 'development',

        'development' => [
            'adapter' => 'pgsql',
            'host'    => $_ENV['DB_HOST']     ?? 'postgres',
            'name'    => $_ENV['DB_NAME']     ?? 'development_db',
            'user'    => $_ENV['DB_USER']     ?? 'senac',
            'pass'    => $_ENV['DB_PASSWORD'] ?? 'senac',
            'port'    => $_ENV['DB_PORT']     ?? '5432',
            'charset' => 'utf8',
        ],

        'testing' => [
            'adapter' => 'pgsql',
            'host'    => $_ENV['DB_HOST']     ?? 'postgres',
            'name'    => 'testing_db',
            'user'    => $_ENV['DB_USER']     ?? 'senac',
            'pass'    => $_ENV['DB_PASSWORD'] ?? 'senac',
            'port'    => $_ENV['DB_PORT']     ?? '5432',
            'charset' => 'utf8',
        ],

        'production' => [
            'adapter' => 'pgsql',
            'host'    => $_ENV['DB_HOST']     ?? 'postgres',
            'name'    => $_ENV['DB_NAME']     ?? 'production_db',
            'user'    => $_ENV['DB_USER']     ?? 'senac',
            'pass'    => $_ENV['DB_PASSWORD'] ?? 'senac',
            'port'    => $_ENV['DB_PORT']     ?? '5432',
            'charset' => 'utf8',
        ],
    ],

    'version_order' => 'creation',
];