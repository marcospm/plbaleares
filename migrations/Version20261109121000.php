<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite al admin activar o desactivar los niveles de dificultad de los exámenes. Empiezan los tres activos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE configuracion_dificultad_examen (id INT AUTO_INCREMENT NOT NULL, facil TINYINT(1) DEFAULT 1 NOT NULL, moderada TINYINT(1) DEFAULT 1 NOT NULL, dificil TINYINT(1) DEFAULT 1 NOT NULL, actualizado_en DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('INSERT INTO configuracion_dificultad_examen (facil, moderada, dificil) VALUES (1, 1, 1)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE configuracion_dificultad_examen');
    }
}
