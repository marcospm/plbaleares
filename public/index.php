<?php

use App\Kernel;
use Platformsh\ConfigReader\Config;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

/**
 * Upsun/Platform.sh – force DATABASE_URL from relationships in platform runtime
 * Redis se configura mediante la variable de entorno REDIS_URL en cualquier entorno
 */
if (getenv('PLATFORM_RELATIONSHIPS')) {
    $config = new Config();
    $creds = $config->credentials('database');

    $dsn = sprintf(
        'mysql://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=mariadb-11.4.0',
        rawurlencode($creds['username']),
        rawurlencode($creds['password']),
        $creds['host'],
        $creds['port'],
        ltrim($creds['path'], '/')
    );

    putenv("DATABASE_URL=$dsn");
    $_ENV['DATABASE_URL'] = $dsn;
    $_SERVER['DATABASE_URL'] = $dsn;
    
    // Configurar REDIS_URL desde PLATFORM_RELATIONSHIPS si Redis está disponible
    // En Upsun, cuando defines Redis como servicio, las credenciales están en PLATFORM_RELATIONSHIPS
    // En otros entornos, REDIS_URL debe estar definida manualmente
    try {
        $redisCreds = $config->credentials('redis');
        if (!empty($redisCreds['host'])) {
            // Construir REDIS_URL según las credenciales
            if (!empty($redisCreds['password'])) {
                $redisUrl = sprintf(
                    'redis://:%s@%s:%s',
                    rawurlencode($redisCreds['password']),
                    $redisCreds['host'],
                    $redisCreds['port']
                );
            } else {
                $redisUrl = sprintf(
                    'redis://%s:%s',
                    $redisCreds['host'],
                    $redisCreds['port']
                );
            }
            putenv("REDIS_URL=$redisUrl");
            $_ENV['REDIS_URL'] = $redisUrl;
            $_SERVER['REDIS_URL'] = $redisUrl;
        }
    } catch (\Exception $e) {
        // Redis no está disponible en PLATFORM_RELATIONSHIPS
        // En Upsun: asegúrate de que Redis esté configurado en .upsun/config.yaml
        // En otros entornos: define REDIS_URL manualmente en las variables de entorno
    }
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
