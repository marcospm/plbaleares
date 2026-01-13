<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración para convertir relaciones ManyToOne a ManyToMany en la tabla sesion
 * 
 * Esta migración:
 * 1. Crea las tablas ManyToMany si no existen
 * 2. Migra los datos de tema_id y tema_municipal_id a las nuevas tablas
 * 3. Elimina las columnas antiguas si existen
 * 
 * Es segura de ejecutar incluso si la migración original ya creó las tablas ManyToMany
 */
final class Version20260113133311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertir relaciones ManyToOne a ManyToMany en sesion: migrar datos y actualizar estructura';
    }

    public function up(Schema $schema): void
    {
        // Crear tablas ManyToMany si no existen
        $this->addSql('CREATE TABLE IF NOT EXISTS sesion_tema (
            sesion_id INT NOT NULL,
            tema_id INT NOT NULL,
            INDEX IDX_SESION_TEMA_SESION (sesion_id),
            INDEX IDX_SESION_TEMA_TEMA (tema_id),
            PRIMARY KEY(sesion_id, tema_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('CREATE TABLE IF NOT EXISTS sesion_tema_municipal (
            sesion_id INT NOT NULL,
            tema_municipal_id INT NOT NULL,
            INDEX IDX_SESION_TEMA_MUNICIPAL_SESION (sesion_id),
            INDEX IDX_SESION_TEMA_MUNICIPAL_TEMA (tema_municipal_id),
            PRIMARY KEY(sesion_id, tema_municipal_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Añadir foreign keys (usando procedimiento almacenado temporal para evitar errores si ya existen)
        $this->addSql("
            SET @sql = IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'sesion_tema' 
                 AND CONSTRAINT_NAME = 'FK_SESION_TEMA_SESION') = 0,
                'ALTER TABLE sesion_tema ADD CONSTRAINT FK_SESION_TEMA_SESION FOREIGN KEY (sesion_id) REFERENCES sesion (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'sesion_tema' 
                 AND CONSTRAINT_NAME = 'FK_SESION_TEMA_TEMA') = 0,
                'ALTER TABLE sesion_tema ADD CONSTRAINT FK_SESION_TEMA_TEMA FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'sesion_tema_municipal' 
                 AND CONSTRAINT_NAME = 'FK_SESION_TEMA_MUNICIPAL_SESION') = 0,
                'ALTER TABLE sesion_tema_municipal ADD CONSTRAINT FK_SESION_TEMA_MUNICIPAL_SESION FOREIGN KEY (sesion_id) REFERENCES sesion (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'sesion_tema_municipal' 
                 AND CONSTRAINT_NAME = 'FK_SESION_TEMA_MUNICIPAL_TEMA') = 0,
                'ALTER TABLE sesion_tema_municipal ADD CONSTRAINT FK_SESION_TEMA_MUNICIPAL_TEMA FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE CASCADE',
                'SELECT 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Migrar datos: Si existe la columna tema_id, migrar los datos
        // Usar un procedimiento almacenado para verificar primero si la columna existe
        $this->addSql("
            SET @col_exists = (
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_id'
            );
            SET @sql = IF(@col_exists > 0, 
                'INSERT IGNORE INTO sesion_tema (sesion_id, tema_id)
                 SELECT s.id, s.tema_id
                 FROM sesion s
                 WHERE s.tema_id IS NOT NULL', 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @col_exists = (
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_municipal_id'
            );
            SET @sql = IF(@col_exists > 0, 
                'INSERT IGNORE INTO sesion_tema_municipal (sesion_id, tema_municipal_id)
                 SELECT s.id, s.tema_municipal_id
                 FROM sesion s
                 WHERE s.tema_municipal_id IS NOT NULL', 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Eliminar foreign keys antiguas si existen
        $this->addSql("
            SET @fk_name = (
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL 
                LIMIT 1
            );
            SET @sql = IF(@fk_name IS NOT NULL, 
                CONCAT('ALTER TABLE sesion DROP FOREIGN KEY ', @fk_name), 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @fk_name = (
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_municipal_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL 
                LIMIT 1
            );
            SET @sql = IF(@fk_name IS NOT NULL, 
                CONCAT('ALTER TABLE sesion DROP FOREIGN KEY ', @fk_name), 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Eliminar índices antiguos si existen
        $this->addSql("
            SET @idx_name = (
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_id' 
                AND INDEX_NAME != 'PRIMARY'
                LIMIT 1
            );
            SET @sql = IF(@idx_name IS NOT NULL, 
                CONCAT('ALTER TABLE sesion DROP INDEX ', @idx_name), 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @idx_name = (
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_municipal_id' 
                AND INDEX_NAME != 'PRIMARY'
                LIMIT 1
            );
            SET @sql = IF(@idx_name IS NOT NULL, 
                CONCAT('ALTER TABLE sesion DROP INDEX ', @idx_name), 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Eliminar columnas antiguas si existen
        $this->addSql("
            SET @col_exists = (
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_id'
            );
            SET @sql = IF(@col_exists > 0, 
                'ALTER TABLE sesion DROP COLUMN tema_id', 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @col_exists = (
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'sesion' 
                AND COLUMN_NAME = 'tema_municipal_id'
            );
            SET @sql = IF(@col_exists > 0, 
                'ALTER TABLE sesion DROP COLUMN tema_municipal_id', 
                'SELECT 1');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // Restaurar columnas antiguas
        $this->addSql('ALTER TABLE sesion 
            ADD COLUMN tema_id INT DEFAULT NULL,
            ADD COLUMN tema_municipal_id INT DEFAULT NULL');
        
        $this->addSql('CREATE INDEX IDX_SESION_TEMA ON sesion (tema_id)');
        $this->addSql('CREATE INDEX IDX_SESION_TEMA_MUNICIPAL ON sesion (tema_municipal_id)');
        
        // Migrar datos de vuelta (solo el primer tema de cada tipo)
        $this->addSql('UPDATE sesion s 
            INNER JOIN (
                SELECT sesion_id, MIN(tema_id) as tema_id 
                FROM sesion_tema 
                GROUP BY sesion_id
            ) st ON s.id = st.sesion_id
            SET s.tema_id = st.tema_id');
        
        $this->addSql('UPDATE sesion s 
            INNER JOIN (
                SELECT sesion_id, MIN(tema_municipal_id) as tema_municipal_id 
                FROM sesion_tema_municipal 
                GROUP BY sesion_id
            ) stm ON s.id = stm.sesion_id
            SET s.tema_municipal_id = stm.tema_municipal_id');
        
        // Añadir foreign keys
        $this->addSql('ALTER TABLE sesion 
            ADD CONSTRAINT FK_SESION_TEMA FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sesion 
            ADD CONSTRAINT FK_SESION_TEMA_MUNICIPAL FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE SET NULL');
    }
}
