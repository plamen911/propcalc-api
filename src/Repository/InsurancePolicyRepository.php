<?php

namespace App\Repository;

use App\Entity\InsurancePolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InsurancePolicy>
 *
 * @method InsurancePolicy|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsurancePolicy|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsurancePolicy[]    findAll()
 * @method InsurancePolicy[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsurancePolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsurancePolicy::class);
    }

    public function save(InsurancePolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsurancePolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Count insurance policies created on a specific date
     *
     * @param \DateTime $date
     * @return int
     */
    public function countPoliciesForDate(\DateTime $date): int
    {
        $startDate = clone $date;
        $startDate->setTime(0, 0, 0);

        $endDate = clone $date;
        $endDate->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
