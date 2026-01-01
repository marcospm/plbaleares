<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101103533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cambiar relación de ExamenPDF con Tema de ManyToOne a ManyToMany';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla de unión para la relación ManyToMany
        $this->addSql('CREATE TABLE examen_pdf_tema (
            examen_pdf_id INT NOT NULL,
            tema_id INT NOT NULL,
            INDEX IDX_EXAMEN_PDF_TEMA_EXAMEN (examen_pdf_id),
            INDEX IDX_EXAMEN_PDF_TEMA_TEMA (tema_id),
            PRIMARY KEY(examen_pdf_id, tema_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE examen_pdf_tema ADD CONSTRAINT FK_EXAMEN_PDF_TEMA_EXAMEN FOREIGN KEY (examen_pdf_id) REFERENCES examen_pdf (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE examen_pdf_tema ADD CONSTRAINT FK_EXAMEN_PDF_TEMA_TEMA FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE');
        
        // Migrar datos existentes de tema_id a la nueva tabla
        $this->addSql('INSERT INTO examen_pdf_tema (examen_pdf_id, tema_id)
            SELECT id, tema_id FROM examen_pdf WHERE tema_id IS NOT NULL');
        
        // Eliminar la columna tema_id de examen_pdf
        $this->addSql('ALTER TABLE examen_pdf DROP FOREIGN KEY FK_75FB0120A64A8A17');
        $this->addSql('ALTER TABLE examen_pdf DROP COLUMN tema_id');
    }

    public function down(Schema $schema): void
    {
        // Agregar columna tema_id de vuelta
        $this->addSql('ALTER TABLE examen_pdf ADD tema_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen_pdf ADD CONSTRAINT FK_75FB0120A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id)');
        
        // Migrar datos de vuelta (solo el primer tema de cada examen)
        $this->addSql('UPDATE examen_pdf ep
            INNER JOIN (
                SELECT examen_pdf_id, MIN(tema_id) as tema_id
                FROM examen_pdf_tema
                GROUP BY examen_pdf_id
            ) ept ON ep.id = ept.examen_pdf_id
            SET ep.tema_id = ept.tema_id');
        
        // Eliminar tabla de unión
        $this->addSql('ALTER TABLE examen_pdf_tema DROP FOREIGN KEY FK_EXAMEN_PDF_TEMA_EXAMEN');
        $this->addSql('ALTER TABLE examen_pdf_tema DROP FOREIGN KEY FK_EXAMEN_PDF_TEMA_TEMA');
        $this->addSql('DROP TABLE examen_pdf_tema');
    }
}
