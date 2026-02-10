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
}
