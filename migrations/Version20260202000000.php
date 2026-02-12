<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración para convertir relaciones ManyToOne a ManyToMany en la tabla sesion
 * 
 * Esta migración:
 * 1. Crea las tablas ManyToMany para municipios y convocatorias
 * 2. Migra los datos de municipio_id y convocatoria_id a las nuevas tablas
 * 3. Elimina las columnas antiguas
 */
final class Version20260202000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertir relaciones ManyToOne a ManyToMany en sesion: municipios y convocatorias';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla ManyToMany para sesion_municipio
        $this->addSql('CREATE TABLE IF NOT EXISTS sesion_municipio (
            sesion_id INT NOT NULL,
            municipio_id INT NOT NULL,
            INDEX IDX_SESION_MUNICIPIO_SESION (sesion_id),
            INDEX IDX_SESION_MUNICIPIO_MUNICIPIO (municipio_id),
            PRIMARY KEY(sesion_id, municipio_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Crear tabla ManyToMany para sesion_convocatoria
        $this->addSql('CREATE TABLE IF NOT EXISTS sesion_convocatoria (
            sesion_id INT NOT NULL,
            convocatoria_id INT NOT NULL,
            INDEX IDX_SESION_CONVOCATORIA_SESION (sesion_id),
            INDEX IDX_SESION_CONVOCATORIA_CONVOCATORIA (convocatoria_id),
            PRIMARY KEY(sesion_id, convocatoria_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Migrar datos de municipio_id a sesion_municipio (solo si la columna existe)
        $this->addSql('INSERT IGNORE INTO sesion_municipio (sesion_id, municipio_id)
            SELECT id, municipio_id 
            FROM sesion 
            WHERE municipio_id IS NOT NULL');
        
        // Migrar datos de convocatoria_id a sesion_convocatoria (solo si la columna existe)
        $this->addSql('INSERT IGNORE INTO sesion_convocatoria (sesion_id, convocatoria_id)
            SELECT id, convocatoria_id 
            FROM sesion 
            WHERE convocatoria_id IS NOT NULL');
        
        // Añadir foreign keys para sesion_municipio
        $this->addSql('ALTER TABLE sesion_municipio ADD CONSTRAINT FK_SESION_MUNICIPIO_SESION FOREIGN KEY (sesion_id) REFERENCES sesion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sesion_municipio ADD CONSTRAINT FK_SESION_MUNICIPIO_MUNICIPIO FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE CASCADE');
        
        // Añadir foreign keys para sesion_convocatoria
        $this->addSql('ALTER TABLE sesion_convocatoria ADD CONSTRAINT FK_SESION_CONVOCATORIA_SESION FOREIGN KEY (sesion_id) REFERENCES sesion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sesion_convocatoria ADD CONSTRAINT FK_SESION_CONVOCATORIA_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
        
        // Eliminar foreign keys antiguas (ignorar error si no existen)
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'sesion' AND constraint_name = 'FK_SESION_MUNICIPIO');
        SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE sesion DROP FOREIGN KEY FK_SESION_MUNICIPIO', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'sesion' AND constraint_name = 'FK_SESION_CONVOCATORIA');
        SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE sesion DROP FOREIGN KEY FK_SESION_CONVOCATORIA', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        // Eliminar índices antiguos (ignorar error si no existen)
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'sesion' AND index_name = 'IDX_SESION_MUNICIPIO');
        SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE sesion DROP INDEX IDX_SESION_MUNICIPIO', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'sesion' AND index_name = 'IDX_SESION_CONVOCATORIA');
        SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE sesion DROP INDEX IDX_SESION_CONVOCATORIA', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        // Eliminar columnas antiguas (ignorar error si no existen)
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'sesion' AND column_name = 'municipio_id');
        SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE sesion DROP COLUMN municipio_id', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'sesion' AND column_name = 'convocatoria_id');
        SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE sesion DROP COLUMN convocatoria_id', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
    }

    public function down(Schema $schema): void
    {
        // Recrear columnas antiguas (ignorar error si ya existen)
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'sesion' AND column_name = 'municipio_id');
        SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sesion ADD municipio_id INT DEFAULT NULL', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'sesion' AND column_name = 'convocatoria_id');
        SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sesion ADD convocatoria_id INT DEFAULT NULL', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        // Migrar datos de vuelta (tomar el primer municipio y convocatoria de cada sesión)
        $this->addSql('UPDATE sesion s
            INNER JOIN (
                SELECT sesion_id, MIN(municipio_id) as municipio_id
                FROM sesion_municipio
                GROUP BY sesion_id
            ) sm ON s.id = sm.sesion_id
            SET s.municipio_id = sm.municipio_id');
        
        $this->addSql('UPDATE sesion s
            INNER JOIN (
                SELECT sesion_id, MIN(convocatoria_id) as convocatoria_id
                FROM sesion_convocatoria
                GROUP BY sesion_id
            ) sc ON s.id = sc.sesion_id
            SET s.convocatoria_id = sc.convocatoria_id');
        
        // Recrear índices y foreign keys
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'sesion' AND index_name = 'IDX_SESION_MUNICIPIO');
        SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX IDX_SESION_MUNICIPIO ON sesion (municipio_id)', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        $this->addSql("SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'sesion' AND index_name = 'IDX_SESION_CONVOCATORIA');
        SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX IDX_SESION_CONVOCATORIA ON sesion (convocatoria_id)', 'SELECT 1');
        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;");
        
        $this->addSql('ALTER TABLE sesion ADD CONSTRAINT FK_SESION_MUNICIPIO FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sesion ADD CONSTRAINT FK_SESION_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE SET NULL');
        
        // Eliminar tablas ManyToMany
        $this->addSql('DROP TABLE IF EXISTS sesion_convocatoria');
        $this->addSql('DROP TABLE IF EXISTS sesion_municipio');
    }
}
