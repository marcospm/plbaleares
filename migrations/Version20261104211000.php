<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104211000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade la fecha de fin de inscripción a las convocatorias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE convocatoria ADD fecha_inscripcion DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE convocatoria DROP fecha_inscripcion');
    }
}
