<?php

namespace App\Repository;

use App\Entity\TariffPresetClause;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TariffPresetClause>
 *
 * @method TariffPresetClause|null find($id, $lockMode = null, $lockVersion = null)
 * @method TariffPresetClause|null findOneBy(array $criteria, array $orderBy = null)
 * @method TariffPresetClause[]    findAll()
 * @method TariffPresetClause[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TariffPresetClauseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TariffPresetClause::class);
    }

    /**
     * Find all clauses for multiple tariff presets with their insurance clauses
     *
     * @param array $tariffPresets Array of TariffPreset objects
     * @return array Array of TariffPresetClause objects indexed by tariff preset ID
     */
    public function findByTariffPresetsWithInsuranceClauses(array $tariffPresets): array
    {
        $presetIds = array_map(function($preset) {
            return $preset->getId();
        }, $tariffPresets);

        if (empty($presetIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('tpc')
            ->addSelect('tp', 'ic')
            ->leftJoin('tpc.tariffPreset', 'tp')
            ->leftJoin('tpc.insuranceClause', 'ic')
            ->where('tpc.tariffPreset IN (:presetIds)')
            ->andWhere('ic.active = :active')
            ->setParameter('presetIds', $presetIds)
            ->setParameter('active', true)
            ->orderBy('tpc.position', 'ASC');

        $clauses = $qb->getQuery()->getResult();

        // Group clauses by tariff preset ID
        $result = [];
        foreach ($clauses as $clause) {
            $presetId = $clause->getTariffPreset()->getId();
            if (!isset($result[$presetId])) {
                $result[$presetId] = [];
            }
            $result[$presetId][] = $clause;
        }

        return $result;
    }

    public function save(TariffPresetClause $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TariffPresetClause $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all tariff preset clauses with active insurance clauses
     *
     * @return array Array of TariffPresetClause objects
     */
    public function findAllWithActiveInsuranceClauses(): array
    {
        $qb = $this->createQueryBuilder('tpc')
            ->addSelect('tp', 'ic')
            ->leftJoin('tpc.tariffPreset', 'tp')
            ->leftJoin('tpc.insuranceClause', 'ic')
            ->where('ic.active = :active')
            ->setParameter('active', true)
            ->orderBy('tpc.position', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find tariff preset clauses by tariff preset with active insurance clauses
     *
     * @param TariffPreset $tariffPreset The tariff preset to find clauses for
     * @param array $orderBy Optional ordering criteria
     * @return array Array of TariffPresetClause objects
     */
    public function findByTariffPresetWithActiveInsuranceClauses($tariffPreset, array $orderBy = ['position' => 'ASC']): array
    {
        $qb = $this->createQueryBuilder('tpc')
            ->addSelect('tp', 'ic')
            ->leftJoin('tpc.tariffPreset', 'tp')
            ->leftJoin('tpc.insuranceClause', 'ic')
            ->where('tpc.tariffPreset = :tariffPreset')
            ->andWhere('ic.active = :active')
            ->setParameter('tariffPreset', $tariffPreset)
            ->setParameter('active', true);

        // Add ordering
        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('tpc.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }
}
