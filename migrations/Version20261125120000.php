<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea la tabla de flashcards del alumno, agrupadas por tema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE flashcard (
            id INT AUTO_INCREMENT NOT NULL,
            usuario_id INT NOT NULL,
            tema_id INT DEFAULT NULL,
            tema_municipal_id INT DEFAULT NULL,
            pregunta_id INT DEFAULT NULL,
            pregunta_municipal_id INT DEFAULT NULL,
            anverso LONGTEXT NOT NULL,
            reverso LONGTEXT NOT NULL,
            creado_en DATETIME NOT NULL,
            actualizado_en DATETIME NOT NULL,
            UNIQUE INDEX uniq_flashcard_usuario_pregunta (usuario_id, pregunta_id),
            UNIQUE INDEX uniq_flashcard_usuario_pregunta_municipal (usuario_id, pregunta_municipal_id),
            INDEX IDX_flashcard_tema (tema_id),
            INDEX IDX_flashcard_tema_municipal (tema_municipal_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE flashcard ADD CONSTRAINT FK_flashcard_usuario FOREIGN KEY (usuario_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flashcard ADD CONSTRAINT FK_flashcard_tema FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE flashcard ADD CONSTRAINT FK_flashcard_tema_municipal FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE flashcard ADD CONSTRAINT FK_flashcard_pregunta FOREIGN KEY (pregunta_id) REFERENCES pregunta (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE flashcard ADD CONSTRAINT FK_flashcard_pregunta_municipal FOREIGN KEY (pregunta_municipal_id) REFERENCES pregunta_municipal (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flashcard DROP FOREIGN KEY FK_flashcard_usuario');
        $this->addSql('ALTER TABLE flashcard DROP FOREIGN KEY FK_flashcard_tema');
        $this->addSql('ALTER TABLE flashcard DROP FOREIGN KEY FK_flashcard_tema_municipal');
        $this->addSql('ALTER TABLE flashcard DROP FOREIGN KEY FK_flashcard_pregunta');
        $this->addSql('ALTER TABLE flashcard DROP FOREIGN KEY FK_flashcard_pregunta_municipal');
        $this->addSql('DROP TABLE flashcard');
    }
}
