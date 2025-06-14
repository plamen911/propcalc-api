<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IdNumberType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdNumberType>
 *
 * @method IdNumberType|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdNumberType|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdNumberType[]    findAll()
 * @method IdNumberType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdNumberTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdNumberType::class);
    }

    public function save(IdNumberType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(IdNumberType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
