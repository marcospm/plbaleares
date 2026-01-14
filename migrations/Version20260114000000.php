<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración para crear la tabla partida_juego
 * 
 * Esta migración crea la tabla para guardar las partidas de los juegos de gamificación
 */
final class Version20260114000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla partida_juego para registrar partidas de juegos de gamificación';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla partida_juego
        $this->addSql('CREATE TABLE partida_juego (
            id INT AUTO_INCREMENT NOT NULL,
            usuario_id INT NOT NULL,
            tipo_juego VARCHAR(50) NOT NULL,
            fecha_creacion DATETIME NOT NULL,
            INDEX IDX_PARTIDA_JUEGO_USUARIO_TIPO (usuario_id, tipo_juego),
            INDEX IDX_PARTIDA_JUEGO_TIPO (tipo_juego),
            INDEX IDX_PARTIDA_JUEGO_FECHA (fecha_creacion),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Agregar foreign key
        $this->addSql('ALTER TABLE partida_juego 
            ADD CONSTRAINT FK_PARTIDA_JUEGO_USUARIO 
            FOREIGN KEY (usuario_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Eliminar foreign key
        $this->addSql('ALTER TABLE partida_juego DROP FOREIGN KEY FK_PARTIDA_JUEGO_USUARIO');
        
        // Eliminar tabla
        $this->addSql('DROP TABLE partida_juego');
    }
}
