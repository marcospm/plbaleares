<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crea la tabla grupo y la tabla de unión grupo_user para gestionar grupos de alumnos
 */
final class Version20260104102311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea la tabla grupo y la tabla de unión grupo_user para gestionar grupos de alumnos';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla grupo
        $this->addSql('CREATE TABLE grupo (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, fecha_creacion DATETIME NOT NULL, fecha_actualizacion DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Crear tabla de unión grupo_user
        $this->addSql('CREATE TABLE grupo_user (grupo_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_6DC044C59C833003 (grupo_id), INDEX IDX_6DC044C5A76ED395 (user_id), PRIMARY KEY(grupo_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Añadir foreign keys
        $this->addSql('ALTER TABLE grupo_user ADD CONSTRAINT FK_6DC044C59C833003 FOREIGN KEY (grupo_id) REFERENCES grupo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE grupo_user ADD CONSTRAINT FK_6DC044C5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Eliminar foreign keys
        $this->addSql('ALTER TABLE grupo_user DROP FOREIGN KEY FK_6DC044C59C833003');
        $this->addSql('ALTER TABLE grupo_user DROP FOREIGN KEY FK_6DC044C5A76ED395');
        
        // Eliminar tablas
        $this->addSql('DROP TABLE grupo_user');
        $this->addSql('DROP TABLE grupo');
    }
}

