<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222093504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE convocatoria (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, fecha_teorico DATE DEFAULT NULL, fecha_fisicas DATE DEFAULT NULL, fecha_psicotecnico DATE DEFAULT NULL, activo TINYINT DEFAULT 1 NOT NULL, fecha_creacion DATETIME NOT NULL, fecha_actualizacion DATETIME NOT NULL, municipio_id INT DEFAULT NULL, INDEX IDX_6D77302158BC1BE0 (municipio_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE convocatoria_user (convocatoria_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_5DD133784EE93BE6 (convocatoria_id), INDEX IDX_5DD13378A76ED395 (user_id), PRIMARY KEY (convocatoria_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE convocatoria ADD CONSTRAINT FK_6D77302158BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id)');
        $this->addSql('ALTER TABLE convocatoria_user ADD CONSTRAINT FK_5DD133784EE93BE6 FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE convocatoria_user ADD CONSTRAINT FK_5DD13378A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE convocatoria DROP FOREIGN KEY FK_6D77302158BC1BE0');
        $this->addSql('ALTER TABLE convocatoria_user DROP FOREIGN KEY FK_5DD133784EE93BE6');
        $this->addSql('ALTER TABLE convocatoria_user DROP FOREIGN KEY FK_5DD13378A76ED395');
        $this->addSql('DROP TABLE convocatoria');
        $this->addSql('DROP TABLE convocatoria_user');
    }
}
