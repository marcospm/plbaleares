<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Eliminar tabla documento_convocatoria y su relación';
    }

    public function up(Schema $schema): void
    {
        // Verificar si la tabla existe antes de eliminarla
        $tableExists = $this->connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documento_convocatoria'"
        )->fetchOne();
        
        if ($tableExists > 0) {
            // Eliminar foreign key
            $this->addSql('ALTER TABLE documento_convocatoria DROP FOREIGN KEY FK_DOC_CONVOCATORIA');
            
            // Eliminar la tabla
            $this->addSql('DROP TABLE documento_convocatoria');
        }
    }

    public function down(Schema $schema): void
    {
        // Recrear la tabla si es necesario (solo para rollback)
        $this->addSql('CREATE TABLE documento_convocatoria (id INT AUTO_INCREMENT NOT NULL, convocatoria_id INT NOT NULL, nombre VARCHAR(255) NOT NULL, ruta_archivo VARCHAR(500) NOT NULL, INDEX IDX_DOC_CONVOCATORIA (convocatoria_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE documento_convocatoria ADD CONSTRAINT FK_DOC_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');
    }
}

