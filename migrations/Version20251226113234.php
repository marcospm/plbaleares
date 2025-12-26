<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226113234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD email VARCHAR(255) DEFAULT NULL, ADD telefono VARCHAR(20) DEFAULT NULL, ADD direccion LONGTEXT DEFAULT NULL, ADD codigo_postal VARCHAR(10) DEFAULT NULL, ADD ciudad VARCHAR(100) DEFAULT NULL, ADD provincia VARCHAR(100) DEFAULT NULL, ADD fecha_nacimiento DATE DEFAULT NULL, ADD sexo VARCHAR(1) DEFAULT NULL, ADD dni VARCHAR(20) DEFAULT NULL, ADD iban VARCHAR(34) DEFAULT NULL, ADD banco VARCHAR(100) DEFAULT NULL, ADD notas LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP email, DROP telefono, DROP direccion, DROP codigo_postal, DROP ciudad, DROP provincia, DROP fecha_nacimiento, DROP sexo, DROP dni, DROP iban, DROP banco, DROP notas');
    }
}
