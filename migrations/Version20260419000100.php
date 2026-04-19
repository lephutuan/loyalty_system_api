<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index for wallet point history queries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE points ADD INDEX idx_points_wallet_created_at (wallet_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE points DROP INDEX idx_points_wallet_created_at');
    }
}