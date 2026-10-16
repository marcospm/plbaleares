<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tabla de corte de ranking (ya aplicada en producción).
 * Se mantiene el archivo para que coincida con doctrine_migration_versions
 * tras el revert del resto de la feature.
 */
final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade configuracion_ranking con fecha de corte para reiniciar rankings';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS configuracion_ranking (id INT AUTO_INCREMENT NOT NULL, fecha_corte DATETIME DEFAULT NULL, actualizado_en DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS configuracion_ranking');
    }
}
