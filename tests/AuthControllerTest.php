<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends WebTestCase
{
    private $token;

    public function testSuccessfulRegister()
    {
        $client = $this->createAuthenticatedClient();
        $response = $this->sendPostRequest($client, '/api/v1/register', [
            'username' => 'newuser@example.com',
            'password' => 'newpassword123'
        ]);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testFailedRegister()
    {
        $client = $this->createAuthenticatedClient();
        $response = $this->sendPostRequest($client, '/api/v1/register', [
            'username' => 'newuser@example.com',
            'password' => 'password123'
        ]);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testSuccessfulAuth()
    {
        $client = $this->createAuthenticatedClient();
        $response = $this->sendPostRequest($client, '/api/v1/auth', [
            'username' => 'newuser@example.com',
            'password' => 'newpassword123'
        ]);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testFailedAuth()
    {
        $client = $this->createAuthenticatedClient();
        $response = $this->sendPostRequest($client, '/api/v1/auth', [
            'username' => 'wrong@example.com',
            'password' => 'wrongpassword'
        ]);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetCurrentUserSuccess()
    {
        $client = $this->createAuthenticatedClient();
        $this->authenticateUser($client, 'newuser@example.com', 'newpassword123');
        
        $client->request('GET', '/api/v1/users/current', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->getToken()]);
        
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('username', $responseData);
        $this->assertArrayHasKey('roles', $responseData);
        $this->assertArrayHasKey('balance', $responseData);
    }

    public function testGetCurrentUserUnauthorized()
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/v1/users/current');
        
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    private function createAuthenticatedClient()
    {
        return static::createClient();
    }

    private function sendPostRequest($client, $url, $data)
    {
        $client->request('POST', $url, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
        return $client->getResponse();
    }

    private function authenticateUser($client, $username, $password)
    {
        $response = $this->sendPostRequest($client, '/api/v1/auth', [
            'username' => $username,
            'password' => $password
        ]);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->token = $data['token'];
    }

    private function getToken()
    {
        return $this->token;
    }
}