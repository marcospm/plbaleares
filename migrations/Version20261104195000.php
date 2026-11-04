<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite varios PDF de bases por convocatoria';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE convocatoria_base (id INT AUTO_INCREMENT NOT NULL, convocatoria_id INT NOT NULL, nombre_original VARCHAR(255) NOT NULL, ruta_archivo VARCHAR(500) NOT NULL, fecha_creacion DATETIME NOT NULL, INDEX IDX_CONVOCATORIA_BASE_CONVOCATORIA (convocatoria_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE convocatoria_base ADD CONSTRAINT FK_CONVOCATORIA_BASE_CONVOCATORIA FOREIGN KEY (convocatoria_id) REFERENCES convocatoria (id) ON DELETE CASCADE');

        $hayColumnaAntigua = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'convocatoria' AND COLUMN_NAME = 'ruta_bases_pdf'"
        );
        if ($hayColumnaAntigua > 0) {
            $this->addSql("INSERT INTO convocatoria_base (convocatoria_id, nombre_original, ruta_archivo, fecha_creacion) SELECT id, 'Bases.pdf', ruta_bases_pdf, NOW() FROM convocatoria WHERE ruta_bases_pdf IS NOT NULL AND ruta_bases_pdf <> ''");
            $this->addSql('ALTER TABLE convocatoria DROP ruta_bases_pdf');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE convocatoria ADD ruta_bases_pdf VARCHAR(500) DEFAULT NULL');
        $this->addSql('UPDATE convocatoria c INNER JOIN convocatoria_base b ON b.convocatoria_id = c.id INNER JOIN (SELECT convocatoria_id, MIN(id) AS id FROM convocatoria_base GROUP BY convocatoria_id) primero ON primero.id = b.id SET c.ruta_bases_pdf = b.ruta_archivo');
        $this->addSql('ALTER TABLE convocatoria_base DROP FOREIGN KEY FK_CONVOCATORIA_BASE_CONVOCATORIA');
        $this->addSql('DROP TABLE convocatoria_base');
    }
}
