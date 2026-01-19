<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add performance indexes for plantilla queries
 */
final class Version20250126000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for plantilla queries (plantilla_id + activo, dificultad)';
    }

    public function up(Schema $schema): void
    {
        // Add composite index for pregunta queries filtering by plantilla and activo
        $this->addSql('CREATE INDEX IDX_PREGUNTA_PLANTILLA_ACTIVO ON pregunta (plantilla_id, activo)');
        
        // Add composite index for pregunta_municipal queries filtering by plantilla and activo
        $this->addSql('CREATE INDEX IDX_PREGUNTA_MUNICIPAL_PLANTILLA_ACTIVO ON pregunta_municipal (plantilla_id, activo)');
        
        // Add index for dificultad in plantilla table (for filtering queries)
        $this->addSql('CREATE INDEX IDX_PLANTILLA_DIFICULTAD ON plantilla (dificultad)');
        
        // Add composite index for plantilla queries filtering by tema and dificultad
        $this->addSql('CREATE INDEX IDX_PLANTILLA_TEMA_DIFICULTAD ON plantilla (tema_id, dificultad)');
        
        // Add index for dificultad in plantilla_municipal table
        $this->addSql('CREATE INDEX IDX_PLANTILLA_MUNICIPAL_DIFICULTAD ON plantilla_municipal (dificultad)');
        
        // Add composite index for plantilla_municipal queries filtering by tema_municipal and dificultad
        $this->addSql('CREATE INDEX IDX_PLANTILLA_MUNICIPAL_TEMA_DIFICULTAD ON plantilla_municipal (tema_municipal_id, dificultad)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PREGUNTA_PLANTILLA_ACTIVO ON pregunta');
        $this->addSql('DROP INDEX IDX_PREGUNTA_MUNICIPAL_PLANTILLA_ACTIVO ON pregunta_municipal');
        $this->addSql('DROP INDEX IDX_PLANTILLA_DIFICULTAD ON plantilla');
        $this->addSql('DROP INDEX IDX_PLANTILLA_TEMA_DIFICULTAD ON plantilla');
        $this->addSql('DROP INDEX IDX_PLANTILLA_MUNICIPAL_DIFICULTAD ON plantilla_municipal');
        $this->addSql('DROP INDEX IDX_PLANTILLA_MUNICIPAL_TEMA_DIFICULTAD ON plantilla_municipal');
    }
}
