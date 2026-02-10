<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppConfig>
 *
 * @method AppConfig|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppConfig|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppConfig[]    findAll()
 * @method AppConfig[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppConfig::class);
    }

    /**
     * Find multiple app configs by their names
     *
     * @param array $names Array of config names to find
     * @return array Array of AppConfig objects indexed by name
     */
    public function findByNames(array $names): array
    {
        $configs = $this->createQueryBuilder('c')
            ->where('c.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        // Index the results by name for easier access
        $result = [];
        foreach ($configs as $config) {
            $result[$config->getName()] = $config;
        }

        return $result;
    }

    public function save(AppConfig $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
