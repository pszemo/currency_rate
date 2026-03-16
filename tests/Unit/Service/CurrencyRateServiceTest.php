<?php

declare(strict_types=1);

namespace CurrencyRate\Tests\Unit\Service;

use CurrencyRate\Api\NbpApiClient;
use CurrencyRate\Cache\RateCache;
use CurrencyRate\Repository\CurrencyRateRepository;
use CurrencyRate\Service\CurrencyRateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CurrencyRateService — verifies:
 * - Cache hit avoids DB/API calls
 * - Cache miss triggers DB lookup
 * - DB empty → API fallback
 * - API failure is handled gracefully (returns [], logs error)
 * - backfillRates / clearRates / clearAndRefetch behaviour
 */
class CurrencyRateServiceTest extends TestCase
{
    private NbpApiClient&MockObject       $apiClient;
    private CurrencyRateRepository&MockObject $repository;
    private RateCache&MockObject          $cache;
    private CurrencyRateService          $service;

    protected function setUp(): void
    {
        \Cache::reset();
        \PrestaShopLogger::reset();

        $this->apiClient  = $this->createMock(NbpApiClient::class);
        $this->repository = $this->createMock(CurrencyRateRepository::class);
        $this->cache      = $this->createMock(RateCache::class);

        $this->service = $this->buildService();
    }

    // -------------------------------------------------------------------------
    // getTodayRates
    // -------------------------------------------------------------------------

    public function testGetTodayRatesReturnsCachedValueWithoutHittingDb(): void
    {
        $cachedRates = [
            ['currency_code' => 'EUR', 'currency_name' => 'Euro', 'rate' => 4.285, 'date' => '2024-03-13'],
        ];

        $this->cache->method('get')->with('today')->willReturn($cachedRates);

        // Repository and API must NOT be called
        $this->repository->expects($this->never())->method('getLatestRates');
        $this->apiClient->expects($this->never())->method('fetchTodayRates');

        $result = $this->service->getTodayRates();

        $this->assertSame($cachedRates, $result);
    }

    public function testGetTodayRatesQueriesDbOnCacheMiss(): void
    {
        $dbRates = [
            ['currency_code' => 'USD', 'currency_name' => 'Dolar', 'rate' => 3.96, 'date' => '2024-03-13'],
        ];

        $this->cache->method('get')->with('today')->willReturn(null);
        $this->repository->method('getLatestRates')->willReturn($dbRates);
        $this->cache->expects($this->once())->method('set')->with('today', $dbRates);

        $result = $this->service->getTodayRates();

        $this->assertSame($dbRates, $result);
    }

    public function testGetTodayRatesFetchesFromApiWhenDbIsEmpty(): void
    {
        $apiTables = [[
            'effectiveDate' => '2024-03-13',
            'rates' => [
                ['code' => 'EUR', 'currency' => 'euro', 'mid' => 4.285],
            ],
        ]];

        $dbRatesAfterSave = [
            ['currency_code' => 'EUR', 'currency_name' => 'Euro', 'rate' => 4.285, 'date' => '2024-03-13'],
        ];

        $this->cache->method('get')->with('today')->willReturn(null);
        $this->repository->method('getLatestRates')
            ->willReturnOnConsecutiveCalls([], $dbRatesAfterSave); // empty first, populated after save

        $this->apiClient->expects($this->once())
            ->method('fetchTodayRates')
            ->willReturn($apiTables);

        $this->repository->expects($this->once())->method('saveRates');
        $this->cache->expects($this->once())->method('invalidateAll');

        $result = $this->service->getTodayRates();

        $this->assertSame($dbRatesAfterSave, $result);
    }

