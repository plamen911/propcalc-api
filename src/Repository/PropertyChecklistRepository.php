<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PropertyChecklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropertyChecklist>
 *
 * @method PropertyChecklist|null find($id, $lockMode = null, $lockVersion = null)
 * @method PropertyChecklist|null findOneBy(array $criteria, array $orderBy = null)
 * @method PropertyChecklist[]    findAll()
 * @method PropertyChecklist[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PropertyChecklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyChecklist::class);
    }
}
