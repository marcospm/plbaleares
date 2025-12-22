<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222091112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE examen_tema_municipal (examen_id INT NOT NULL, tema_municipal_id INT NOT NULL, INDEX IDX_D766CBE75C8659A (examen_id), INDEX IDX_D766CBE71DFFCAC4 (tema_municipal_id), PRIMARY KEY (examen_id, tema_municipal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE municipio (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, fecha_creacion DATETIME NOT NULL, UNIQUE INDEX UNIQ_NOMBRE_MUNICIPIO (nombre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_municipio (municipio_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_FE78780458BC1BE0 (municipio_id), INDEX IDX_FE787804A76ED395 (user_id), PRIMARY KEY (municipio_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pregunta_municipal (id INT AUTO_INCREMENT NOT NULL, texto LONGTEXT NOT NULL, dificultad VARCHAR(20) NOT NULL, retroalimentacion LONGTEXT DEFAULT NULL, opcion_a LONGTEXT NOT NULL, opcion_b LONGTEXT NOT NULL, opcion_c LONGTEXT NOT NULL, opcion_d LONGTEXT NOT NULL, respuesta_correcta VARCHAR(1) NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, tema_municipal_id INT NOT NULL, municipio_id INT NOT NULL, INDEX IDX_A94D00551DFFCAC4 (tema_municipal_id), INDEX IDX_A94D005558BC1BE0 (municipio_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tema_municipal (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, ruta_pdf VARCHAR(500) DEFAULT NULL, activo TINYINT DEFAULT 1 NOT NULL, fecha_creacion DATETIME NOT NULL, municipio_id INT NOT NULL, INDEX IDX_175DEFF358BC1BE0 (municipio_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE examen_tema_municipal ADD CONSTRAINT FK_D766CBE75C8659A FOREIGN KEY (examen_id) REFERENCES examen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_tema_municipal ADD CONSTRAINT FK_D766CBE71DFFCAC4 FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_municipio ADD CONSTRAINT FK_FE78780458BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_municipio ADD CONSTRAINT FK_FE787804A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pregunta_municipal ADD CONSTRAINT FK_A94D00551DFFCAC4 FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id)');
        $this->addSql('ALTER TABLE pregunta_municipal ADD CONSTRAINT FK_A94D005558BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id)');
        $this->addSql('ALTER TABLE tema_municipal ADD CONSTRAINT FK_175DEFF358BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id)');
        $this->addSql('ALTER TABLE examen ADD municipio_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FEC58BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id)');
        $this->addSql('CREATE INDEX IDX_514C8FEC58BC1BE0 ON examen (municipio_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen_tema_municipal DROP FOREIGN KEY FK_D766CBE75C8659A');
        $this->addSql('ALTER TABLE examen_tema_municipal DROP FOREIGN KEY FK_D766CBE71DFFCAC4');
        $this->addSql('ALTER TABLE user_municipio DROP FOREIGN KEY FK_FE78780458BC1BE0');
        $this->addSql('ALTER TABLE user_municipio DROP FOREIGN KEY FK_FE787804A76ED395');
        $this->addSql('ALTER TABLE pregunta_municipal DROP FOREIGN KEY FK_A94D00551DFFCAC4');
        $this->addSql('ALTER TABLE pregunta_municipal DROP FOREIGN KEY FK_A94D005558BC1BE0');
        $this->addSql('ALTER TABLE tema_municipal DROP FOREIGN KEY FK_175DEFF358BC1BE0');
        $this->addSql('DROP TABLE examen_tema_municipal');
        $this->addSql('DROP TABLE municipio');
        $this->addSql('DROP TABLE user_municipio');
        $this->addSql('DROP TABLE pregunta_municipal');
        $this->addSql('DROP TABLE tema_municipal');
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FEC58BC1BE0');
        $this->addSql('DROP INDEX IDX_514C8FEC58BC1BE0 ON examen');
        $this->addSql('ALTER TABLE examen DROP municipio_id');
    }
}
