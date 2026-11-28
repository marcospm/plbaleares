<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registra cada vez que un alumno abre un archivo de psicotécnicos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE apertura_archivo_psicotecnico (id INT AUTO_INCREMENT NOT NULL, archivo_id INT NOT NULL, usuario_id INT NOT NULL, fecha DATETIME NOT NULL, INDEX IDX_APERTURA_PSICO_ARCHIVO (archivo_id), INDEX IDX_APERTURA_PSICO_USUARIO (usuario_id), INDEX idx_apertura_psico_fecha (fecha), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE apertura_archivo_psicotecnico ADD CONSTRAINT FK_APERTURA_PSICO_ARCHIVO FOREIGN KEY (archivo_id) REFERENCES archivo_psicotecnico (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE apertura_archivo_psicotecnico ADD CONSTRAINT FK_APERTURA_PSICO_USUARIO FOREIGN KEY (usuario_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE apertura_archivo_psicotecnico DROP FOREIGN KEY FK_APERTURA_PSICO_ARCHIVO');
        $this->addSql('ALTER TABLE apertura_archivo_psicotecnico DROP FOREIGN KEY FK_APERTURA_PSICO_USUARIO');
        $this->addSql('DROP TABLE apertura_archivo_psicotecnico');
    }
}
