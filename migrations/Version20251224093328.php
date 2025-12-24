<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251224093328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE examen_semanal_pregunta (examen_semanal_id INT NOT NULL, pregunta_id INT NOT NULL, INDEX IDX_6C881AE983394AC (examen_semanal_id), INDEX IDX_6C881AE931A5801E (pregunta_id), PRIMARY KEY (examen_semanal_id, pregunta_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen_semanal_pregunta_municipal (examen_semanal_id INT NOT NULL, pregunta_municipal_id INT NOT NULL, INDEX IDX_DC05D9E583394AC (examen_semanal_id), INDEX IDX_DC05D9E545B14EF (pregunta_municipal_id), PRIMARY KEY (examen_semanal_id, pregunta_municipal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE examen_semanal_pregunta ADD CONSTRAINT FK_6C881AE983394AC FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal_pregunta ADD CONSTRAINT FK_6C881AE931A5801E FOREIGN KEY (pregunta_id) REFERENCES pregunta (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal_pregunta_municipal ADD CONSTRAINT FK_DC05D9E583394AC FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal_pregunta_municipal ADD CONSTRAINT FK_DC05D9E545B14EF FOREIGN KEY (pregunta_municipal_id) REFERENCES pregunta_municipal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal ADD modo_creacion VARCHAR(50) DEFAULT \'temas\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen_semanal_pregunta DROP FOREIGN KEY FK_6C881AE983394AC');
        $this->addSql('ALTER TABLE examen_semanal_pregunta DROP FOREIGN KEY FK_6C881AE931A5801E');
        $this->addSql('ALTER TABLE examen_semanal_pregunta_municipal DROP FOREIGN KEY FK_DC05D9E583394AC');
        $this->addSql('ALTER TABLE examen_semanal_pregunta_municipal DROP FOREIGN KEY FK_DC05D9E545B14EF');
        $this->addSql('DROP TABLE examen_semanal_pregunta');
        $this->addSql('DROP TABLE examen_semanal_pregunta_municipal');
        $this->addSql('ALTER TABLE examen_semanal DROP modo_creacion');
    }
}
