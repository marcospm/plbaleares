<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add ultimoLogin field to user table
 */
final class Version20250127000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ultimoLogin field to user table to track last login date';
    }

    public function up(Schema $schema): void
    {
        // Add ultimo_login column to user table
        $this->addSql('ALTER TABLE user ADD ultimo_login DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove ultimo_login column from user table
        $this->addSql('ALTER TABLE user DROP ultimo_login');
    }
}
