<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añadir relación ManyToOne entre Examen y Convocatoria';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen ADD convocatoria_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen ADD CONSTRAINT FK_EXAMEN_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_EXAMEN_CONVOCATORIA ON examen (convocatoria_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen DROP FOREIGN KEY FK_EXAMEN_CONVOCATORIA');
        $this->addSql('DROP INDEX IDX_EXAMEN_CONVOCATORIA ON examen');
        $this->addSql('ALTER TABLE examen DROP convocatoria_id');
    }
}

