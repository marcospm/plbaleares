<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108161427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade campos titulo_ley, capitulo, seccion y texto_legal a la tabla articulo';
    }

    public function up(Schema $schema): void
    {
        // Añadir campos a la tabla articulo
        $this->addSql('ALTER TABLE articulo ADD titulo_ley VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE articulo ADD capitulo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE articulo ADD seccion VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE articulo ADD texto_legal LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Eliminar campos de la tabla articulo
        $this->addSql('ALTER TABLE articulo DROP titulo_ley');
        $this->addSql('ALTER TABLE articulo DROP capitulo');
        $this->addSql('ALTER TABLE articulo DROP seccion');
        $this->addSql('ALTER TABLE articulo DROP texto_legal');
    }
}

