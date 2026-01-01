<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración a sistema de planificaciones con fechas específicas
 */
final class Version20260101075801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migración a sistema de planificaciones con fechas específicas';
    }

    public function up(Schema $schema): void
    {
        // Verificar y eliminar foreign key si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @constraintname = 'FK_2CC96A9708CCB';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND CONSTRAINT_NAME = @constraintname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP FOREIGN KEY ', @constraintname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Eliminar índice si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'IDX_2CC96A9708CCB';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @indexname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Eliminar índice único si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'UNIQ_USUARIO_PLANIFICACION';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @indexname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Eliminar columna planificacion_base_id si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'planificacion_base_id';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Agregar columnas a planificacion_personalizada si no existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'nombre';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' VARCHAR(255) DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'descripcion';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' LONGTEXT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'fecha_inicio';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' DATE DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'fecha_fin';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' DATE DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'creado_por_id';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' INT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Actualizar valores NULL con valores por defecto
        $this->addSql("UPDATE planificacion_personalizada SET fecha_inicio = CURDATE() WHERE fecha_inicio IS NULL");
        $this->addSql("UPDATE planificacion_personalizada SET fecha_fin = DATE_ADD(CURDATE(), INTERVAL 7 DAY) WHERE fecha_fin IS NULL");
        $this->addSql("UPDATE planificacion_personalizada SET creado_por_id = (SELECT id FROM user WHERE (roles LIKE '%ROLE_PROFESOR%' OR roles LIKE '%ROLE_ADMIN%') LIMIT 1) WHERE creado_por_id IS NULL");

        // Hacer las columnas NOT NULL
        $this->addSql('ALTER TABLE planificacion_personalizada MODIFY fecha_inicio DATE NOT NULL');
        $this->addSql('ALTER TABLE planificacion_personalizada MODIFY fecha_fin DATE NOT NULL');
        $this->addSql('ALTER TABLE planificacion_personalizada MODIFY creado_por_id INT NOT NULL');

        // Agregar foreign key si no existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @constraintname = 'FK_PLANIFICACION_CREADO_POR';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND CONSTRAINT_NAME = @constraintname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, ' FOREIGN KEY (creado_por_id) REFERENCES user (id);'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Crear índices si no existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'IDX_PLANIFICACION_FECHAS';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) = 0,
                CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, ' (fecha_inicio, fecha_fin);'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'IDX_PLANIFICACION_USUARIO';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) = 0,
                CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, ' (usuario_id);'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Modificar tabla franja_horaria_personalizada
        // Eliminar columna dia_semana si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'dia_semana';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Agregar fecha_especifica si no existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'fecha_especifica';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' DATE DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Actualizar fecha_especifica si es NULL (usar fecha actual como fallback)
        $this->addSql("UPDATE franja_horaria_personalizada SET fecha_especifica = CURDATE() WHERE fecha_especifica IS NULL");
        $this->addSql('ALTER TABLE franja_horaria_personalizada MODIFY fecha_especifica DATE NOT NULL');

        // Agregar nuevas columnas si no existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'temas';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' LONGTEXT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'recursos';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' LONGTEXT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'enlaces';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' LONGTEXT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'notas';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' LONGTEXT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Crear índice si no existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @indexname = 'IDX_FRANJA_FECHA';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) = 0,
                CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, ' (fecha_especifica);'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");
    }

    public function down(Schema $schema): void
    {
        // Revertir cambios en planificacion_personalizada
        // Eliminar foreign key si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @constraintname = 'FK_PLANIFICACION_CREADO_POR';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND CONSTRAINT_NAME = @constraintname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP FOREIGN KEY ', @constraintname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Eliminar índices si existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'IDX_PLANIFICACION_FECHAS';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @indexname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'IDX_PLANIFICACION_USUARIO';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @indexname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Eliminar columnas si existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'creado_por_id';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'fecha_fin';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'fecha_inicio';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'descripcion';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'nombre';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Agregar columnas del sistema antiguo si no existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @columnname = 'planificacion_base_id';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' INT DEFAULT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Agregar foreign key si no existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @constraintname = 'FK_2CC96A9708CCB';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND CONSTRAINT_NAME = @constraintname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, ' FOREIGN KEY (planificacion_base_id) REFERENCES planificacion_semanal (id);'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Crear índice único si no existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'planificacion_personalizada';
            SET @indexname = 'UNIQ_USUARIO_PLANIFICACION';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) = 0,
                CONCAT('CREATE UNIQUE INDEX ', @indexname, ' ON ', @tablename, ' (usuario_id);'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Revertir cambios en franja_horaria_personalizada
        // Eliminar índice si existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @indexname = 'IDX_FRANJA_FECHA';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND INDEX_NAME = @indexname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @indexname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Eliminar columnas si existen
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'notas';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'enlaces';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'recursos';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'temas';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'fecha_especifica';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) > 0,
                CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname, ';'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");

        // Agregar columna dia_semana si no existe
        $this->addSql("SET @dbname = DATABASE();
            SET @tablename = 'franja_horaria_personalizada';
            SET @columnname = 'dia_semana';
            SET @preparedStatement = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND COLUMN_NAME = @columnname) = 0,
                CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' INT NOT NULL;'),
                'SELECT 1;'
            ));
            PREPARE stmt FROM @preparedStatement;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;");
    }
}
