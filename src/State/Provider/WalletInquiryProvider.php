<?php

declare(strict_types=1);

namespace App\State\Provider;

use App\Dto\PointHistoryItem;
use App\Dto\WalletInquiryOutput;
use App\Repository\MemberRepository;
use App\Repository\PointRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<WalletInquiryOutput>
 */
final class WalletInquiryProvider implements ProviderInterface
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly PointRepository $pointRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $memberIdRaw = $uriVariables['member_id'] ?? null;
        if (!is_scalar($memberIdRaw) || !is_numeric((string) $memberIdRaw)) {
            throw new NotFoundHttpException('Member not found.');
        }

        $memberId = (int) $memberIdRaw;
        $member = $this->memberRepository->findOneWithWallet($memberId);

        if ($member === null) {
            throw new NotFoundHttpException('Member not found.');
        }

        $wallet = $member->getWallet();
        $recentPoints = array_map(
            static function (array $row): PointHistoryItem {
                $createdAt = $row['createdAt'] ?? null;

                return new PointHistoryItem(
                    pointAmount: (string) ($row['pointAmount'] ?? '0.00'),
                    description: (string) ($row['description'] ?? ''),
                    transactionId: isset($row['transactionId']) ? (int) $row['transactionId'] : null,
                    redemptionId: isset($row['redemptionId']) ? (int) $row['redemptionId'] : null,
                    createdAt: $createdAt instanceof DateTimeInterface
                    ? $createdAt->format(DATE_ATOM)
                    : (new \DateTimeImmutable((string) $createdAt))->format(DATE_ATOM),
                );
            },
            $this->pointRepository->findLatestHistoryForWallet($wallet, 10),
        );

        return new WalletInquiryOutput(
            memberId: (int) $member->getId(),
            fullname: $member->getFullname(),
            email: $member->getEmail(),
            balance: $wallet->getBalance(),
            recentPoints: $recentPoints,
        );
    }
}
