<?php

namespace App\Tests\Controller\Api\V1;

use App\Entity\WaterDistance;
use App\Entity\EstateType;
use App\Entity\EarthquakeZone;
use App\Entity\InsurancePolicy;
use App\Entity\Settlement;
use App\Repository\WaterDistanceRepository;
use App\Repository\EstateTypeRepository;
use App\Repository\InsurancePolicyRepository;
use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InsurancePolicyControllerTest extends WebTestCase
{
    public function testCreateInsurancePolicy(): void
    {
        $client = static::createClient();

        // Create mock repositories
        $settlementRepository = $this->createMock(SettlementRepository::class);
        $estateTypeRepository = $this->createMock(EstateTypeRepository::class);
        $waterDistanceRepository = $this->createMock(WaterDistanceRepository::class);
        $insurancePolicyRepository = $this->createMock(InsurancePolicyRepository::class);

        // Create mock entities
        $settlement = new Settlement();
        $this->setEntityId($settlement, 2399);
        $settlement->setName('Test Settlement');
        $settlement->setPostCode('12345');

        // Create a mock EarthquakeZone
        $earthquakeZone = new EarthquakeZone();
        $earthquakeZone->setName('Зона 2');
        $earthquakeZone->setTariffNumber(0.025);
        $earthquakeZone->setPosition(2);
        $this->setEntityId($earthquakeZone, 2);

        $settlement->setEarthquakeZone($earthquakeZone);

        $estateType = new EstateType();
        $this->setEntityId($estateType, 1);
        $estateType->setName('Test Estate Type');
        $estateType->setCode('TEST');
        $estateType->setPosition(1);

        $estateSubtype = new EstateType();
        $this->setEntityId($estateSubtype, 2);
        $estateSubtype->setName('Test Estate Subtype');
        $estateSubtype->setCode('TEST-SUB');
        $estateSubtype->setPosition(1);

        $distanceToWater = new WaterDistance();
        $this->setEntityId($distanceToWater, 1);
        $distanceToWater->setName('Test Distance to Water');
        $distanceToWater->setCode('T');
        $distanceToWater->setPosition(1);

        // Set up repository mocks to return the mock entities
        $settlementRepository->expects($this->once())
            ->method('find')
            ->with(2399)
            ->willReturn($settlement);

        $estateTypeRepository->expects($this->exactly(2))
            ->method('find')
            ->willReturnMap([
                [1, $estateType],
                [2, $estateSubtype]
            ]);

        $waterDistanceRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($distanceToWater);

        // Set up the insurancePolicyRepository mock to simulate saving
        $insurancePolicyRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function (InsurancePolicy $policy) use ($settlement, $estateType, $estateSubtype, $distanceToWater) {
                    return $policy->getSettlement() === $settlement
                        && $policy->getEstateType() === $estateType
                        && $policy->getEstateSubtype() === $estateSubtype
                        && $policy->getDistanceToWater() === $distanceToWater
                        && $policy->getAreaSqMeters() === 100.0;
                }),
                true
            );

        // Replace the real repositories with the mocks
        self::getContainer()->set(SettlementRepository::class, $settlementRepository);
        self::getContainer()->set(EstateTypeRepository::class, $estateTypeRepository);
        self::getContainer()->set(WaterDistanceRepository::class, $waterDistanceRepository);
        self::getContainer()->set(InsurancePolicyRepository::class, $insurancePolicyRepository);

        // Make the request
        $client->request(
            'POST',
            '/api/v1/insurance-policies',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'settlement_id' => 2399,
                'estate_type_id' => 1,
                'estate_subtype_id' => 2,
                'distance_to_water_id' => 1,
                'area_sq_meters' => 100
            ])
        );

        // Check that the response is successful
        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        // Check that the response is in JSON format
        $this->assertTrue(
            $client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'The response content type is not application/json'
        );

        // Decode the JSON response
        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Check that the response contains the expected data
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('settlement_id', $responseData);
        $this->assertArrayHasKey('estate_type_id', $responseData);
        $this->assertArrayHasKey('estate_subtype_id', $responseData);
        $this->assertArrayHasKey('distance_to_water_id', $responseData);
        $this->assertArrayHasKey('area_sq_meters', $responseData);
        $this->assertArrayHasKey('subtotal', $responseData);
        $this->assertArrayHasKey('discount', $responseData);
        $this->assertArrayHasKey('subtotal_tax', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertArrayHasKey('created_at', $responseData);
        $this->assertArrayHasKey('updated_at', $responseData);
    }

    public function testCreateInsurancePolicyWithMissingFields(): void
    {
        $client = static::createClient();

        // Make the request with missing fields
        $client->request(
            'POST',
            '/api/v1/insurance-policies',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'settlement_id' => 2399,
                // Missing estate_type_id
                'estate_subtype_id' => 2,
                // Missing distance_to_water_id
                'area_sq_meters' => 100
            ])
        );

        // Check that the response is a bad request
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Decode the JSON response
        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Check that the response contains error messages for the missing fields
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertCount(2, $responseData['errors']);
        $this->assertContains('The field "estate_type_id" is required.', $responseData['errors']);
        $this->assertContains('The field "distance_to_water_id" is required.', $responseData['errors']);
    }

    public function testCreateInsurancePolicyWithInvalidAreaSqMeters(): void
    {
        $client = static::createClient();

        // Make the request with invalid area_sq_meters
        $client->request(
            'POST',
            '/api/v1/insurance-policies',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'settlement_id' => 2399,
                'estate_type_id' => 1,
                'estate_subtype_id' => 2,
                'distance_to_water_id' => 1,
                'area_sq_meters' => 200000 // Exceeds the maximum of 100000
            ])
        );

        // Check that the response is a bad request
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Decode the JSON response
        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Check that the response contains an error message for the invalid area_sq_meters
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertCount(1, $responseData['errors']);
        $this->assertContains('Area in square meters must be a number between 0 and 100000.', $responseData['errors']);
    }

    public function testCreateInsurancePolicyWithNonExistentEntities(): void
    {
        $client = static::createClient();

        // Create mock repositories that return null for all finds
        $settlementRepository = $this->createMock(SettlementRepository::class);
        $estateTypeRepository = $this->createMock(EstateTypeRepository::class);
        $waterDistanceRepository = $this->createMock(WaterDistanceRepository::class);

        // Set up repository mocks to return null
        $settlementRepository->expects($this->once())
            ->method('find')
            ->with(9999)
            ->willReturn(null);

        $estateTypeRepository->expects($this->exactly(2))
            ->method('find')
            ->willReturnMap([
                [9998, null],
                [9997, null]
            ]);

        $waterDistanceRepository->expects($this->once())
            ->method('find')
            ->with(9996)
            ->willReturn(null);

        // Replace the real repositories with the mocks
        self::getContainer()->set(SettlementRepository::class, $settlementRepository);
        self::getContainer()->set(EstateTypeRepository::class, $estateTypeRepository);
        self::getContainer()->set(WaterDistanceRepository::class, $waterDistanceRepository);

        // Make the request with non-existent entity IDs
        $client->request(
            'POST',
            '/api/v1/insurance-policies',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'settlement_id' => 9999,
                'estate_type_id' => 9998,
                'estate_subtype_id' => 9997,
                'distance_to_water_id' => 9996,
                'area_sq_meters' => 100
            ])
        );

        // Check that the response is a bad request
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Decode the JSON response
        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Check that the response contains error messages for all non-existent entities
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertCount(4, $responseData['errors']);
        $this->assertContains('Settlement with ID 9999 not found.', $responseData['errors']);
        $this->assertContains('Estate type with ID 9998 not found.', $responseData['errors']);
        $this->assertContains('Estate subtype with ID 9997 not found.', $responseData['errors']);
        $this->assertContains('Distance to water with ID 9996 not found.', $responseData['errors']);
    }

    /**
     * Helper method to set the ID property of an entity using reflection
     */
    private function setEntityId(object $entity, int $id): void
    {
        $reflectionClass = new \ReflectionClass(get_class($entity));
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($entity, $id);
    }
}
