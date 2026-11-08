<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade los vídeos explicativos de una convocatoria';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE video_convocatoria (id INT AUTO_INCREMENT NOT NULL, convocatoria_id INT NOT NULL, creado_por_id INT NOT NULL, titulo VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, enlace_video LONGTEXT NOT NULL, orden INT DEFAULT 0 NOT NULL, fecha_creacion DATETIME NOT NULL, INDEX IDX_VIDEO_CONVOCATORIA_CONVOCATORIA (convocatoria_id), INDEX IDX_VIDEO_CONVOCATORIA_CREADO_POR (creado_por_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE video_convocatoria ADD CONSTRAINT FK_VIDEO_CONVOCATORIA_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_convocatoria ADD CONSTRAINT FK_VIDEO_CONVOCATORIA_CREADO_POR FOREIGN KEY (creado_por_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_convocatoria DROP FOREIGN KEY FK_VIDEO_CONVOCATORIA_CONVOCATORIA');
        $this->addSql('ALTER TABLE video_convocatoria DROP FOREIGN KEY FK_VIDEO_CONVOCATORIA_CREADO_POR');
        $this->addSql('DROP TABLE video_convocatoria');
    }
}
