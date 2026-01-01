<?php

use App\Kernel;
use Platformsh\ConfigReader\Config;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
 
/**
 * Upsun/Platform.sh – generate DATABASE_URL from relationships
 */
if (!getenv('DATABASE_URL')) {
    // Primero intentar usar variables de entorno directas de Upsun
    $dbHost = getenv('DATABASE_HOST');
    $dbPort = getenv('DATABASE_PORT') ?: '3306';
    $dbName = getenv('DATABASE_NAME');
    $dbUser = getenv('DATABASE_USER');
    $dbPassword = getenv('DATABASE_PASSWORD');
    
    if ($dbHost && $dbName && $dbUser) {
        $dsn = sprintf(
            'mysql://%s:%s@%s:%s/%s?charset=utf8mb4',
            rawurlencode($dbUser),
            rawurlencode($dbPassword ?: ''),
            $dbHost,
            $dbPort,
            $dbName
        );

        putenv("DATABASE_URL=$dsn");
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    } 
    // Si no hay variables directas, intentar usar PLATFORM_RELATIONSHIPS (método antiguo)
    elseif (getenv('PLATFORM_RELATIONSHIPS')) {
        try {
            $config = new Config();
            $creds = $config->credentials('database');

            $dsn = sprintf(
                'mysql://%s:%s@%s:%s/%s?charset=utf8mb4',
                rawurlencode($creds['username']),
                rawurlencode($creds['password']),
                $creds['host'],
                $creds['port'],
                ltrim($creds['path'], '/')
            );

            putenv("DATABASE_URL=$dsn");
            $_ENV['DATABASE_URL'] = $dsn;
            $_SERVER['DATABASE_URL'] = $dsn;
        } catch (\Exception $e) {
            // Si falla, dejar que Symfony use DATABASE_URL directamente si está definida
        }
    }
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
