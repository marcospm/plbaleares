<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega token y caducidad para recuperacion de contrasena en usuarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD reset_password_token VARCHAR(64) DEFAULT NULL, ADD reset_password_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USER_RESET_PASSWORD_TOKEN ON user (reset_password_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_USER_RESET_PASSWORD_TOKEN ON user');
        $this->addSql('ALTER TABLE user DROP reset_password_token, DROP reset_password_expires_at');
    }
}
