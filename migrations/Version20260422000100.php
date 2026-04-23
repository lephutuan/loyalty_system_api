<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optimistic lock version columns for wallets and gifts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets ADD version INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE gifts ADD version INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallets DROP version');
        $this->addSql('ALTER TABLE gifts DROP version');
    }
}
