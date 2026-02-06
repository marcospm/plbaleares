<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add eliminado field to user table for logical deletion
 */
final class Version20250202000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add eliminado field to user table for logical deletion (soft delete)';
    }

    public function up(Schema $schema): void
    {
        // Add eliminado column to user table
        $this->addSql('ALTER TABLE user ADD eliminado TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove eliminado column from user table
        $this->addSql('ALTER TABLE user DROP eliminado');
    }
}
