<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wallet>
 */
final class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    public function findOneByMemberId(int $memberId): ?Wallet
    {
        $result = $this->createQueryBuilder('w')
            ->innerJoin('w.member', 'm')
            ->addSelect('m')
            ->andWhere('m.id = :memberId')
            ->setParameter('memberId', $memberId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Wallet ? $result : null;
    }
}
