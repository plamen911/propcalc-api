<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settlement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settlement>
 *
 * @method Settlement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Settlement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Settlement[]    findAll()
 * @method Settlement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SettlementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settlement::class);
    }

    /**
     * Find settlements by name or postal code for autocomplete
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results to return
     * @return Settlement[] Returns an array of Settlement objects
     */
    public function findByNameOrPostalCode(string $query, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('s');
        $expr = $qb->expr();

        return $qb
            ->leftJoin('s.type', 't')
            ->leftJoin('s.municipality', 'm')
            ->leftJoin('s.region', 'r')
            ->where(
                $expr->andX(
                    $expr->orX(
                        $expr->like('LOWER(s.name)', 'LOWER(:query)'),
                        $expr->like('s.postCode', ':query')
                    ),
                    $expr->isNotNull('s.earthquakeZone')
                )
            )
            ->setParameter('query', $query . '%')
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    /**
     * Find and remove duplicate settlements
     * Duplicates are defined as settlements with the same name and post-code
     * Uses direct SQL for better memory efficiency
     *
     * @return int Number of removed duplicates
     * @throws Exception
     */
    public function removeDuplicates(): int
    {
        $conn = $this->getEntityManager()->getConnection();

        // First, identify the duplicate sets and the IDs to keep (the minimum ID for each set)
        $sql = "
            SELECT MIN(id) as keep_id, name, post_code
            FROM settlements
            GROUP BY name, post_code
            HAVING COUNT(*) > 1
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $duplicateSets = $result->fetchAllAssociative();

        $removedCount = 0;

        // For each set of duplicates, delete all except the one with the minimum ID
        foreach ($duplicateSets as $set) {
            $deleteSql = "
                DELETE FROM settlements
                WHERE name = :name
                AND post_code = :post_code
                AND id != :keep_id
            ";

            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bindValue('name', $set['name']);
            $deleteStmt->bindValue('post_code', $set['post_code']);
            $deleteStmt->bindValue('keep_id', $set['keep_id']);

            $result = $deleteStmt->executeStatement();
            $removedCount += $result;
        }

        return $removedCount;
    }
}
