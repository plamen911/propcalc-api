<?php

namespace App\Repository;

use App\Entity\WaterDistance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaterDistance>
 *
 * @method WaterDistance|null find($id, $lockMode = null, $lockVersion = null)
 * @method WaterDistance|null findOneBy(array $criteria, array $orderBy = null)
 * @method WaterDistance[]    findAll()
 * @method WaterDistance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WaterDistanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaterDistance::class);
    }

    public function save(WaterDistance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WaterDistance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
