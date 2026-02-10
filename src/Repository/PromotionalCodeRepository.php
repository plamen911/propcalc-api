<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PromotionalCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromotionalCode>
 *
 * @method PromotionalCode|null find($id, $lockMode = null, $lockVersion = null)
 * @method PromotionalCode|null findOneBy(array $criteria, array $orderBy = null)
 * @method PromotionalCode[]    findAll()
 * @method PromotionalCode[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PromotionalCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromotionalCode::class);
    }

    public function save(PromotionalCode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PromotionalCode $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
