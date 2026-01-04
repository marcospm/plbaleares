<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Elimina la tabla user_municipio ya que los alumnos ahora se asignan a través de convocatorias
 */
final class Version20260103150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina la tabla user_municipio - los alumnos ahora se asignan a través de convocatorias';
    }

    public function up(Schema $schema): void
    {
        // Desactivar verificación de foreign keys temporalmente
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        
        // Eliminar foreign keys primero
        // Nota: Si alguna foreign key no existe, el comando fallará pero continuará con la siguiente
        $this->addSql('ALTER TABLE user_municipio DROP FOREIGN KEY FK_FE78780458BC1BE0');
        $this->addSql('ALTER TABLE user_municipio DROP FOREIGN KEY FK_FE787804A76ED395');
        
        // Eliminar la tabla
        $this->addSql('DROP TABLE IF EXISTS user_municipio');
        
        // Reactivar verificación de foreign keys
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        // Recrear la tabla si es necesario hacer rollback
        $this->addSql('CREATE TABLE user_municipio (municipio_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_FE78780458BC1BE0 (municipio_id), INDEX IDX_FE787804A76ED395 (user_id), PRIMARY KEY (municipio_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_municipio ADD CONSTRAINT FK_FE78780458BC1BE0 FOREIGN KEY (municipio_id) REFERENCES municipio (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_municipio ADD CONSTRAINT FK_FE787804A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}

