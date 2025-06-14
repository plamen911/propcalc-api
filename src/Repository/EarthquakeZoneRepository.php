<?php

namespace App\Repository;

use App\Entity\EarthquakeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EarthquakeZone>
 *
 * @method EarthquakeZone|null find($id, $lockMode = null, $lockVersion = null)
 * @method EarthquakeZone|null findOneBy(array $criteria, array $orderBy = null)
 * @method EarthquakeZone[]    findAll()
 * @method EarthquakeZone[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EarthquakeZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EarthquakeZone::class);
    }

    public function save(EarthquakeZone $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EarthquakeZone $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
