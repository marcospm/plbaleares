<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marca las cuentas con datos personales suprimidos y la fecha de supresión';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD suprimido TINYINT(1) DEFAULT 0 NOT NULL, ADD suprimido_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USER_SUPRIMIDO_AT ON user (suprimido_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_USER_SUPRIMIDO_AT ON user');
        $this->addSql('ALTER TABLE user DROP suprimido, DROP suprimido_at');
    }
}
