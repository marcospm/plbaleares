<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221191550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mensaje_contacto (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, asunto VARCHAR(255) DEFAULT NULL, mensaje LONGTEXT NOT NULL, fecha_creacion DATETIME NOT NULL, leido TINYINT DEFAULT 0 NOT NULL, usuario_id INT DEFAULT NULL, INDEX IDX_34BB8C1DDB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mensaje_contacto ADD CONSTRAINT FK_34BB8C1DDB38439E FOREIGN KEY (usuario_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje_contacto DROP FOREIGN KEY FK_34BB8C1DDB38439E');
        $this->addSql('DROP TABLE mensaje_contacto');
    }
}
