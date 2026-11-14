<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marca las sesiones de psicotécnicos y añade el archivo de psicotécnicos con categorías editables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sesion ADD es_psicotecnico TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE categoria_psicotecnico (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, orden INT DEFAULT 0 NOT NULL, activo TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_CATEGORIA_PSICOTECNICO_NOMBRE (nombre), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE archivo_psicotecnico (id INT AUTO_INCREMENT NOT NULL, creado_por_id INT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, ruta_archivo VARCHAR(500) NOT NULL, nombre_archivo_original VARCHAR(255) DEFAULT NULL, fecha_creacion DATETIME NOT NULL, INDEX IDX_ARCHIVO_PSICOTECNICO_CREADO_POR (creado_por_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE archivo_psicotecnico_categoria (archivo_psicotecnico_id INT NOT NULL, categoria_psicotecnico_id INT NOT NULL, INDEX IDX_ARCHIVO_PSICOTECNICO_CATEGORIA_ARCHIVO (archivo_psicotecnico_id), INDEX IDX_ARCHIVO_PSICOTECNICO_CATEGORIA_CATEGORIA (categoria_psicotecnico_id), PRIMARY KEY(archivo_psicotecnico_id, categoria_psicotecnico_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE archivo_psicotecnico ADD CONSTRAINT FK_ARCHIVO_PSICOTECNICO_CREADO_POR FOREIGN KEY (creado_por_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE archivo_psicotecnico_categoria ADD CONSTRAINT FK_ARCHIVO_PSICOTECNICO_CATEGORIA_ARCHIVO FOREIGN KEY (archivo_psicotecnico_id) REFERENCES archivo_psicotecnico (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE archivo_psicotecnico_categoria ADD CONSTRAINT FK_ARCHIVO_PSICOTECNICO_CATEGORIA_CATEGORIA FOREIGN KEY (categoria_psicotecnico_id) REFERENCES categoria_psicotecnico (id)');
        $this->addSql("INSERT INTO categoria_psicotecnico (nombre, descripcion, orden, activo) VALUES
            ('Matemáticas', 'Ejercicios y material de psicotécnicos de matemáticas.', 1, 1),
            ('Apuntes de matemáticas', 'Apuntes de teoría y método para matemáticas.', 2, 1),
            ('Verbal', 'Psicotécnicos de aptitud verbal.', 3, 1),
            ('Espacial', 'Psicotécnicos de aptitud espacial.', 4, 1),
            ('Ómnibus', 'Psicotécnicos ómnibus.', 5, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archivo_psicotecnico_categoria DROP FOREIGN KEY FK_ARCHIVO_PSICOTECNICO_CATEGORIA_ARCHIVO');
        $this->addSql('ALTER TABLE archivo_psicotecnico_categoria DROP FOREIGN KEY FK_ARCHIVO_PSICOTECNICO_CATEGORIA_CATEGORIA');
        $this->addSql('ALTER TABLE archivo_psicotecnico DROP FOREIGN KEY FK_ARCHIVO_PSICOTECNICO_CREADO_POR');
        $this->addSql('DROP TABLE archivo_psicotecnico_categoria');
        $this->addSql('DROP TABLE archivo_psicotecnico');
        $this->addSql('DROP TABLE categoria_psicotecnico');
        $this->addSql('ALTER TABLE sesion DROP es_psicotecnico');
    }
}
