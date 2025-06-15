<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CorsTest extends WebTestCase
{
    public function testCorsHeaders(): void
    {
        $client = static::createClient();

        // Test a regular GET request without Origin header
        $client->request('GET', '/api/v1/insurance-policies/admin/insurance-clauses');
        $response = $client->getResponse();

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Headers'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Credentials'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));

        // Test a regular GET request with Origin header
        $client->request('GET', '/api/v1/insurance-policies/admin/insurance-clauses', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]);
        $response = $client->getResponse();

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Headers'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Credentials'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));

        // Test a preflight OPTIONS request
        $client->request('OPTIONS', '/api/v1/insurance-policies/admin/insurance-clauses', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
        ]);
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Headers'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Credentials'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertTrue($response->headers->has('Access-Control-Max-Age'));
    }
}
