<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$connection = $entityManager->getConnection();

// Buscar ley de Constitución
$ley = $connection->fetchAssociative(
    "SELECT id, nombre FROM ley WHERE nombre LIKE '%Constitución%' OR nombre LIKE '%Constitucion%' LIMIT 1"
);

if (!$ley) {
    echo "No se encontró la ley de Constitución\n";
    exit(1);
}

echo "LEY_ID: " . $ley['id'] . " (" . $ley['nombre'] . ")\n\n";

// Buscar artículos 4-9
$articulos = $connection->fetchAllAssociative(
    "SELECT a.id, a.numero, a.ley_id 
     FROM articulo a 
     WHERE a.numero IN ('4', '5', '6', '7', '8', '9') 
     AND a.ley_id = ? 
     ORDER BY CAST(a.numero AS UNSIGNED)",
    [$ley['id']]
);

echo "ARTÍCULOS:\n";
foreach ($articulos as $art) {
    echo "ARTICULO_" . $art['numero'] . "_ID: " . $art['id'] . "\n";
}

// Buscar tema relacionado
$tema = $connection->fetchAssociative(
    "SELECT t.id, t.nombre 
     FROM tema t 
     INNER JOIN tema_ley tl ON t.id = tl.tema_id 
     WHERE tl.ley_id = ? 
     LIMIT 1",
    [$ley['id']]
);

if ($tema) {
    echo "\nTEMA_ID: " . $tema['id'] . " (" . $tema['nombre'] . ")\n";
} else {
    echo "\nTEMA_ID: (no encontrado, usar NULL o crear uno)\n";
}









