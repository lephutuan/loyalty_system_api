<?php

declare(strict_types=1);

namespace App\State\Processor;

use App\Dto\CreateRedemptionInput;
use App\Dto\RedemptionOutput;
use App\Entity\Point;
use App\Entity\Redemption;
use App\Enum\RedemptionStatus;
use App\Repository\GiftRepository;
use App\Repository\MemberRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<CreateRedemptionInput, RedemptionOutput>
 */
final class CreateRedemptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MemberRepository $memberRepository,
        private readonly GiftRepository $giftRepository,
    ) {
    }

    /**
     * @param CreateRedemptionInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CreateRedemptionInput) {
            throw new BadRequestHttpException('Invalid redemption payload.');
        }

        $member = $this->memberRepository->findOneWithWallet($data->memberId);
        if ($member === null) {
            throw new NotFoundHttpException('Member not found.');
        }

        $gift = $this->giftRepository->find($data->giftId);
        if ($gift === null) {
            throw new NotFoundHttpException('Gift not found.');
        }

        if ($gift->isActive() === false) {
            throw new ConflictHttpException('Gift is not available.');
        }

        $wallet = $member->getWallet();

        $redemption = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($member, $gift, $wallet): Redemption {
            $entityManager->lock($wallet, LockMode::PESSIMISTIC_WRITE);
            $entityManager->lock($gift, LockMode::PESSIMISTIC_WRITE);

            if ($gift->getStock() <= 0) {
                throw new ConflictHttpException('Gift is out of stock.');
            }

            if ($wallet->canAfford($gift->getPointCost()) === false) {
                throw new ConflictHttpException('Insufficient points.');
            }

            $gift->reserveOne();
            $wallet->debit($gift->getPointCost());

            $redemption = new Redemption($member, $gift, $gift->getPointCost(), RedemptionStatus::Completed);
            $negativePoints = '-' . $gift->getPointCost();
            $point = new Point($wallet, $negativePoints, 'Redeem gift: ' . $gift->getGiftName(), null, $redemption);

            $entityManager->persist($redemption);
            $entityManager->persist($point);
            $entityManager->flush();

            return $redemption;
        });

        return new RedemptionOutput(
            redemptionId: (int) $redemption->getId(),
            memberId: (int) $member->getId(),
            giftId: (int) $gift->getId(),
            pointsUsed: $redemption->getPointsUsed(),
            status: $redemption->getStatus()->value,
            createdAt: $redemption->getCreatedAt()->format(DATE_ATOM),
        );
    }
}
