<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Idempotente: crea configuracion_ranking solo si no existe.
 * Evita fallos en entornos donde la tabla ya se creó (manual o migración previa).
 */
final class Version20261016100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Asegura la tabla configuracion_ranking (CREATE IF NOT EXISTS)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS configuracion_ranking (
                id INT AUTO_INCREMENT NOT NULL,
                fecha_corte DATETIME DEFAULT NULL,
                actualizado_en DATETIME DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        // No eliminamos la tabla: puede estar en uso o haberse creado por otra vía.
    }
}
