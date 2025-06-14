<?php

namespace App\Repository;

use App\Entity\TariffPreset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TariffPreset>
 *
 * @method TariffPreset|null find($id, $lockMode = null, $lockVersion = null)
 * @method TariffPreset|null findOneBy(array $criteria, array $orderBy = null)
 * @method TariffPreset[]    findAll()
 * @method TariffPreset[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TariffPresetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TariffPreset::class);
    }

    public function save(TariffPreset $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TariffPreset $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
