<?php

declare(strict_types=1);

namespace App\State\Processor;

use App\Dto\CreateRedemptionInput;
use App\Dto\RedemptionOutput;
use App\Entity\Gift;
use App\Entity\Point;
use App\Entity\Redemption;
use App\Entity\Wallet;
use App\Enum\RedemptionStatus;
use App\Repository\GiftRepository;
use App\Repository\MemberRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
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

        try {
            $redemption = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($member, $gift, $wallet): Redemption {
                $lockedWallet = $entityManager->find(Wallet::class, $wallet->getId(), LockMode::PESSIMISTIC_WRITE);
                if (!$lockedWallet instanceof Wallet) {
                    throw new NotFoundHttpException('Wallet not found.');
                }

                $lockedGift = $entityManager->find(Gift::class, $gift->getId(), LockMode::PESSIMISTIC_WRITE);
                if (!$lockedGift instanceof Gift) {
                    throw new NotFoundHttpException('Gift not found.');
                }

                if ($lockedGift->getStock() <= 0) {
                    throw new ConflictHttpException('Gift is out of stock.');
                }

                if ($lockedWallet->canAfford($lockedGift->getPointCost()) === false) {
                    throw new ConflictHttpException('Insufficient points.');
                }

                $lockedGift->reserveOne();
                $lockedWallet->debit($lockedGift->getPointCost());

                $redemption = new Redemption($member, $lockedGift, $lockedGift->getPointCost(), RedemptionStatus::Completed);
                $negativePoints = '-' . $lockedGift->getPointCost();
                $point = new Point($lockedWallet, $negativePoints, 'Redeem gift: ' . $lockedGift->getGiftName(), null, $redemption);

                $entityManager->persist($redemption);
                $entityManager->persist($point);
                $entityManager->flush();

                return $redemption;
            });
        } catch (OptimisticLockException $exception) {
            throw new ConflictHttpException('Concurrent update detected. Please retry.', $exception);
        }

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
