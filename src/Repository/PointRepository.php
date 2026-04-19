<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Point;
use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Point>
 */
final class PointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Point::class);
    }

    /**
     * @return array<int, array{
     *     pointAmount: string,
     *     description: string,
     *     transactionId: int|null,
     *     redemptionId: int|null,
     *     createdAt: mixed
     * }>
     */
    public function findLatestHistoryForWallet(Wallet $wallet, int $limit = 10): array
    {
        return $this->createQueryBuilder('point')
            ->select(
                'point.pointAmount AS pointAmount',
                'point.description AS description',
                'IDENTITY(point.transaction) AS transactionId',
                'IDENTITY(point.redemption) AS redemptionId',
                'point.createdAt AS createdAt'
            )
            ->andWhere('point.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('point.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
}
