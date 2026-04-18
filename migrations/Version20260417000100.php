<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create loyalty system tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE members (id INT AUTO_INCREMENT NOT NULL, fullname VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_33D1C7E792E7C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallets (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, balance NUMERIC(15, 2) NOT NULL DEFAULT \'0.00\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_72E90EFD1F9D217A (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, amount NUMERIC(15, 2) NOT NULL, status VARCHAR(32) NOT NULL COMMENT \'(DC2Type:string)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1C83F54A1F9D217A (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE points (id INT AUTO_INCREMENT NOT NULL, wallet_id INT NOT NULL, transaction_id INT DEFAULT NULL, redemption_id INT DEFAULT NULL, point_amount NUMERIC(15, 2) NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F97A50B6676B531B (wallet_id), INDEX IDX_F97A50B59E38E4E (transaction_id), INDEX IDX_F97A50B53EEB65A (redemption_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gifts (id INT AUTO_INCREMENT NOT NULL, gift_name VARCHAR(255) NOT NULL, point_cost NUMERIC(15, 2) NOT NULL, stock INT NOT NULL, status VARCHAR(32) NOT NULL COMMENT \'(DC2Type:string)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE redemptions (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, gift_id INT NOT NULL, points_used NUMERIC(15, 2) NOT NULL, status VARCHAR(32) NOT NULL COMMENT \'(DC2Type:string)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9DA94A511F9D217A (member_id), INDEX IDX_9DA94A514592A0B (gift_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_72E90EFD1F9D217A FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_1C83F54A1F9D217A FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE points ADD CONSTRAINT FK_F97A50B6676B531B FOREIGN KEY (wallet_id) REFERENCES wallets (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE points ADD CONSTRAINT FK_F97A50B59E38E4E FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE points ADD CONSTRAINT FK_F97A50B53EEB65A FOREIGN KEY (redemption_id) REFERENCES redemptions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE redemptions ADD CONSTRAINT FK_9DA94A511F9D217A FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE redemptions ADD CONSTRAINT FK_9DA94A514592A0B FOREIGN KEY (gift_id) REFERENCES gifts (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE points');
        $this->addSql('DROP TABLE redemptions');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE wallets');
        $this->addSql('DROP TABLE gifts');
        $this->addSql('DROP TABLE members');
    }
}
