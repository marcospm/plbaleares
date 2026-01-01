<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101140740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla documento_convocatoria para almacenar documentos de convocatorias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE documento_convocatoria (
            id INT AUTO_INCREMENT NOT NULL,
            convocatoria_id INT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            ruta_archivo VARCHAR(500) NOT NULL,
            fecha_subida DATETIME NOT NULL,
            INDEX IDX_DOC_CONVOCATORIA (convocatoria_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE documento_convocatoria ADD CONSTRAINT FK_DOC_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documento_convocatoria DROP FOREIGN KEY FK_DOC_CONVOCATORIA');
        $this->addSql('DROP TABLE documento_convocatoria');
    }
}
