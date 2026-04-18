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
     * @return array<int, Point>
     */
    public function findLatestForWallet(Wallet $wallet, int $limit = 10): array
    {
        $result = (array) $this->createQueryBuilder('point')
            ->andWhere('point.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('point.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $filtered = [];
        foreach ($result as $item) {
            if ($item instanceof Point) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }
}
