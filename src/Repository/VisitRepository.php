<?php

namespace App\Repository;

use App\Entity\Visit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visit::class);
    }

    public function getTotalVisits(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTodayVisits(\DateTimeImmutable $todayStart, \DateTimeImmutable $todayEnd): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.visitedAt >= :from')
            ->andWhere('v.visitedAt < :to')
            ->setParameter('from', $todayStart)
            ->setParameter('to', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTopRoutes(int $limit = 5): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.routeName AS route, v.path AS path, COUNT(v.id) AS hits')
            ->groupBy('v.routeName, v.path')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTopBrowsers(int $limit = 5): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.browser AS browser, COUNT(v.id) AS hits')
            ->groupBy('v.browser')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
}
