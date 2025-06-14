<?php

namespace App\Tests\Controller\Api\V1;

use App\Entity\Settlement;
use App\Entity\EarthquakeZone;
use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SettlementControllerTest extends WebTestCase
{
    public function testAutocomplete(): void
    {
        $client = static::createClient();

        // Create a mock repository
        $repository = $this->createMock(SettlementRepository::class);

        // Set up the mock to return test data
        $settlement1 = new Settlement();
        $settlement1->setName('Sofia');
        $settlement1->setPostCode('1000');

        // Create a mock EarthquakeZone
        $earthquakeZone = new EarthquakeZone();
        $earthquakeZone->setName('Зона 2');
        $earthquakeZone->setTariffNumber(0.025);
        $earthquakeZone->setPosition(2);

        // Use reflection to set the ID property for the EarthquakeZone
        $reflectionClass = new \ReflectionClass(EarthquakeZone::class);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($earthquakeZone, 2);

        $settlement1->setEarthquakeZone($earthquakeZone);

        // Use reflection to set the ID property for testing
        $reflectionClass = new \ReflectionClass(Settlement::class);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($settlement1, 1);

        $settlement2 = new Settlement();
        $settlement2->setName('Sofia District');
        $settlement2->setPostCode('1100');
        $settlement2->setEarthquakeZone($earthquakeZone);

        // Use reflection to set the ID property for testing
        $reflectionProperty->setValue($settlement2, 2);

        $repository->expects($this->once())
            ->method('findByNameOrPostalCode')
            ->with('sof', 5)
            ->willReturn([$settlement1, $settlement2]);

        // Replace the real repository with the mock
        self::getContainer()->set(SettlementRepository::class, $repository);

        // Make the request
        $client->request('GET', '/api/v1/settlements?query=sof&limit=5');

        // Check that the response is successful
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Check that the response is in JSON format
        $this->assertTrue(
            $client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'The response content type is not application/json'
        );

        // Decode the JSON response
        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Check that the response contains the expected data
        $this->assertCount(2, $responseData);
        $this->assertEquals(1, $responseData[0]['id']);
        $this->assertEquals('Sofia', $responseData[0]['name']);
        $this->assertEquals('1000', $responseData[0]['postCode']);
        $this->assertEquals(2, $responseData[1]['id']);
        $this->assertEquals('Sofia District', $responseData[1]['name']);
        $this->assertEquals('1100', $responseData[1]['postCode']);
    }
}
