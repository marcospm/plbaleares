<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105114728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade tabla examen_borrador para guardar exámenes en progreso';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs!
        $this->addSql('CREATE TABLE examen_borrador (id INT AUTO_INCREMENT NOT NULL, usuario_id INT NOT NULL, examen_semanal_id INT DEFAULT NULL, tipo_examen VARCHAR(50) NOT NULL, config JSON NOT NULL, preguntas_ids JSON NOT NULL, respuestas JSON NOT NULL, pregunta_actual INT NOT NULL, tiempo_restante INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, fecha_actualizacion DATETIME NOT NULL, INDEX IDX_examen_borrador_usuario (usuario_id), INDEX IDX_examen_borrador_examen_semanal (examen_semanal_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE examen_borrador ADD CONSTRAINT FK_examen_borrador_usuario FOREIGN KEY (usuario_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_borrador ADD CONSTRAINT FK_examen_borrador_examen_semanal FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs!
        $this->addSql('ALTER TABLE examen_borrador DROP FOREIGN KEY FK_examen_borrador_usuario');
        $this->addSql('ALTER TABLE examen_borrador DROP FOREIGN KEY FK_examen_borrador_examen_semanal');
        $this->addSql('DROP TABLE examen_borrador');
    }
}
