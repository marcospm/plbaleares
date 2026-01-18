<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 * 
 * Migración para crear la tabla pregunta_riesgo
 * Esta tabla almacena las preguntas marcadas como "Me la juego" por los alumnos
 * junto con el resultado (acierto/fallo) de cada pregunta.
 */
final class Version20260118000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla pregunta_riesgo para almacenar preguntas marcadas como riesgo por los alumnos';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla pregunta_riesgo
        $this->addSql("CREATE TABLE IF NOT EXISTS pregunta_riesgo (
            id INT AUTO_INCREMENT NOT NULL,
            usuario_id INT NOT NULL,
            pregunta_id INT DEFAULT NULL,
            pregunta_municipal_id INT DEFAULT NULL,
            acertada TINYINT(1) NOT NULL,
            fecha_actualizacion DATETIME NOT NULL,
            INDEX IDX_pregunta_riesgo_usuario (usuario_id),
            INDEX IDX_pregunta_riesgo_pregunta (pregunta_id),
            INDEX IDX_pregunta_riesgo_pregunta_municipal (pregunta_municipal_id),
            INDEX idx_pregunta_riesgo_usuario_pregunta (usuario_id, pregunta_id),
            INDEX idx_pregunta_riesgo_usuario_pregunta_municipal (usuario_id, pregunta_municipal_id),
            INDEX idx_pregunta_riesgo_usuario_acertada (usuario_id, acertada),
            UNIQUE KEY unique_usuario_pregunta (usuario_id, pregunta_id),
            UNIQUE KEY unique_usuario_pregunta_municipal (usuario_id, pregunta_municipal_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        
        // Agregar foreign keys si no existen
        $this->addSql("
            SET @fk_exists = (
                SELECT COUNT(*) 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'pregunta_riesgo' 
                AND CONSTRAINT_NAME = 'FK_pregunta_riesgo_usuario'
            );
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE pregunta_riesgo 
                    ADD CONSTRAINT FK_pregunta_riesgo_usuario 
                    FOREIGN KEY (usuario_id) REFERENCES user (id)',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @fk_exists = (
                SELECT COUNT(*) 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'pregunta_riesgo' 
                AND CONSTRAINT_NAME = 'FK_pregunta_riesgo_pregunta'
            );
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE pregunta_riesgo 
                    ADD CONSTRAINT FK_pregunta_riesgo_pregunta 
                    FOREIGN KEY (pregunta_id) REFERENCES pregunta (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @fk_exists = (
                SELECT COUNT(*) 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'pregunta_riesgo' 
                AND CONSTRAINT_NAME = 'FK_pregunta_riesgo_pregunta_municipal'
            );
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE pregunta_riesgo 
                    ADD CONSTRAINT FK_pregunta_riesgo_pregunta_municipal 
                    FOREIGN KEY (pregunta_municipal_id) REFERENCES pregunta_municipal (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pregunta_riesgo DROP FOREIGN KEY FK_pregunta_riesgo_usuario');
        $this->addSql('ALTER TABLE pregunta_riesgo DROP FOREIGN KEY FK_pregunta_riesgo_pregunta');
        $this->addSql('ALTER TABLE pregunta_riesgo DROP FOREIGN KEY FK_pregunta_riesgo_pregunta_municipal');
        $this->addSql('DROP TABLE pregunta_riesgo');
    }
}
