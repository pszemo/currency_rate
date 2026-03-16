<?php

declare(strict_types=1);

namespace CurrencyRate\Cache;

use Cache;
use Configuration;
use Db;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * CacheInspector exposes diagnostic information about the module's cache state.
 *
 * When PS cache is enabled  → scans the active cache backend for currency_rate_* keys.
 * When PS cache is disabled → falls back to showing current DB state as a substitute,
 *                             so the BO panel always provides useful diagnostic data.
 */
class CacheInspector
{
    private const PREFIX = 'currency_rate_';
    private const TTL    = 86400; // matches RateCache::TTL

    public function isCacheEnabled(): bool
    {
        $parametersFile = _PS_ROOT_DIR_ . '/app/config/parameters.php';
        if (!file_exists($parametersFile)) {
            return false;
        }

        $parameters = require $parametersFile;
        return !empty($parameters['parameters']['ps_cache_enable']);
    }

    /**
     * @return array<int, array{key: string, preview: string, size_bytes: int, expires_at: string}>
     */
    public function getEntries(): array
    {
        if (!$this->isCacheEnabled()) {
            return [];
        }

        $fsEntries = $this->scanFilesystemCache();

        if (!empty($fsEntries)) {
            return $fsEntries;
        }

        return $this->probeKnownKeys();
    }

    /**
     * Returns a summary of what IS in the database - used as a cache substitute
     * when PS cache is disabled, so the BO panel still shows useful data.
     *
     * @return array<string, mixed>
     */
    public function getDbSummary(): array
    {
        $db = Db::getInstance();

        $totalRates = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'currency_rate`'
        );

        $latestDate = $db->getValue(
            'SELECT MAX(`date`) FROM `' . _DB_PREFIX_ . 'currency_rate`'
        );

        $oldestDate = $db->getValue(
            'SELECT MIN(`date`) FROM `' . _DB_PREFIX_ . 'currency_rate`'
        );

        $activeCurrencies = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'currency_rate_config` WHERE `is_active` = 1'
        );

        $todayDate     = date('Y-m-d');
        $hasTodayRates = (bool) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'currency_rate` WHERE `date` = \'' . pSQL($todayDate) . '\''
        );

        $latestRates = $db->executeS(
            'SELECT r.`currency_code`, r.`rate`, r.`date`
             FROM `' . _DB_PREFIX_ . 'currency_rate` r
             INNER JOIN `' . _DB_PREFIX_ . 'currency_rate_config` c
                ON c.`currency_code` = r.`currency_code` AND c.`is_active` = 1
             INNER JOIN (
                SELECT `currency_code`, MAX(`date`) AS max_date
                FROM `' . _DB_PREFIX_ . 'currency_rate`
                GROUP BY `currency_code`
             ) latest ON latest.`currency_code` = r.`currency_code`
                      AND latest.`max_date` = r.`date`
             ORDER BY r.`currency_code` ASC'
        ) ?: [];

        return [
            'total_rates'       => $totalRates,
            'latest_date'       => $latestDate ?: '-',
            'oldest_date'       => $oldestDate ?: '-',
            'active_currencies' => $activeCurrencies,
            'has_today_rates'   => $hasTodayRates,
            'today_date'        => $todayDate,
            'latest_rates'      => $latestRates,
            'ttl_hours'         => self::TTL / 3600,
        ];
    }

    private function scanFilesystemCache(): array
    {
        $cacheDir = _PS_ROOT_DIR_ . '/cache/cachefs/';

        if (!is_dir($cacheDir)) {
            return [];
        }

        $entries  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $content = @file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $data = @unserialize($content);
            if (!is_array($data) || !isset($data['key'])) {
                continue;
            }

            $key = (string) $data['key'];
            if (!str_starts_with($key, self::PREFIX)) {
                continue;
            }

            $value     = $data['value'] ?? null;
            $entries[] = [
                'key'        => $key,
                'preview'    => $this->buildPreview($value),
                'size_bytes' => $file->getSize(),
                'expires_at' => isset($data['expire']) ? date('Y-m-d H:i:s', (int) $data['expire']) : '-',
            ];
        }

        usort($entries, fn($a, $b) => strcmp($a['key'], $b['key']));

        return $entries;
    }

    private function probeKnownKeys(): array
    {
        $knownKeys = [self::PREFIX . 'today'];

        foreach ([1, 2, 3] as $page) {
            foreach ([10, 20, 50] as $perPage) {
                foreach (['date', 'currency_code', 'rate'] as $field) {
                    foreach (['ASC', 'DESC'] as $dir) {
                        $knownKeys[] = self::PREFIX . sprintf('historical_%d_%d_%s_%s', $page, $perPage, $field, $dir);
                    }
                }
            }
        }

        $entries = [];
        $cache = \Cache::getInstance();

        foreach ($knownKeys as $key) {
            if (!$cache->exists($key)) {
                continue;
            }

            $value = $cache->get($key);
            $entries[] = [
                'key'        => $key,
                'preview'    => $this->buildPreview($value),
                'size_bytes' => strlen(serialize($value)),
                'expires_at' => '- (backend nie ujawnia TTL)',
            ];
        }

        return $entries;
    }

    private function buildPreview(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }

        if (is_array($value)) {
            if (isset($value[0]['currency_code'])) {
                $codes = array_column($value, 'currency_code');
                return sprintf('Tablica kursów [%d wpisów]: %s', count($value), implode(', ', $codes));
            }

            if (isset($value['total'], $value['pages'])) {
                return sprintf('Wyniki historyczne: %d rekordów, strona x/%d', $value['total'], $value['pages']);
            }

            return 'Tablica [' . count($value) . ' elementów]';
        }

        return substr((string) $value, 0, 120);
    }

    public function invalidateAll(): void
    {
        $cache = \Cache::getInstance();
        $cache->delete(self::PREFIX . 'today');
        foreach ($this->getEntries() as $entry) {
            $cache->delete($entry['key']);
        }
    }
}
