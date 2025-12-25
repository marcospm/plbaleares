<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251225104606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mensaje_articulo (id INT AUTO_INCREMENT NOT NULL, mensaje LONGTEXT NOT NULL, fecha_creacion DATETIME NOT NULL, es_respuesta TINYINT DEFAULT 0 NOT NULL, articulo_id INT NOT NULL, autor_id INT NOT NULL, mensaje_padre_id INT DEFAULT NULL, INDEX IDX_7A138BB02DBC2FC9 (articulo_id), INDEX IDX_7A138BB014D45BBE (autor_id), INDEX IDX_7A138BB072C62979 (mensaje_padre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mensaje_articulo ADD CONSTRAINT FK_7A138BB02DBC2FC9 FOREIGN KEY (articulo_id) REFERENCES articulo (id)');
        $this->addSql('ALTER TABLE mensaje_articulo ADD CONSTRAINT FK_7A138BB014D45BBE FOREIGN KEY (autor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE mensaje_articulo ADD CONSTRAINT FK_7A138BB072C62979 FOREIGN KEY (mensaje_padre_id) REFERENCES mensaje_articulo (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje_articulo DROP FOREIGN KEY FK_7A138BB02DBC2FC9');
        $this->addSql('ALTER TABLE mensaje_articulo DROP FOREIGN KEY FK_7A138BB014D45BBE');
        $this->addSql('ALTER TABLE mensaje_articulo DROP FOREIGN KEY FK_7A138BB072C62979');
        $this->addSql('DROP TABLE mensaje_articulo');
    }
}
