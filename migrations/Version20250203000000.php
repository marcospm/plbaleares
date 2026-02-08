<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add mensaje table for chat system
 */
final class Version20250203000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mensaje table for asynchronous chat system between users';
    }

    public function up(Schema $schema): void
    {
        // Create mensaje table
        $this->addSql('CREATE TABLE mensaje (
            id INT AUTO_INCREMENT NOT NULL,
            remitente_id INT NOT NULL,
            destinatario_id INT NOT NULL,
            contenido LONGTEXT NOT NULL,
            fecha_envio DATETIME NOT NULL,
            leido TINYINT(1) DEFAULT 0 NOT NULL,
            fecha_lectura DATETIME DEFAULT NULL,
            INDEX IDX_mensaje_remitente (remitente_id),
            INDEX IDX_mensaje_destinatario (destinatario_id),
            INDEX IDX_mensaje_leido (leido),
            INDEX IDX_mensaje_fecha_envio (fecha_envio),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign keys
        $this->addSql('ALTER TABLE mensaje ADD CONSTRAINT FK_mensaje_remitente FOREIGN KEY (remitente_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE mensaje ADD CONSTRAINT FK_mensaje_destinatario FOREIGN KEY (destinatario_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys
        $this->addSql('ALTER TABLE mensaje DROP FOREIGN KEY FK_mensaje_remitente');
        $this->addSql('ALTER TABLE mensaje DROP FOREIGN KEY FK_mensaje_destinatario');
        
        // Drop table
        $this->addSql('DROP TABLE mensaje');
    }
}
