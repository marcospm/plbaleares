<?php

use App\Kernel;
use Platformsh\ConfigReader\Config;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
 
/**
 * Upsun/Platform.sh – generate DATABASE_URL from relationships
 */
// Verificar si DATABASE_URL existe pero usa localhost (que intenta socket Unix)
$existingDbUrl = getenv('DATABASE_URL');
if ($existingDbUrl && (strpos($existingDbUrl, '@localhost') !== false || strpos($existingDbUrl, '@localhost/') !== false)) {
    // Reemplazar localhost con 127.0.0.1 para forzar TCP/IP
    $existingDbUrl = str_replace('@localhost', '@127.0.0.1', $existingDbUrl);
    putenv("DATABASE_URL=$existingDbUrl");
    $_ENV['DATABASE_URL'] = $existingDbUrl;
    $_SERVER['DATABASE_URL'] = $existingDbUrl;
}

if (!getenv('DATABASE_URL')) {
    $dsn = null;
    
    // Primero intentar usar variables de entorno directas de Upsun
    $dbHost = getenv('DATABASE_HOST');
    $dbPort = getenv('DATABASE_PORT') ?: '3306';
    $dbName = getenv('DATABASE_NAME');
    $dbUser = getenv('DATABASE_USER');
    $dbPassword = getenv('DATABASE_PASSWORD');
    
    if ($dbHost && $dbName && $dbUser) {
        // Forzar TCP/IP si el host es localhost
        if ($dbHost === 'localhost' || $dbHost === '127.0.0.1') {
            // En Upsun, si es localhost, probablemente necesitamos el host real
            // Intentar obtenerlo de otras variables o usar el hostname del servicio
            $dbHost = getenv('DATABASE_HOSTNAME') ?: $dbHost;
        }
        
        // Forzar TCP/IP añadiendo el protocolo explícitamente
        $dsn = sprintf(
            'mysql://%s:%s@%s:%s/%s?charset=utf8mb4',
            rawurlencode($dbUser),
            rawurlencode($dbPassword ?: ''),
            $dbHost,
            $dbPort,
            $dbName
        );
    } 
    // Si no hay variables directas, intentar usar PLATFORM_RELATIONSHIPS (método antiguo)
    elseif (getenv('PLATFORM_RELATIONSHIPS')) {
        try {
            $config = new Config();
            $creds = $config->credentials('database');

            $host = $creds['host'] ?? '';
            // Forzar TCP/IP: si el host es localhost, usar 127.0.0.1 o el hostname real
            if ($host === 'localhost') {
                $host = '127.0.0.1';
            }

            $dsn = sprintf(
                'mysql://%s:%s@%s:%s/%s?charset=utf8mb4',
                rawurlencode($creds['username'] ?? ''),
                rawurlencode($creds['password'] ?? ''),
                $host,
                $creds['port'] ?? '3306',
                ltrim($creds['path'] ?? '', '/')
            );
        } catch (\Exception $e) {
            // Si falla, intentar leer PLATFORM_RELATIONSHIPS directamente como JSON
            $relationships = json_decode(getenv('PLATFORM_RELATIONSHIPS'), true);
            if ($relationships && isset($relationships['database']) && is_array($relationships['database'])) {
                $db = $relationships['database'][0] ?? null;
                if ($db) {
                    $host = $db['host'] ?? '127.0.0.1';
                    if ($host === 'localhost') {
                        $host = '127.0.0.1';
                    }
                    $dsn = sprintf(
                        'mysql://%s:%s@%s:%s/%s?charset=utf8mb4',
                        rawurlencode($db['username'] ?? ''),
                        rawurlencode($db['password'] ?? ''),
                        $host,
                        $db['port'] ?? '3306',
                        ltrim($db['path'] ?? '', '/')
                    );
                }
            }
        }
    }
    
    // Si se generó un DSN, establecerlo
    if ($dsn) {
        putenv("DATABASE_URL=$dsn");
        $_ENV['DATABASE_URL'] = $dsn;
        $_SERVER['DATABASE_URL'] = $dsn;
    }
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
