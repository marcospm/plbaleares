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
        $connection = $this->connection;
        
        // Check if plantilla_id column exists in pregunta table before creating index
        $checkPreguntaPlantilla = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'pregunta' 
             AND COLUMN_NAME = 'plantilla_id'"
        );
        
        if ($checkPreguntaPlantilla > 0) {
            // Check if index already exists
            $indexExists = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'pregunta' 
                 AND INDEX_NAME = 'IDX_PREGUNTA_PLANTILLA_ACTIVO'"
            );
            
            if ($indexExists == 0) {
                $this->addSql('CREATE INDEX IDX_PREGUNTA_PLANTILLA_ACTIVO ON pregunta (plantilla_id, activo)');
            }
        }
        
        // Check if plantilla_id column exists in pregunta_municipal table before creating index
        $checkPreguntaMunicipalPlantilla = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'pregunta_municipal' 
             AND COLUMN_NAME = 'plantilla_id'"
        );
        
        if ($checkPreguntaMunicipalPlantilla > 0) {
            // Check if index already exists
            $indexExists = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'pregunta_municipal' 
                 AND INDEX_NAME = 'IDX_PREGUNTA_MUNICIPAL_PLANTILLA_ACTIVO'"
            );
            
            if ($indexExists == 0) {
                $this->addSql('CREATE INDEX IDX_PREGUNTA_MUNICIPAL_PLANTILLA_ACTIVO ON pregunta_municipal (plantilla_id, activo)');
            }
        }
        
        // Check if plantilla table exists before creating indexes
        $checkPlantillaTable = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'plantilla'"
        );
        
        if ($checkPlantillaTable > 0) {
            // Check if index already exists
            $indexExists = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'plantilla' 
                 AND INDEX_NAME = 'IDX_PLANTILLA_DIFICULTAD'"
            );
            
            if ($indexExists == 0) {
                $this->addSql('CREATE INDEX IDX_PLANTILLA_DIFICULTAD ON plantilla (dificultad)');
            }
            
            // Check if composite index already exists
            $indexExists = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'plantilla' 
                 AND INDEX_NAME = 'IDX_PLANTILLA_TEMA_DIFICULTAD'"
            );
            
            if ($indexExists == 0) {
                $this->addSql('CREATE INDEX IDX_PLANTILLA_TEMA_DIFICULTAD ON plantilla (tema_id, dificultad)');
            }
        }
        
        // Check if plantilla_municipal table exists before creating indexes
        $checkPlantillaMunicipalTable = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'plantilla_municipal'"
        );
        
        if ($checkPlantillaMunicipalTable > 0) {
            // Check if index already exists
            $indexExists = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'plantilla_municipal' 
                 AND INDEX_NAME = 'IDX_PLANTILLA_MUNICIPAL_DIFICULTAD'"
            );
            
            if ($indexExists == 0) {
                $this->addSql('CREATE INDEX IDX_PLANTILLA_MUNICIPAL_DIFICULTAD ON plantilla_municipal (dificultad)');
            }
            
            // Check if composite index already exists
            $indexExists = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'plantilla_municipal' 
                 AND INDEX_NAME = 'IDX_PLANTILLA_MUNICIPAL_TEMA_DIFICULTAD'"
            );
            
            if ($indexExists == 0) {
                $this->addSql('CREATE INDEX IDX_PLANTILLA_MUNICIPAL_TEMA_DIFICULTAD ON plantilla_municipal (tema_municipal_id, dificultad)');
            }
        }
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
