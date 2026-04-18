<?php

declare(strict_types=1);

namespace App\State\Processor;

use App\Dto\CreateTransactionInput;
use App\Dto\TransactionOutput;
use App\Entity\Point;
use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use App\Repository\MemberRepository;
use App\Service\LoyaltyPointCalculator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<CreateTransactionInput, TransactionOutput>
 */
final class CreateTransactionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MemberRepository $memberRepository,
        private readonly LoyaltyPointCalculator $pointCalculator,
    ) {
    }

    /**
     * @param CreateTransactionInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CreateTransactionInput) {
            throw new BadRequestHttpException('Invalid transaction payload.');
        }

        if (bccomp($data->amount, '0.00', 2) <= 0) {
            throw new BadRequestHttpException('Amount must be greater than zero.');
        }

        $member = $this->memberRepository->findOneWithWallet($data->memberId);
        if ($member === null) {
            throw new NotFoundHttpException('Member not found.');
        }

        $wallet = $member->getWallet();
        $amount = number_format((float) $data->amount, 2, '.', '');

        $transaction = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($amount, $member, $wallet): Transaction {
            $entityManager->lock($wallet, LockMode::PESSIMISTIC_WRITE);

            $transaction = new Transaction($member, $amount, TransactionStatus::Completed);
            $earnedPoints = $this->pointCalculator->calculateEarnedPoints($amount);

            $wallet->credit($earnedPoints);

            $point = new Point($wallet, $earnedPoints, 'Earn points from transaction.', $transaction);

            $entityManager->persist($transaction);
            $entityManager->persist($point);
            $entityManager->flush();

            return $transaction;
        });

        $earnedPoints = $this->pointCalculator->calculateEarnedPoints($amount);

        return new TransactionOutput(
            transactionId: (int) $transaction->getId(),
            memberId: (int) $member->getId(),
            amount: $transaction->getAmount(),
            status: $transaction->getStatus()->value,
            pointsEarned: $earnedPoints,
            createdAt: $transaction->getCreatedAt()->format(DATE_ATOM),
        );
    }
}
