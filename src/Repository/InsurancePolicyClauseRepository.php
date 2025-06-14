<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InsurancePolicyClause;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InsurancePolicyClause>
 *
 * @method InsurancePolicyClause|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsurancePolicyClause|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsurancePolicyClause[]    findAll()
 * @method InsurancePolicyClause[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsurancePolicyClauseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsurancePolicyClause::class);
    }

    public function save(InsurancePolicyClause $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsurancePolicyClause $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
