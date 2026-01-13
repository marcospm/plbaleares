<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración para crear la tabla sesion
 */
final class Version20250113140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla sesion para gestionar sesiones de video de profesores';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla sesion
        $this->addSql('CREATE TABLE sesion (
            id INT AUTO_INCREMENT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            descripcion LONGTEXT DEFAULT NULL,
            municipio_id INT DEFAULT NULL,
            convocatoria_id INT DEFAULT NULL,
            enlace_video LONGTEXT NOT NULL,
            fecha_creacion DATETIME NOT NULL,
            creado_por_id INT NOT NULL,
            INDEX IDX_SESION_MUNICIPIO (municipio_id),
            INDEX IDX_SESION_CONVOCATORIA (convocatoria_id),
            INDEX IDX_SESION_CREADO_POR (creado_por_id),
            INDEX IDX_SESION_FECHA_CREACION (fecha_creacion),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Crear tabla de relación ManyToMany sesion_tema
        $this->addSql('CREATE TABLE sesion_tema (
            sesion_id INT NOT NULL,
            tema_id INT NOT NULL,
            INDEX IDX_SESION_TEMA_SESION (sesion_id),
            INDEX IDX_SESION_TEMA_TEMA (tema_id),
            PRIMARY KEY(sesion_id, tema_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Crear tabla de relación ManyToMany sesion_tema_municipal
        $this->addSql('CREATE TABLE sesion_tema_municipal (
            sesion_id INT NOT NULL,
            tema_municipal_id INT NOT NULL,
            INDEX IDX_SESION_TEMA_MUNICIPAL_SESION (sesion_id),
            INDEX IDX_SESION_TEMA_MUNICIPAL_TEMA (tema_municipal_id),
            PRIMARY KEY(sesion_id, tema_municipal_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Añadir foreign keys
        $this->addSql('ALTER TABLE sesion ADD CONSTRAINT FK_SESION_MUNICIPIO FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sesion ADD CONSTRAINT FK_SESION_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sesion ADD CONSTRAINT FK_SESION_CREADO_POR FOREIGN KEY (creado_por_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sesion_tema ADD CONSTRAINT FK_SESION_TEMA_SESION FOREIGN KEY (sesion_id) REFERENCES sesion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sesion_tema ADD CONSTRAINT FK_SESION_TEMA_TEMA FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sesion_tema_municipal ADD CONSTRAINT FK_SESION_TEMA_MUNICIPAL_SESION FOREIGN KEY (sesion_id) REFERENCES sesion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sesion_tema_municipal ADD CONSTRAINT FK_SESION_TEMA_MUNICIPAL_TEMA FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Eliminar foreign keys de tablas de relación
        $this->addSql('ALTER TABLE sesion_tema DROP FOREIGN KEY FK_SESION_TEMA_SESION');
        $this->addSql('ALTER TABLE sesion_tema DROP FOREIGN KEY FK_SESION_TEMA_TEMA');
        $this->addSql('ALTER TABLE sesion_tema_municipal DROP FOREIGN KEY FK_SESION_TEMA_MUNICIPAL_SESION');
        $this->addSql('ALTER TABLE sesion_tema_municipal DROP FOREIGN KEY FK_SESION_TEMA_MUNICIPAL_TEMA');
        
        // Eliminar foreign keys de tabla principal
        $this->addSql('ALTER TABLE sesion DROP FOREIGN KEY FK_SESION_MUNICIPIO');
        $this->addSql('ALTER TABLE sesion DROP FOREIGN KEY FK_SESION_CONVOCATORIA');
        $this->addSql('ALTER TABLE sesion DROP FOREIGN KEY FK_SESION_CREADO_POR');
        
        // Eliminar tablas
        $this->addSql('DROP TABLE sesion_tema');
        $this->addSql('DROP TABLE sesion_tema_municipal');
        $this->addSql('DROP TABLE sesion');
    }
}
