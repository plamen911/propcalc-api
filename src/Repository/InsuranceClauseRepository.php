<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InsuranceClause;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InsuranceClause>
 *
 * @method InsuranceClause|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsuranceClause|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsuranceClause[]    findAll()
 * @method InsuranceClause[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsuranceClauseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsuranceClause::class);
    }

    public function save(InsuranceClause $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsuranceClause $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
