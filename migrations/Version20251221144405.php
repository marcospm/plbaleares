<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221144405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE articulo (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(50) NOT NULL, explicacion LONGTEXT DEFAULT NULL, ley_id INT NOT NULL, INDEX IDX_69E94E911011658B (ley_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ley (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pregunta (id INT AUTO_INCREMENT NOT NULL, texto LONGTEXT NOT NULL, dificultad VARCHAR(20) NOT NULL, retroalimentacion LONGTEXT DEFAULT NULL, tema_id INT NOT NULL, ley_id INT NOT NULL, articulo_id INT NOT NULL, INDEX IDX_AEE0E1F7A64A8A17 (tema_id), INDEX IDX_AEE0E1F71011658B (ley_id), INDEX IDX_AEE0E1F72DBC2FC9 (articulo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tema (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tema_ley (tema_id INT NOT NULL, ley_id INT NOT NULL, INDEX IDX_677E1AF2A64A8A17 (tema_id), INDEX IDX_677E1AF21011658B (ley_id), PRIMARY KEY (tema_id, ley_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE articulo ADD CONSTRAINT FK_69E94E911011658B FOREIGN KEY (ley_id) REFERENCES ley (id)');
        $this->addSql('ALTER TABLE pregunta ADD CONSTRAINT FK_AEE0E1F7A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id)');
        $this->addSql('ALTER TABLE pregunta ADD CONSTRAINT FK_AEE0E1F71011658B FOREIGN KEY (ley_id) REFERENCES ley (id)');
        $this->addSql('ALTER TABLE pregunta ADD CONSTRAINT FK_AEE0E1F72DBC2FC9 FOREIGN KEY (articulo_id) REFERENCES articulo (id)');
        $this->addSql('ALTER TABLE tema_ley ADD CONSTRAINT FK_677E1AF2A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tema_ley ADD CONSTRAINT FK_677E1AF21011658B FOREIGN KEY (ley_id) REFERENCES ley (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articulo DROP FOREIGN KEY FK_69E94E911011658B');
        $this->addSql('ALTER TABLE pregunta DROP FOREIGN KEY FK_AEE0E1F7A64A8A17');
        $this->addSql('ALTER TABLE pregunta DROP FOREIGN KEY FK_AEE0E1F71011658B');
        $this->addSql('ALTER TABLE pregunta DROP FOREIGN KEY FK_AEE0E1F72DBC2FC9');
        $this->addSql('ALTER TABLE tema_ley DROP FOREIGN KEY FK_677E1AF2A64A8A17');
        $this->addSql('ALTER TABLE tema_ley DROP FOREIGN KEY FK_677E1AF21011658B');
        $this->addSql('DROP TABLE articulo');
        $this->addSql('DROP TABLE ley');
        $this->addSql('DROP TABLE pregunta');
        $this->addSql('DROP TABLE tema');
        $this->addSql('DROP TABLE tema_ley');
    }
}
