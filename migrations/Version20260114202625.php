<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración para crear la tabla partida_juego
 * 
 * Esta migración crea la tabla para guardar las partidas de los juegos de gamificación
 */
final class Version20260114202625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla partida_juego para registrar partidas de juegos de gamificación';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla si no existe
        $this->addSql("CREATE TABLE IF NOT EXISTS partida_juego (
            id INT AUTO_INCREMENT NOT NULL,
            usuario_id INT NOT NULL,
            tipo_juego VARCHAR(50) NOT NULL,
            fecha_creacion DATETIME NOT NULL,
            INDEX IDX_PARTIDA_JUEGO_USUARIO_TIPO (usuario_id, tipo_juego),
            INDEX IDX_PARTIDA_JUEGO_TIPO (tipo_juego),
            INDEX IDX_PARTIDA_JUEGO_FECHA (fecha_creacion),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // Agregar índice IDX_PARTIDA_JUEGO_USUARIO si no existe
        $this->addSql("
            SET @index_exists = (
                SELECT COUNT(*) 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'partida_juego' 
                AND INDEX_NAME = 'IDX_PARTIDA_JUEGO_USUARIO'
            );
            
            SET @sql = IF(@index_exists = 0,
                'ALTER TABLE partida_juego ADD INDEX IDX_PARTIDA_JUEGO_USUARIO (usuario_id)',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");

        // Agregar foreign key si no existe
        $this->addSql("
            SET @fk_exists = (
                SELECT COUNT(*) 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'partida_juego' 
                AND CONSTRAINT_NAME = 'FK_PARTIDA_JUEGO_USUARIO'
            );
            
            SET @sql = IF(@fk_exists = 0,
                'ALTER TABLE partida_juego 
                    ADD CONSTRAINT FK_PARTIDA_JUEGO_USUARIO 
                    FOREIGN KEY (usuario_id) REFERENCES user (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // Eliminar foreign key
        $this->addSql('ALTER TABLE partida_juego DROP FOREIGN KEY FK_PARTIDA_JUEGO_USUARIO');
        
        // Eliminar tabla
        $this->addSql('DROP TABLE partida_juego');
    }
}
