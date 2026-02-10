<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InsurancePolicyPropertyChecklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InsurancePolicyPropertyChecklist>
 *
 * @method InsurancePolicyPropertyChecklist|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsurancePolicyPropertyChecklist|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsurancePolicyPropertyChecklist[]    findAll()
 * @method InsurancePolicyPropertyChecklist[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsurancePolicyPropertyChecklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsurancePolicyPropertyChecklist::class);
    }

    public function save(InsurancePolicyPropertyChecklist $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
