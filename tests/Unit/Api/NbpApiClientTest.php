<?php

declare(strict_types=1);

namespace CurrencyRate\Tests\Unit\Api;

use CurrencyRate\Api\NbpApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests for NbpApiClient — focuses on error handling:
 * - HTTP 404 (weekend/holiday — not an error in NBP world)
 * - HTTP 400 / 500 (real API errors)
 * - Network/transport exceptions
 * - Malformed JSON response
 * - Valid response parsing
 */
class NbpApiClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helper: build a client with an injected mock HTTP client
    // -------------------------------------------------------------------------

    /**
     * NbpApiClient uses a private HttpClientInterface. We expose it for testing
     * via a dedicated constructor parameter (added in the testable subclass below).
     */
    private function makeClient(HttpClientInterface $httpClient): NbpApiClient
    {
        return new NbpApiClientTestable($httpClient);
    }

    // -------------------------------------------------------------------------
    // fetchTodayRates
    // -------------------------------------------------------------------------

    public function testFetchTodayRatesReturnsArrayOnSuccess(): void
    {
        $body = json_encode([
            [
                'table'         => 'A',
                'no'            => '050/A/NBP/2024',
                'effectiveDate' => '2024-03-13',
                'rates'         => [
                    ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.2850],
                    ['currency' => 'dolar amerykański', 'code' => 'USD', 'mid' => 3.9620],
                ],
            ],
        ]);

        $mock   = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));
        $client = $this->makeClient($mock);

        $result = $client->fetchTodayRates();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('2024-03-13', $result[0]['effectiveDate']);
        $this->assertCount(2, $result[0]['rates']);
    }

    public function testFetchTodayRatesReturnsEmptyArrayOn404(): void
    {
        // NBP returns 404 for weekends/holidays — should NOT throw, just return []
        $mock   = new MockHttpClient(new MockResponse('Not Found', ['http_code' => 404]));
        $client = $this->makeClient($mock);

        $result = $client->fetchTodayRates();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFetchTodayRatesThrowsOnHttp500(): void
    {
        $mock   = new MockHttpClient(new MockResponse('Internal Server Error', ['http_code' => 500]));
        $client = $this->makeClient($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        $client->fetchTodayRates();
    }

    public function testFetchTodayRatesThrowsOnHttp400(): void
    {
        $mock   = new MockHttpClient(new MockResponse('Bad Request', ['http_code' => 400]));
        $client = $this->makeClient($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 400/');

        $client->fetchTodayRates();
    }

    public function testFetchTodayRatesThrowsOnNetworkError(): void
    {
        // MockResponse with network error simulation
        $mock = new MockHttpClient(function () {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused');
        });

        $client = $this->makeClient($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/connection error/i');

        $client->fetchTodayRates();
    }

    // -------------------------------------------------------------------------
    // fetchRatesForRange
    // -------------------------------------------------------------------------

    public function testFetchRatesForRangeReturnsMultipleTables(): void
    {
        $body = json_encode([
            ['effectiveDate' => '2024-03-11', 'rates' => [['code' => 'EUR', 'mid' => 4.28]]],
            ['effectiveDate' => '2024-03-12', 'rates' => [['code' => 'EUR', 'mid' => 4.29]]],
            ['effectiveDate' => '2024-03-13', 'rates' => [['code' => 'EUR', 'mid' => 4.30]]],
        ]);

        $mock   = new MockHttpClient(new MockResponse($body, ['http_code' => 200]));
        $client = $this->makeClient($mock);

        $result = $client->fetchRatesForRange('2024-03-11', '2024-03-13');

        $this->assertCount(3, $result);
    }

    public function testFetchRatesForRangeReturnsEmptyOn404(): void
    {
        $mock   = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        $client = $this->makeClient($mock);

        $result = $client->fetchRatesForRange('2024-03-09', '2024-03-10'); // weekend

        $this->assertEmpty($result);
    }

    public function testFetchRatesForRangeThrowsOnServerError(): void
    {
        $mock   = new MockHttpClient(new MockResponse('', ['http_code' => 503]));
        $client = $this->makeClient($mock);

        $this->expectException(\RuntimeException::class);

        $client->fetchRatesForRange('2024-01-01', '2024-01-31');
    }
}

// =============================================================================
// Testable subclass — exposes HttpClient injection
// =============================================================================

/**
 * Extends NbpApiClient to allow injecting a mock HttpClient.
 * This avoids modifying production code while keeping tests isolated.
 */
class NbpApiClientTestable extends NbpApiClient
{
    public function __construct(HttpClientInterface $httpClient)
    {
        // Skip parent __construct (which creates a real HttpClient)
        // and inject the mock directly via reflection.
        $ref      = new \ReflectionClass(NbpApiClient::class);
        $property = $ref->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this, $httpClient);
    }
}
