<?php

namespace App\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractBillingTest extends WebTestCase
{
    protected static ?KernelBrowser $browser = null;

    protected static function getBrowser(): KernelBrowser
    {
        if (static::$browser === null) {
            static::$browser = static::createClient();
            static::$browser->disableReboot();
        }

        static::$browser->getKernel()->boot();

        return static::$browser;
    }

    protected function setUp(): void
    {
        static::getBrowser();
        $this->loadFixtures($this->getFixtures());
    }

    final protected function tearDown(): void
    {
        parent::tearDown();
        static::$browser = null;
    }

    protected function getFixtures(): array
    {
        return [];
    }

    /**
     * Загружает фикстуры из DI-контейнера (поддерживает autowire-зависимости).
     */
    protected function loadFixtures(array $fixtureClasses): void
    {
        $container = static::getBrowser()->getContainer();
        $em = $container->get('doctrine')->getManager();

        $loader = new Loader();
        foreach ($fixtureClasses as $class) {
            $loader->addFixture($container->get($class));
        }

        $purger   = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    /**
     * Авторизуется через /api/v1/auth и возвращает JWT-токен.
     */
    protected function getAuthToken(string $email, string $password): string
    {
        $client = static::getBrowser();
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $email, 'password' => $password])
        );

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $data, 'Авторизация не вернула токен');

        return $data['token'];
    }

    /**
     * Выполняет JSON-запрос к API.
     */
    protected function apiRequest(
        string $method,
        string $url,
        array $data = [],
        ?string $token = null
    ): array {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        static::getBrowser()->request(
            $method,
            $url,
            [],
            [],
            $headers,
            empty($data) ? null : json_encode($data)
        );

        return json_decode(static::getBrowser()->getResponse()->getContent(), true) ?? [];
    }

    protected function getStatusCode(): int
    {
        return static::getBrowser()->getResponse()->getStatusCode();
    }
}
