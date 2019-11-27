<?php

namespace UnitTest;
require_once __DIR__ . './../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

final class todoTest extends TestCase
{
    private $baseUrl = 'http://localhost:1337';
    private $testUsername = 'user1';
    private $testPassword = 'user1';
    private $client = null;
    private $cookieJar = null;

    public function setUp(): void
    {
        // initiate http client
        $this->client = new Client([
            'cookies' => true,
            'allow_redirects' => true
        ]);
        $this->cookieJar = new CookieJar();

        // write session in cookieJar after login
        $this->client->request('POST', $this->baseUrl . '/login', [
            'form_params' => [
                'username' => $this->testUsername,
                'password' => $this->testPassword
            ],
            'cookies' => $this->cookieJar
        ]);
    }

    public function tearDown(): void
    {
        $this->client = null;
        $this->cookieJar = null;
    }

    public function testHomePageAccessible()
    {
        $response = $this->client->request('GET', $this->baseUrl);
        $page = $response->getBody()->getContents();
        $this->assertEquals('200', $response->getStatusCode());
        $this->assertStringContainsString("AskNicely PHP backend", $page);
    }

    public function testTodoListPage()
    {
        $response = $this->client->request(
            'GET',
            $this->baseUrl . '/todo',
            [
                'cookies' => $this->cookieJar
            ]
        );
        $page = $response->getBody()->getContents();
        $this->assertEquals('200', $response->getStatusCode());
        $this->assertStringContainsString("Todo List:", $page);
    }

    /**
     * test add a new todoTask then delete it
     */
    public function testAddThenDeleteTodo()
    {
        // test add
        $response = $this->client->request('POST', $this->baseUrl . '/todo/add', [
            'form_params' => [
                'description' => 'description for test',
            ],
            'cookies' => $this->cookieJar
        ]);
        $page = $response->getBody()->getContents();
        $this->assertStringContainsString("Cool, Add todo successfully!", $page);
        // test delete
        $dom = new \DOMDocument();
        @$dom->loadHTML($page);
        // get todoId
        $nodes = $dom->getElementsByTagName('a');
        foreach ($nodes as $node) {
            if (strpos($node->nodeValue, 'description for test') !== false) {
                $href = $node->getAttribute('href');
                $todoId = str_replace('/todo/', '', $href);
            }
        }
        $response = $this->client->request(
            'GET',
            $this->baseUrl . '/todo/' . $todoId . '/delete',
            ['cookies' => $this->cookieJar]
        );
        $page = $response->getBody()->getContents();
        $this->assertEquals('200', $response->getStatusCode());
        $this->assertStringContainsString("Delete todo successfully!", $page);
    }
}
