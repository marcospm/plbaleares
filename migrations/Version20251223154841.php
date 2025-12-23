<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251223154841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE examen_semanal (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, fecha_apertura DATETIME NOT NULL, fecha_cierre DATETIME NOT NULL, dificultad VARCHAR(20) NOT NULL, activo TINYINT DEFAULT 1 NOT NULL, fecha_creacion DATETIME NOT NULL, creado_por_id INT NOT NULL, municipio_id INT DEFAULT NULL, INDEX IDX_23C65E20FE35D8C4 (creado_por_id), INDEX IDX_23C65E2058BC1BE0 (municipio_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen_semanal_tema (examen_semanal_id INT NOT NULL, tema_id INT NOT NULL, INDEX IDX_FD5E1F2583394AC (examen_semanal_id), INDEX IDX_FD5E1F25A64A8A17 (tema_id), PRIMARY KEY (examen_semanal_id, tema_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE examen_semanal_tema_municipal (examen_semanal_id INT NOT NULL, tema_municipal_id INT NOT NULL, INDEX IDX_7DDC709683394AC (examen_semanal_id), INDEX IDX_7DDC70961DFFCAC4 (tema_municipal_id), PRIMARY KEY (examen_semanal_id, tema_municipal_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE examen_semanal ADD CONSTRAINT FK_23C65E20FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE examen_semanal ADD CONSTRAINT FK_23C65E2058BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id)');
        $this->addSql('ALTER TABLE examen_semanal_tema ADD CONSTRAINT FK_FD5E1F2583394AC FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal_tema ADD CONSTRAINT FK_FD5E1F25A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal_tema_municipal ADD CONSTRAINT FK_7DDC709683394AC FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_semanal_tema_municipal ADD CONSTRAINT FK_7DDC70961DFFCAC4 FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen ADD examen_semanal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_514C8FEC83394AC FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id)');
        $this->addSql('CREATE INDEX IDX_514C8FEC83394AC ON examen (examen_semanal_id)');
        $this->addSql('ALTER TABLE notificacion ADD examen_semanal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19EC83394AC FOREIGN KEY (examen_semanal_id) REFERENCES examen_semanal (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_729A19EC83394AC ON notificacion (examen_semanal_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen_semanal DROP FOREIGN KEY FK_23C65E20FE35D8C4');
        $this->addSql('ALTER TABLE examen_semanal DROP FOREIGN KEY FK_23C65E2058BC1BE0');
        $this->addSql('ALTER TABLE examen_semanal_tema DROP FOREIGN KEY FK_FD5E1F2583394AC');
        $this->addSql('ALTER TABLE examen_semanal_tema DROP FOREIGN KEY FK_FD5E1F25A64A8A17');
        $this->addSql('ALTER TABLE examen_semanal_tema_municipal DROP FOREIGN KEY FK_7DDC709683394AC');
        $this->addSql('ALTER TABLE examen_semanal_tema_municipal DROP FOREIGN KEY FK_7DDC70961DFFCAC4');
        $this->addSql('DROP TABLE examen_semanal');
        $this->addSql('DROP TABLE examen_semanal_tema');
        $this->addSql('DROP TABLE examen_semanal_tema_municipal');
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_514C8FEC83394AC');
        $this->addSql('DROP INDEX IDX_514C8FEC83394AC ON examen');
        $this->addSql('ALTER TABLE examen DROP examen_semanal_id');
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19EC83394AC');
        $this->addSql('DROP INDEX IDX_729A19EC83394AC ON notificacion');
        $this->addSql('ALTER TABLE notificacion DROP examen_semanal_id');
    }
}
