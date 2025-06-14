<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PersonRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonRole>
 *
 * @method PersonRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method PersonRole|null findOneBy(array $criteria, array $orderBy = null)
 * @method PersonRole[]    findAll()
 * @method PersonRole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonRole::class);
    }

    public function save(PersonRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PersonRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
