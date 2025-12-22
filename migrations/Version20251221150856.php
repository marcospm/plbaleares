<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221150856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE examen (id INT AUTO_INCREMENT NOT NULL, dificultad VARCHAR(20) NOT NULL, numero_preguntas INT NOT NULL, fecha DATETIME NOT NULL, nota NUMERIC(4, 2) NOT NULL, aciertos INT NOT NULL, errores INT NOT NULL, respuestas JSON NOT NULL, usuario_id INT NOT NULL, INDEX IDX_514C8FECDB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen_tema (examen_id INT NOT NULL, tema_id INT NOT NULL, INDEX IDX_BEF841215C8659A (examen_id), INDEX IDX_BEF84121A64A8A17 (tema_id), PRIMARY KEY (examen_id, tema_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FECDB38439E FOREIGN KEY (usuario_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE examen_tema ADD CONSTRAINT FK_BEF841215C8659A FOREIGN KEY (examen_id) REFERENCES examen (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_tema ADD CONSTRAINT FK_BEF84121A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pregunta ADD opcion_a LONGTEXT NOT NULL, ADD opcion_b LONGTEXT NOT NULL, ADD opcion_c LONGTEXT NOT NULL, ADD opcion_d LONGTEXT NOT NULL, ADD respuesta_correcta VARCHAR(1) NOT NULL');
        $this->addSql('ALTER TABLE tema ADD ruta_pdf VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FECDB38439E');
        $this->addSql('ALTER TABLE examen_tema DROP FOREIGN KEY FK_BEF841215C8659A');
        $this->addSql('ALTER TABLE examen_tema DROP FOREIGN KEY FK_BEF84121A64A8A17');
        $this->addSql('DROP TABLE examen');
        $this->addSql('DROP TABLE examen_tema');
        $this->addSql('ALTER TABLE pregunta DROP opcion_a, DROP opcion_b, DROP opcion_c, DROP opcion_d, DROP respuesta_correcta');
        $this->addSql('ALTER TABLE tema DROP ruta_pdf');
    }
}
