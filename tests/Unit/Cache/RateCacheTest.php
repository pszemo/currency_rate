<?php

declare(strict_types=1);

namespace CurrencyRate\Tests\Unit\Cache;

use CurrencyRate\Cache\RateCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RateCache — verifies get/set/invalidate behaviour
 * using the in-memory Cache stub from bootstrap.php.
 */
class RateCacheTest extends TestCase
{
    private RateCache $cache;

    protected function setUp(): void
    {
        \Cache::reset();
        $this->cache = new RateCache();
    }

    public function testGetReturnsNullWhenKeyNotStored(): void
    {
        $result = $this->cache->get('nonexistent_key');

        $this->assertNull($result);
    }

    public function testSetAndGetRoundtrip(): void
    {
        $data = [
            ['currency_code' => 'EUR', 'rate' => 4.285, 'date' => '2024-03-13'],
            ['currency_code' => 'USD', 'rate' => 3.962, 'date' => '2024-03-13'],
        ];

        $this->cache->set('today', $data);

        $result = $this->cache->get('today');

        $this->assertSame($data, $result);
    }

    public function testInvalidateRemovesKey(): void
    {
        $this->cache->set('today', ['some' => 'data']);
        $this->cache->invalidate('today');

        $this->assertNull($this->cache->get('today'));
    }

    public function testInvalidateAllRemovesTodayKey(): void
    {
        $this->cache->set('today', ['data']);
        $this->cache->invalidateAll();

        $this->assertNull($this->cache->get('today'));
    }

    public function testInvalidateAllRemovesHistoricalKeys(): void
    {
        $historicalKey = $this->cache->keyForHistorical(1, 20, 'date', 'DESC');
        $this->cache->set($historicalKey, ['rates' => [], 'total' => 0, 'pages' => 0]);

        $this->cache->invalidateAll();

        // After invalidateAll the historical key should be gone
        $this->assertNull($this->cache->get($historicalKey));
    }

    public function testKeyForHistoricalIsConsistent(): void
    {
        $key1 = $this->cache->keyForHistorical(1, 20, 'date', 'DESC');
        $key2 = $this->cache->keyForHistorical(1, 20, 'date', 'DESC');

        $this->assertSame($key1, $key2);
    }

    public function testKeyForHistoricalDiffersAcrossParams(): void
    {
        $key1 = $this->cache->keyForHistorical(1, 20, 'date', 'DESC');
        $key2 = $this->cache->keyForHistorical(2, 20, 'date', 'DESC');
        $key3 = $this->cache->keyForHistorical(1, 50, 'date', 'DESC');
        $key4 = $this->cache->keyForHistorical(1, 20, 'currency_code', 'ASC');

        $this->assertNotSame($key1, $key2);
        $this->assertNotSame($key1, $key3);
        $this->assertNotSame($key1, $key4);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->cache->set('today', ['old']);
        $this->cache->set('today', ['new']);

        $this->assertSame(['new'], $this->cache->get('today'));
    }

    public function testCacheStoresComplexNestedArrays(): void
    {
        $data = [
            'rates' => [
                ['currency_code' => 'EUR', 'currency_name' => 'Euro', 'rate' => 4.285, 'date' => '2024-03-13'],
            ],
            'total' => 1,
            'pages' => 1,
        ];

        $key = $this->cache->keyForHistorical(1, 20, 'date', 'DESC');
        $this->cache->set($key, $data);

        $this->assertSame($data, $this->cache->get($key));
    }
}