    public function testGetTodayRatesReturnsEmptyArrayWhenApiThrows(): void
    {
        $this->cache->method('get')->with('today')->willReturn(null);
        $this->repository->method('getLatestRates')->willReturn([]);

        $this->apiClient->method('fetchTodayRates')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $result = $this->service->getTodayRates();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetTodayRatesLogsErrorWhenApiThrows(): void
    {
        $this->cache->method('get')->with('today')->willReturn(null);
        $this->repository->method('getLatestRates')->willReturn([]);
        $this->apiClient->method('fetchTodayRates')
            ->willThrowException(new \RuntimeException('Timeout'));

        $this->service->getTodayRates();

        $this->assertNotEmpty(\PrestaShopLogger::$logs);
        $this->assertStringContainsString('Timeout', \PrestaShopLogger::$logs[0]['message']);
        $this->assertSame(3, \PrestaShopLogger::$logs[0]['severity']); // 3 = error
    }

    // -------------------------------------------------------------------------
    // getHistoricalRates
    // -------------------------------------------------------------------------

    public function testGetHistoricalRatesReturnsCachedResult(): void
    {
        $cached = ['rates' => [], 'total' => 0, 'pages' => 0];

        $this->cache->method('keyForHistorical')->willReturn('historical_1_20_date_DESC');
        $this->cache->method('get')->with('historical_1_20_date_DESC')->willReturn($cached);

        $this->repository->expects($this->never())->method('getHistoricalRates');

        $result = $this->service->getHistoricalRates();

        $this->assertSame($cached, $result);
    }

    public function testGetHistoricalRatesQueriesDbOnCacheMiss(): void
    {
        $this->cache->method('keyForHistorical')->willReturn('historical_1_20_date_DESC');
        $this->cache->method('get')->willReturn(null);

        $this->repository->method('getHistoricalRates')->willReturn([
            ['currency_code' => 'EUR', 'currency_name' => 'Euro', 'rate' => 4.28, 'date' => '2024-03-13'],
        ]);
        $this->repository->method('countHistoricalRates')->willReturn(1);

        $result = $this->service->getHistoricalRates(1, 20, 'date', 'DESC');

        $this->assertArrayHasKey('rates', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertSame(1, $result['total']);
    }

    // -------------------------------------------------------------------------
    // backfillRates
    // -------------------------------------------------------------------------

    public function testBackfillRatesSavesAndInvalidatesCache(): void
    {
        $apiResponse = [[
            'effectiveDate' => '2024-03-13',
            'rates' => [['code' => 'EUR', 'mid' => 4.285]],
        ]];

        $this->apiClient->method('fetchRatesForRange')->willReturn($apiResponse);
        $this->repository->expects($this->once())->method('saveRates');
        $this->cache->expects($this->once())->method('invalidateAll');

        $this->service->backfillRates(30);
    }

    public function testBackfillRatesDoesNothingWhenApiReturnsEmpty(): void
    {
        $this->apiClient->method('fetchRatesForRange')->willReturn([]);
        $this->repository->expects($this->never())->method('saveRates');

        $this->service->backfillRates(30);
    }

    public function testBackfillRatesThrowsWhenApiThrows(): void
    {
        $this->apiClient->method('fetchRatesForRange')
            ->willThrowException(new \RuntimeException('API down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API down');

        $this->service->backfillRates(30);
    }

    // -------------------------------------------------------------------------
    // clearRates / clearAndRefetch
    // -------------------------------------------------------------------------

    public function testClearRatesDeletesFromDbAndInvalidatesCache(): void
    {
        $this->repository->expects($this->once())->method('deleteAllRates');
        $this->cache->expects($this->once())->method('invalidateAll');

        $this->service->clearRates();
    }

    public function testClearAndRefetchClearsAndBackfills(): void
    {
        $apiResponse = [[
            'effectiveDate' => '2024-03-13',
            'rates' => [['code' => 'EUR', 'mid' => 4.285]],
        ]];

        $this->repository->expects($this->once())->method('deleteAllRates');
        $this->apiClient->method('fetchRatesForRange')->willReturn($apiResponse);
        $this->repository->expects($this->once())->method('saveRates');

        $this->service->clearAndRefetch();
    }

    // -------------------------------------------------------------------------
    // Helper: build service with mocked dependencies via reflection
    // -------------------------------------------------------------------------

    private function buildService(): CurrencyRateService
    {
        // CurrencyRateService creates its deps in __construct — we override via reflection
        $service = (new \ReflectionClass(CurrencyRateService::class))
            ->newInstanceWithoutConstructor();

        $inject = function (string $prop, mixed $value) use ($service): void {
            $ref = new \ReflectionProperty(CurrencyRateService::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue($service, $value);
        };

        $inject('apiClient',  $this->apiClient);
        $inject('repository', $this->repository);
        $inject('cache',      $this->cache);

        return $service;
    }
}
