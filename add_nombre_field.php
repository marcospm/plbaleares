<?php

// Script para añadir la columna nombre a la tabla user
// Ejecutar: php add_nombre_field.php

use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new \App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

$connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');

try {
    // Verificar si la columna ya existe
    $columns = $connection->fetchAllAssociative("SHOW COLUMNS FROM user LIKE 'nombre'");
    if (count($columns) > 0) {
        echo "✓ La columna 'nombre' ya existe en la tabla 'user'.\n";
        exit(0);
    }

    // Añadir la columna
    $connection->executeStatement("ALTER TABLE user ADD nombre VARCHAR(255) DEFAULT NULL");
    
    echo "✓ Columna 'nombre' añadida correctamente a la tabla 'user'.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
