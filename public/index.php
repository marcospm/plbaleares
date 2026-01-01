<?php

use App\Kernel;
use Platformsh\ConfigReader\Config;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

/**
 * Upsun/Platform.sh – force DATABASE_URL from relationships in platform runtime
 */
if (getenv('PLATFORM_RELATIONSHIPS')) {
    $config = new Config();
    $creds = $config->credentials('database');

    $dsn = sprintf(
        'mysql://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=mariadb-11.4',
        rawurlencode($creds['username']),
        rawurlencode($creds['password']),
        $creds['host'],
        $creds['port'],
        ltrim($creds['path'], '/')
    );

    putenv("DATABASE_URL=$dsn");
    $_ENV['DATABASE_URL'] = $dsn;
    $_SERVER['DATABASE_URL'] = $dsn;
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
