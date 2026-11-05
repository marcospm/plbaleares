<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite asignar recursos específicos a convocatorias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recurso_especifico_convocatoria (recurso_especifico_id INT NOT NULL, convocatoria_id INT NOT NULL, INDEX IDX_RECURSO_ESPECIFICO_CONVOCATORIA_RECURSO (recurso_especifico_id), INDEX IDX_RECURSO_ESPECIFICO_CONVOCATORIA_CONVOCATORIA (convocatoria_id), PRIMARY KEY (recurso_especifico_id, convocatoria_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE recurso_especifico_convocatoria ADD CONSTRAINT FK_RECURSO_ESPECIFICO_CONVOCATORIA_RECURSO FOREIGN KEY (recurso_especifico_id) REFERENCES recurso_especifico (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recurso_especifico_convocatoria ADD CONSTRAINT FK_RECURSO_ESPECIFICO_CONVOCATORIA_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recurso_especifico_convocatoria DROP FOREIGN KEY FK_RECURSO_ESPECIFICO_CONVOCATORIA_RECURSO');
        $this->addSql('ALTER TABLE recurso_especifico_convocatoria DROP FOREIGN KEY FK_RECURSO_ESPECIFICO_CONVOCATORIA_CONVOCATORIA');
        $this->addSql('DROP TABLE recurso_especifico_convocatoria');
    }
}
