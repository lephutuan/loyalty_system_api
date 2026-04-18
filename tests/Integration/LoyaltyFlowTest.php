<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Gift;
use App\Entity\Member;
use App\Enum\GiftStatus;
use PHPUnit\Framework\TestCase;

final class LoyaltyFlowTest extends TestCase
{
    public function testMemberWalletIsCreatedWithMember(): void
    {
        $member = Member::register('John Doe', 'john@example.com');

        self::assertSame('John Doe', $member->getFullname());
        self::assertSame('john@example.com', $member->getEmail());
        self::assertNotNull($member->getWallet());
        self::assertSame('0.00', $member->getWallet()->getBalance());
    }

    public function testGiftFactoryPersistsStock(): void
    {
        $gift = new Gift(
            giftName: 'Coffee Mug',
            pointCost: '500.00',
            stock: 3,
            status: GiftStatus::Active,
        );

        self::assertSame('Coffee Mug', $gift->getGiftName());
        self::assertSame('500.00', $gift->getPointCost());
        self::assertSame(3, $gift->getStock());
        self::assertTrue($gift->isActive());
    }
}
