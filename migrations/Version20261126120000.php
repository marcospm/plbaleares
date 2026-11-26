<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite clasificar una flashcard creada a mano con una ley.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flashcard ADD ley_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE flashcard ADD CONSTRAINT FK_flashcard_ley FOREIGN KEY (ley_id) REFERENCES ley (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_flashcard_ley ON flashcard (ley_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flashcard DROP FOREIGN KEY FK_flashcard_ley');
        $this->addSql('DROP INDEX IDX_flashcard_ley ON flashcard');
        $this->addSql('ALTER TABLE flashcard DROP ley_id');
    }
}
