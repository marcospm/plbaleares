<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251223114840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notificacion ADD articulo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19EC2DBC2FC9 FOREIGN KEY (articulo_id) REFERENCES articulo (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_729A19EC2DBC2FC9 ON notificacion (articulo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19EC2DBC2FC9');
        $this->addSql('DROP INDEX IDX_729A19EC2DBC2FC9 ON notificacion');
        $this->addSql('ALTER TABLE notificacion DROP articulo_id');
    }
}
