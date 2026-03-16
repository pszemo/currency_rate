<?php

declare(strict_types=1);

namespace CurrencyRate\Cache;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RateCache
{
    private const PREFIX = 'currency_rate_';
    private const TTL    = 86400;

    private \Cache $cache;

    public function __construct()
    {
        $this->cache = \Cache::getInstance();
    }

    public function get(string $key): mixed
    {
        $value = $this->cache->get(self::PREFIX . $key);
        return $value !== false ? $value : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache->set(self::PREFIX . $key, $value, self::TTL);
    }

    public function invalidate(string $key): void
    {
        $this->cache->delete(self::PREFIX . $key);
    }

    public function invalidateAll(): void
    {
        $this->cache->delete(self::PREFIX . 'today');

        foreach ([1, 2, 3, 4, 5] as $page) {
            foreach ([10, 20, 50] as $perPage) {
                foreach (['date', 'currency_code', 'rate'] as $field) {
                    foreach (['ASC', 'DESC'] as $dir) {
                        $this->cache->delete(
                            self::PREFIX . sprintf('historical_%d_%d_%s_%s', $page, $perPage, $field, $dir)
                        );
                    }
                }
            }
        }
    }

    public function keyForHistorical(int $page, int $perPage, string $sortField, string $sortDir): string
    {
        return sprintf('historical_%d_%d_%s_%s', $page, $perPage, $sortField, $sortDir);
    }
}
