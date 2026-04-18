<?php

declare(strict_types=1);

namespace App\State\Provider;

use App\Dto\PointHistoryItem;
use App\Dto\WalletInquiryOutput;
use App\Entity\Point;
use App\Repository\MemberRepository;
use App\Repository\PointRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
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
            static fn (Point $point): PointHistoryItem => new PointHistoryItem(
                pointAmount: $point->getPointAmount(),
                description: $point->getDescription(),
                transactionId: $point->getTransaction()?->getId(),
                redemptionId: $point->getRedemption()?->getId(),
                createdAt: $point->getCreatedAt()->format(DATE_ATOM),
            ),
            $this->pointRepository->findLatestForWallet($wallet, 10),
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
