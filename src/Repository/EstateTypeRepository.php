<?php

namespace App\Repository;

use App\Entity\EstateType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EstateType>
 *
 * @method EstateType|null find($id, $lockMode = null, $lockVersion = null)
 * @method EstateType|null findOneBy(array $criteria, array $orderBy = null)
 * @method EstateType[]    findAll()
 * @method EstateType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstateTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EstateType::class);
    }
}
