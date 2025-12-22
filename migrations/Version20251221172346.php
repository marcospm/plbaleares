<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221172346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE franja_horaria (id INT AUTO_INCREMENT NOT NULL, dia_semana INT NOT NULL, hora_inicio TIME NOT NULL, hora_fin TIME NOT NULL, tipo_actividad VARCHAR(50) NOT NULL, descripcion_repaso VARCHAR(255) DEFAULT NULL, orden INT NOT NULL, planificacion_id INT NOT NULL, INDEX IDX_570EA8204428E082 (planificacion_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE franja_horaria_personalizada (id INT AUTO_INCREMENT NOT NULL, dia_semana INT NOT NULL, hora_inicio TIME NOT NULL, hora_fin TIME NOT NULL, tipo_actividad VARCHAR(50) NOT NULL, descripcion_repaso VARCHAR(255) DEFAULT NULL, orden INT NOT NULL, planificacion_id INT NOT NULL, franja_base_id INT DEFAULT NULL, INDEX IDX_DB0B15454428E082 (planificacion_id), INDEX IDX_DB0B15453B8909C5 (franja_base_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planificacion_personalizada (id INT AUTO_INCREMENT NOT NULL, fecha_creacion DATETIME NOT NULL, fecha_modificacion DATETIME NOT NULL, usuario_id INT NOT NULL, planificacion_base_id INT DEFAULT NULL, INDEX IDX_2CC96A9708CCB (planificacion_base_id), UNIQUE INDEX UNIQ_USUARIO_PLANIFICACION (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planificacion_semanal (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, activa TINYINT DEFAULT 1 NOT NULL, creado_por_id INT NOT NULL, INDEX IDX_E9516A8EFE35D8C4 (creado_por_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tarea (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT NOT NULL, semana_asignacion DATE NOT NULL, fecha_creacion DATETIME NOT NULL, creado_por_id INT NOT NULL, tema_id INT DEFAULT NULL, ley_id INT DEFAULT NULL, articulo_id INT DEFAULT NULL, INDEX IDX_3CA05366FE35D8C4 (creado_por_id), INDEX IDX_3CA05366A64A8A17 (tema_id), INDEX IDX_3CA053661011658B (ley_id), INDEX IDX_3CA053662DBC2FC9 (articulo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tarea_asignada (id INT AUTO_INCREMENT NOT NULL, completada TINYINT DEFAULT 0 NOT NULL, fecha_completada DATETIME DEFAULT NULL, fecha_asignacion DATETIME NOT NULL, tarea_id INT NOT NULL, usuario_id INT NOT NULL, franja_horaria_id INT DEFAULT NULL, INDEX IDX_E7C1C3AC6D5BDFE1 (tarea_id), INDEX IDX_E7C1C3ACDB38439E (usuario_id), INDEX IDX_E7C1C3ACDF6A9861 (franja_horaria_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE franja_horaria ADD CONSTRAINT FK_570EA8204428E082 FOREIGN KEY (planificacion_id) REFERENCES planificacion_semanal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE franja_horaria_personalizada ADD CONSTRAINT FK_DB0B15454428E082 FOREIGN KEY (planificacion_id) REFERENCES planificacion_personalizada (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE franja_horaria_personalizada ADD CONSTRAINT FK_DB0B15453B8909C5 FOREIGN KEY (franja_base_id) REFERENCES franja_horaria (id)');
        $this->addSql('ALTER TABLE planificacion_personalizada ADD CONSTRAINT FK_2CC96A9DB38439E FOREIGN KEY (usuario_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE planificacion_personalizada ADD CONSTRAINT FK_2CC96A9708CCB FOREIGN KEY (planificacion_base_id) REFERENCES planificacion_semanal (id)');
        $this->addSql('ALTER TABLE planificacion_semanal ADD CONSTRAINT FK_E9516A8EFE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tarea ADD CONSTRAINT FK_3CA05366FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tarea ADD CONSTRAINT FK_3CA05366A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id)');
        $this->addSql('ALTER TABLE tarea ADD CONSTRAINT FK_3CA053661011658B FOREIGN KEY (ley_id) REFERENCES ley (id)');
        $this->addSql('ALTER TABLE tarea ADD CONSTRAINT FK_3CA053662DBC2FC9 FOREIGN KEY (articulo_id) REFERENCES articulo (id)');
        $this->addSql('ALTER TABLE tarea_asignada ADD CONSTRAINT FK_E7C1C3AC6D5BDFE1 FOREIGN KEY (tarea_id) REFERENCES tarea (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tarea_asignada ADD CONSTRAINT FK_E7C1C3ACDB38439E FOREIGN KEY (usuario_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tarea_asignada ADD CONSTRAINT FK_E7C1C3ACDF6A9861 FOREIGN KEY (franja_horaria_id) REFERENCES franja_horaria_personalizada (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE franja_horaria DROP FOREIGN KEY FK_570EA8204428E082');
        $this->addSql('ALTER TABLE franja_horaria_personalizada DROP FOREIGN KEY FK_DB0B15454428E082');
        $this->addSql('ALTER TABLE franja_horaria_personalizada DROP FOREIGN KEY FK_DB0B15453B8909C5');
        $this->addSql('ALTER TABLE planificacion_personalizada DROP FOREIGN KEY FK_2CC96A9DB38439E');
        $this->addSql('ALTER TABLE planificacion_personalizada DROP FOREIGN KEY FK_2CC96A9708CCB');
        $this->addSql('ALTER TABLE planificacion_semanal DROP FOREIGN KEY FK_E9516A8EFE35D8C4');
        $this->addSql('ALTER TABLE tarea DROP FOREIGN KEY FK_3CA05366FE35D8C4');
        $this->addSql('ALTER TABLE tarea DROP FOREIGN KEY FK_3CA05366A64A8A17');
        $this->addSql('ALTER TABLE tarea DROP FOREIGN KEY FK_3CA053661011658B');
        $this->addSql('ALTER TABLE tarea DROP FOREIGN KEY FK_3CA053662DBC2FC9');
        $this->addSql('ALTER TABLE tarea_asignada DROP FOREIGN KEY FK_E7C1C3AC6D5BDFE1');
        $this->addSql('ALTER TABLE tarea_asignada DROP FOREIGN KEY FK_E7C1C3ACDB38439E');
        $this->addSql('ALTER TABLE tarea_asignada DROP FOREIGN KEY FK_E7C1C3ACDF6A9861');
        $this->addSql('DROP TABLE franja_horaria');
        $this->addSql('DROP TABLE franja_horaria_personalizada');
        $this->addSql('DROP TABLE planificacion_personalizada');
        $this->addSql('DROP TABLE planificacion_semanal');
        $this->addSql('DROP TABLE tarea');
        $this->addSql('DROP TABLE tarea_asignada');
    }
}
