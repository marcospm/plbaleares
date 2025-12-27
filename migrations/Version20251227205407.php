<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227205407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE configuracion_examen (id INT AUTO_INCREMENT NOT NULL, porcentaje NUMERIC(5, 2) DEFAULT NULL, activo TINYINT DEFAULT 1 NOT NULL, tema_id INT NOT NULL, INDEX IDX_20A67939A64A8A17 (tema_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE configuracion_examen ADD CONSTRAINT FK_20A67939A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id)');
        
        // Inicializar configuraciones para todos los temas existentes (con porcentaje null = distribución equitativa)
        $this->addSql('INSERT INTO configuracion_examen (tema_id, porcentaje, activo) SELECT id, NULL, 1 FROM tema WHERE activo = 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE configuracion_examen DROP FOREIGN KEY FK_20A67939A64A8A17');
        $this->addSql('DROP TABLE configuracion_examen');
    }
}
