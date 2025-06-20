<?php

namespace App\Repository;

use App\Entity\InsurancePolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InsurancePolicy>
 *
 * @method InsurancePolicy|null find($id, $lockMode = null, $lockVersion = null)
 * @method InsurancePolicy|null findOneBy(array $criteria, array $orderBy = null)
 * @method InsurancePolicy[]    findAll()
 * @method InsurancePolicy[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InsurancePolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsurancePolicy::class);
    }

    public function save(InsurancePolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsurancePolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Count insurance policies created on a specific date
     *
     * @param \DateTime $date
     * @return int
     */
    public function countPoliciesForDate(\DateTime $date): int
    {
        $startDate = clone $date;
        $startDate->setTime(0, 0, 0);

        $endDate = clone $date;
        $endDate->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find insurance policies with pagination, sorting, and filtering
     *
     * @param int $page
     * @param int $limit
     * @param string $sortBy
     * @param string $sortOrder
     * @param string $search
     * @param string $status
     * @return array
     */
    public function findWithPagination(int $page, int $limit, string $sortBy, string $sortOrder, string $search = '', string $status = ''): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.settlement', 's')
            ->leftJoin('p.estateType', 'et')
            ->leftJoin('p.personRole', 'pr')
            ->leftJoin('p.tariffPreset', 'tp')
            ->addSelect('s', 'et', 'pr', 'tp');

        // Add search conditions
        if (!empty($search)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('p.code', ':search'),
                    $qb->expr()->like('p.fullName', ':search'),
                    $qb->expr()->like('p.idNumber', ':search'),
                    $qb->expr()->like('p.phone', ':search'),
                    $qb->expr()->like('p.email', ':search'),
                    $qb->expr()->like('s.name', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }

        // Add status filter if provided
        if (!empty($status)) {
            // You can add status logic here if needed
        }

        // Add sorting
        switch ($sortBy) {
            case 'code':
                $qb->orderBy('p.code', $sortOrder);
                break;
            case 'fullName':
                $qb->orderBy('p.fullName', $sortOrder);
                break;
            case 'total':
                $qb->orderBy('p.total', $sortOrder);
                break;
            case 'settlement':
                $qb->orderBy('s.name', $sortOrder);
                break;
            default:
                $qb->orderBy('p.createdAt', $sortOrder);
                break;
        }

        // Get total count for pagination
        $countQb = clone $qb;
        $totalItems = $countQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Get paginated results
        $policies = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Format the results
        $formattedPolicies = [];
        foreach ($policies as $policy) {
            $formattedPolicies[] = [
                'id' => $policy->getId(),
                'code' => $policy->getCode(),
                'fullName' => $policy->getFullName(),
                'idNumber' => $policy->getIdNumber(),
                'phone' => $policy->getPhone(),
                'email' => $policy->getEmail(),
                'settlement' => $policy->getSettlement() ? $policy->getSettlement()->getName() : null,
                'estateType' => $policy->getEstateType() ? $policy->getEstateType()->getName() : null,
                'personRole' => $policy->getPersonRole() ? $policy->getPersonRole()->getName() : null,
                'tariffPreset' => $policy->getTariffPreset() ? $policy->getTariffPreset()->getName() : $policy->getTariffPresetName(),
                'subtotal' => $policy->getSubtotal(),
                'discount' => $policy->getDiscount(),
                'subtotalTax' => $policy->getSubtotalTax(),
                'total' => $policy->getTotal(),
                'createdAt' => $policy->getCreatedAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $policy->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $totalPages = ceil($totalItems / $limit);

        return [
            'policies' => $formattedPolicies,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages
        ];
    }

    /**
     * Find insurance policy with all related details
     *
     * @param int $id
     * @return array|null
     */
    public function findWithDetails(int $id): ?array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.settlement', 's')
            ->leftJoin('p.estateType', 'et')
            ->leftJoin('p.estateSubtype', 'est')
            ->leftJoin('p.distanceToWater', 'dw')
            ->leftJoin('p.personRole', 'pr')
            ->leftJoin('p.idNumberType', 'int')
            ->leftJoin('p.insurerNationality', 'insNat')
            ->leftJoin('p.insurerSettlement', 'insSet')
            ->leftJoin('p.propertyOwnerIdNumberType', 'point')
            ->leftJoin('p.propertyOwnerNationality', 'poNat')
            ->leftJoin('p.tariffPreset', 'tp')
            ->leftJoin('p.promotionalCode', 'pc')
            ->leftJoin('p.insurancePolicyClauses', 'ipc')
            ->leftJoin('ipc.insuranceClause', 'ic')
            ->leftJoin('p.insurancePolicyPropertyChecklists', 'ippc')
            ->leftJoin('ippc.propertyChecklist', 'pcl')
            ->addSelect('s', 'et', 'est', 'dw', 'pr', 'int', 'insNat', 'insSet', 'point', 'poNat', 'tp', 'pc', 'ipc', 'ic', 'ippc', 'pcl')
            ->where('p.id = :id')
            ->setParameter('id', $id);

        $policy = $qb->getQuery()->getOneOrNullResult();

        if (!$policy) {
            return null;
        }

        // Format the policy with all details
        $formattedPolicy = [
            'id' => $policy->getId(),
            'code' => $policy->getCode(),
            'fullName' => $policy->getFullName(),
            'idNumber' => $policy->getIdNumber(),
            'birthDate' => $policy->getBirthDate() ? $policy->getBirthDate()->format('d.m.Y') : null,
            'gender' => $policy->getGender(),
            'permanentAddress' => $policy->getPermanentAddress(),
            'phone' => $policy->getPhone(),
            'email' => $policy->getEmail(),
            'propertyAddress' => $policy->getPropertyAddress(),
            'propertyOwnerName' => $policy->getPropertyOwnerName(),
            'propertyOwnerIdNumber' => $policy->getPropertyOwnerIdNumber(),
            'propertyOwnerBirthDate' => $policy->getPropertyOwnerBirthDate() ? $policy->getPropertyOwnerBirthDate()->format('d.m.Y') : null,
            'propertyOwnerGender' => $policy->getPropertyOwnerGender(),
            'areaSqMeters' => $policy->getAreaSqMeters(),
            'subtotal' => $policy->getSubtotal(),
            'discount' => $policy->getDiscount(),
            'subtotalTax' => $policy->getSubtotalTax(),
            'total' => $policy->getTotal(),
            'createdAt' => $policy->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $policy->getUpdatedAt()->format('Y-m-d H:i:s'),
            'settlement' => $policy->getSettlement() ? [
                'id' => $policy->getSettlement()->getId(),
                'name' => $policy->getSettlement()->getFullName(),
            ] : null,
            'estateType' => $policy->getEstateType() ? [
                'id' => $policy->getEstateType()->getId(),
                'name' => $policy->getEstateType()->getName()
            ] : null,
            'estateSubtype' => $policy->getEstateSubtype() ? [
                'id' => $policy->getEstateSubtype()->getId(),
                'name' => $policy->getEstateSubtype()->getName()
            ] : null,
            'distanceToWater' => $policy->getDistanceToWater() ? [
                'id' => $policy->getDistanceToWater()->getId(),
                'name' => $policy->getDistanceToWater()->getName()
            ] : null,
            'personRole' => $policy->getPersonRole() ? [
                'id' => $policy->getPersonRole()->getId(),
                'name' => $policy->getPersonRole()->getName()
            ] : null,
            'idNumberType' => $policy->getIdNumberType() ? [
                'id' => $policy->getIdNumberType()->getId(),
                'name' => $policy->getIdNumberType()->getName()
            ] : null,
            'insurerNationality' => $policy->getInsurerNationality() ? [
                'id' => $policy->getInsurerNationality()->getId(),
                'name' => $policy->getInsurerNationality()->getName()
            ] : null,
            'insurerSettlement' => $policy->getInsurerSettlement() ? [
                'id' => $policy->getInsurerSettlement()->getId(),
                'name' => $policy->getInsurerSettlement()->getFullName()
            ] : null,
            'propertyOwnerIdNumberType' => $policy->getPropertyOwnerIdNumberType() ? [
                'id' => $policy->getPropertyOwnerIdNumberType()->getId(),
                'name' => $policy->getPropertyOwnerIdNumberType()->getName()
            ] : null,
            'propertyOwnerNationality' => $policy->getPropertyOwnerNationality() ? [
                'id' => $policy->getPropertyOwnerNationality()->getId(),
                'name' => $policy->getPropertyOwnerNationality()->getName()
            ] : null,
            'tariffPreset' => $policy->getTariffPreset() ? [
                'id' => $policy->getTariffPreset()->getId(),
                'name' => $policy->getTariffPreset()->getName()
            ] : null,
            'tariffPresetName' => $policy->getTariffPresetName(),
            'promotionalCode' => $policy->getPromotionalCode() ? [
                'id' => $policy->getPromotionalCode()->getId(),
                'code' => $policy->getPromotionalCode()->getCode(),
                'discountPercentage' => $policy->getPromotionalCode()->getDiscountPercentage()
            ] : null,
            'promotionalCodeDiscount' => $policy->getPromotionalCodeDiscount(),
            'insurancePolicyClauses' => [],
            'propertyChecklistItems' => [],
            'propertyAdditionalInfo' => $policy->getPropertyAdditionalInfo(),
            'propertyOwnerSettlement' => $policy->getPropertyOwnerSettlement() ? [
                'id' => $policy->getPropertyOwnerSettlement()->getId(),
                'name' => $policy->getPropertyOwnerSettlement()->getFullName()
            ] : null,
            'propertyOwnerPermanentAddress' => $policy->getPropertyOwnerPermanentAddress(),
        ];

        // Add insurance policy clauses
        foreach ($policy->getInsurancePolicyClauses() as $clause) {
            $formattedPolicy['insurancePolicyClauses'][] = [
                'id' => $clause->getId(),
                'name' => $clause->getName(),
                'tariffNumber' => $clause->getTariffNumber(),
                'tariffAmount' => $clause->getTariffAmount(),
                'position' => $clause->getPosition(),
                'insuranceClause' => $clause->getInsuranceClause() ? [
                    'id' => $clause->getInsuranceClause()->getId(),
                    'name' => $clause->getInsuranceClause()->getName()
                ] : null
            ];
        }

        // Add property checklist items
        foreach ($policy->getInsurancePolicyPropertyChecklists() as $checklist) {
            $formattedPolicy['propertyChecklistItems'][] = [
                'id' => $checklist->getId(),
                'name' => $checklist->getName(),
                'value' => $checklist->getValue(),
                'propertyChecklist' => $checklist->getPropertyChecklist() ? [
                    'id' => $checklist->getPropertyChecklist()->getId(),
                    'name' => $checklist->getPropertyChecklist()->getName()
                ] : null
            ];
        }

        return $formattedPolicy;
    }

    /**
     * Get statistics for insurance policies
     *
     * @return array
     */
    public function getStats(): array
    {
        $qb = $this->createQueryBuilder('p');

        // Total policies
        $totalPolicies = $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Total amount
        $totalAmount = $qb->select('SUM(p.total)')->getQuery()->getSingleScalarResult();

        // Policies today
        $today = new \DateTime();
        $todayStart = clone $today;
        $todayStart->setTime(0, 0, 0);
        $todayEnd = clone $today;
        $todayEnd->setTime(23, 59, 59);

        $todayPolicies = $qb->select('COUNT(p.id)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();

        // Today's total amount
        $todayAmount = $qb->select('SUM(p.total)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalPolicies' => (int) $totalPolicies,
            'totalAmount' => (float) $totalAmount,
            'todayPolicies' => (int) $todayPolicies,
            'todayAmount' => (float) $todayAmount
        ];
    }
}
