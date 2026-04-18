<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Gift;
use App\Entity\Member;
use App\Entity\Point;
use App\Entity\Redemption;
use App\Entity\Transaction;
use App\Enum\GiftStatus;
use App\Enum\RedemptionStatus;
use App\Enum\TransactionStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:loyalty', description: 'Seed demo data for loyalty system (members, wallets, gifts, transactions, points, redemptions).')]
final class SeedLoyaltyDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Truncate seeded tables before inserting fresh data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $shouldReset = (bool) $input->getOption('reset');
        $connection = $this->entityManager->getConnection();

        $counts = $this->getTableCounts($connection);
        $hasExistingData = array_sum($counts) > 0;

        if ($hasExistingData && !$shouldReset) {
            $io->warning('Database already contains data. Re-run with --reset to seed a clean dataset.');
            $io->table(['Table', 'Rows'], $this->tableRowsForOutput($counts));

            return Command::INVALID;
        }

        if ($shouldReset) {
            $this->truncateSeedTables($connection);
            $this->entityManager->clear();
            $io->text('Existing data has been truncated.');
        }

        [$memberA, $memberB, $memberC] = $this->createMembers();
        [$giftA, $giftB, $giftC] = $this->createGifts();

        $this->createEarnTransaction($memberA, '100000.00', '1000.00', 'Seed: earn points from purchase.');
        $this->createEarnTransaction($memberB, '50000.00', '500.00', 'Seed: earn points from purchase.');

        $this->createCompletedRedemption($memberA, $giftA, '500.00');

        // Keep this member with empty activity to test wallet inquiry for a new account.
        $memberC->getWallet();

        $this->entityManager->flush();

        $io->success('Seed data inserted successfully.');
        $io->table(
            ['Entity', 'Value'],
            [
                ['Member A', sprintf('%d - %s', (int) $memberA->getId(), $memberA->getEmail())],
                ['Member B', sprintf('%d - %s', (int) $memberB->getId(), $memberB->getEmail())],
                ['Member C', sprintf('%d - %s', (int) $memberC->getId(), $memberC->getEmail())],
                ['Gift A', sprintf('%d - %s (stock: %d)', (int) $giftA->getId(), $giftA->getGiftName(), $giftA->getStock())],
                ['Gift B', sprintf('%d - %s (stock: %d)', (int) $giftB->getId(), $giftB->getGiftName(), $giftB->getStock())],
                ['Gift C', sprintf('%d - %s (stock: %d)', (int) $giftC->getId(), $giftC->getGiftName(), $giftC->getStock())],
                ['Member A wallet balance', $memberA->getWallet()->getBalance()],
                ['Member B wallet balance', $memberB->getWallet()->getBalance()],
                ['Member C wallet balance', $memberC->getWallet()->getBalance()],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * @return array{Member, Member, Member}
     */
    private function createMembers(): array
    {
        $memberA = Member::register('Nguyen Van A', 'member.a@example.com');
        $memberB = Member::register('Nguyen Van B', 'member.b@example.com');
        $memberC = Member::register('Nguyen Van C', 'member.c@example.com');

        $this->entityManager->persist($memberA);
        $this->entityManager->persist($memberB);
        $this->entityManager->persist($memberC);

        return [$memberA, $memberB, $memberC];
    }

    /**
     * @return array{Gift, Gift, Gift}
     */
    private function createGifts(): array
    {
        $giftA = new Gift('Voucher 50K', '500.00', 10, GiftStatus::Active);
        $giftB = new Gift('Bluetooth Earbuds', '1200.00', 5, GiftStatus::Active);
        $giftC = new Gift('Tote Bag Limited', '300.00', 0, GiftStatus::Inactive);

        $this->entityManager->persist($giftA);
        $this->entityManager->persist($giftB);
        $this->entityManager->persist($giftC);

        return [$giftA, $giftB, $giftC];
    }

    private function createEarnTransaction(Member $member, string $amount, string $pointsEarned, string $description): void
    {
        $wallet = $member->getWallet();
        $wallet->credit($pointsEarned);

        $transaction = new Transaction($member, $amount, TransactionStatus::Completed);
        $point = new Point($wallet, $pointsEarned, $description, $transaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($point);
    }

    private function createCompletedRedemption(Member $member, Gift $gift, string $pointsUsed): void
    {
        $wallet = $member->getWallet();

        $gift->reserveOne();
        $wallet->debit($pointsUsed);

        $redemption = new Redemption($member, $gift, $pointsUsed, RedemptionStatus::Completed);
        $point = new Point($wallet, '-' . $pointsUsed, 'Seed: redeem gift.', null, $redemption);

        $this->entityManager->persist($redemption);
        $this->entityManager->persist($point);
    }

    /**
     * @return array<string, int>
     */
    private function getTableCounts(Connection $connection): array
    {
        return [
            'members' => (int) $connection->fetchOne('SELECT COUNT(*) FROM members'),
            'wallets' => (int) $connection->fetchOne('SELECT COUNT(*) FROM wallets'),
            'transactions' => (int) $connection->fetchOne('SELECT COUNT(*) FROM transactions'),
            'points' => (int) $connection->fetchOne('SELECT COUNT(*) FROM points'),
            'gifts' => (int) $connection->fetchOne('SELECT COUNT(*) FROM gifts'),
            'redemptions' => (int) $connection->fetchOne('SELECT COUNT(*) FROM redemptions'),
        ];
    }

    /**
     * @param array<string, int> $counts
     *
     * @return array<int, array<int, string>>
     */
    private function tableRowsForOutput(array $counts): array
    {
        $rows = [];

        foreach ($counts as $table => $count) {
            $rows[] = [$table, (string) $count];
        }

        return $rows;
    }

    private function truncateSeedTables(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $connection->executeStatement('TRUNCATE TABLE points');
        $connection->executeStatement('TRUNCATE TABLE redemptions');
        $connection->executeStatement('TRUNCATE TABLE transactions');
        $connection->executeStatement('TRUNCATE TABLE wallets');
        $connection->executeStatement('TRUNCATE TABLE gifts');
        $connection->executeStatement('TRUNCATE TABLE members');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }
}
