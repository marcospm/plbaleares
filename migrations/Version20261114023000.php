<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114023000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mueve la ruta pública de los archivos de psicotécnicos para que no coincida con la página del alumno.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE archivo_psicotecnico SET ruta_archivo = REPLACE(ruta_archivo, '/psicotecnicos/', '/archivos_psicotecnicos/') WHERE ruta_archivo LIKE '/psicotecnicos/%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE archivo_psicotecnico SET ruta_archivo = REPLACE(ruta_archivo, '/archivos_psicotecnicos/', '/psicotecnicos/') WHERE ruta_archivo LIKE '/archivos_psicotecnicos/%'");
    }
}
