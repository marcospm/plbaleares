<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102145546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cambiar relación de Convocatoria con Municipio de ManyToOne a ManyToMany';
    }

    public function up(Schema $schema): void
    {
        // Crear la nueva tabla para la relación ManyToMany
        $this->addSql('CREATE TABLE IF NOT EXISTS convocatoria_municipio (convocatoria_id INT NOT NULL, municipio_id INT NOT NULL, INDEX IDX_CONVOCATORIA_MUNICIPIO_CONVOCATORIA (convocatoria_id), INDEX IDX_CONVOCATORIA_MUNICIPIO_MUNICIPIO (municipio_id), PRIMARY KEY(convocatoria_id, municipio_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Migrar los datos existentes de municipio_id a la nueva tabla
        $this->addSql('INSERT IGNORE INTO convocatoria_municipio (convocatoria_id, municipio_id) SELECT id, municipio_id FROM convocatoria WHERE municipio_id IS NOT NULL');
        
        // Añadir las claves foráneas
        try {
            $this->addSql('ALTER TABLE convocatoria_municipio ADD CONSTRAINT FK_CONVOCATORIA_MUNICIPIO_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            // La constraint ya existe, continuar
        }
        
        try {
            $this->addSql('ALTER TABLE convocatoria_municipio ADD CONSTRAINT FK_CONVOCATORIA_MUNICIPIO_MUNICIPIO FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            // La constraint ya existe, continuar
        }
        
        // Eliminar la columna municipio_id y su clave foránea
        // Primero intentar eliminar la clave foránea
        try {
            $this->addSql('ALTER TABLE convocatoria DROP FOREIGN KEY FK_6D77302158BC1BE0');
        } catch (\Exception $e) {
            // La constraint no existe o tiene otro nombre, continuar
        }
        
        // Eliminar el índice
        try {
            $this->addSql('DROP INDEX IDX_6D77302158BC1BE0 ON convocatoria');
        } catch (\Exception $e) {
            // El índice no existe, continuar
        }
        
        // Eliminar la columna
        try {
            $this->addSql('ALTER TABLE convocatoria DROP COLUMN municipio_id');
        } catch (\Exception $e) {
            // La columna no existe, continuar
        }
    }

    public function down(Schema $schema): void
    {
        // Añadir de nuevo la columna municipio_id
        $this->addSql('ALTER TABLE convocatoria ADD municipio_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE convocatoria ADD CONSTRAINT FK_6D77302158BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id)');
        $this->addSql('CREATE INDEX IDX_6D77302158BC1BE0 ON convocatoria (municipio_id)');
        
        // Migrar los datos de vuelta (solo el primer municipio de cada convocatoria)
        $this->addSql('UPDATE convocatoria c SET c.municipio_id = (SELECT cm.municipio_id FROM convocatoria_municipio cm WHERE cm.convocatoria_id = c.id LIMIT 1)');
        
        // Eliminar la tabla de relación ManyToMany
        $this->addSql('ALTER TABLE convocatoria_municipio DROP FOREIGN KEY FK_CONVOCATORIA_MUNICIPIO_CONVOCATORIA');
        $this->addSql('ALTER TABLE convocatoria_municipio DROP FOREIGN KEY FK_CONVOCATORIA_MUNICIPIO_MUNICIPIO');
        $this->addSql('DROP TABLE IF EXISTS convocatoria_municipio');
    }
}
