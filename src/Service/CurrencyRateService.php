<?php

declare(strict_types=1);

namespace CurrencyRate\Service;

use CurrencyRate\Api\NbpApiClient;
use CurrencyRate\Cache\RateCache;
use CurrencyRate\Repository\CurrencyRateRepository;
use PrestaShopLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CurrencyRateService
{
    private NbpApiClient $apiClient;
    private CurrencyRateRepository $repository;
    private RateCache $cache;

    public function __construct()
    {
        $this->apiClient  = new NbpApiClient();
        $this->repository = new CurrencyRateRepository();
        $this->cache      = new RateCache();
    }

    /**
     * Get today's rates (from cache or DB, fallback to API).
     *
     * @return array<int, array{currency_code: string, currency_name: string, rate: float, date: string}>
     */
    public function getTodayRates(): array
    {
        $cached = $this->cache->get('today');
        if ($cached !== null) {
            return $cached;
        }

        $rates = $this->repository->getLatestRates();

        if (empty($rates)) {
            // DB empty - fetch from API and store
            $rates = $this->fetchAndSaveToday();
        }

        $this->cache->set('today', $rates);

        return $rates;
    }

    /**
     * Get historical rates with pagination.
     *
     * @return array{rates: array, total: int, pages: int}
     */
    public function getHistoricalRates(int $page = 1, int $perPage = 20, string $sortField = 'date', string $sortDir = 'DESC'): array
    {
        $cacheKey = $this->cache->keyForHistorical($page, $perPage, $sortField, $sortDir);
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $rates = $this->repository->getHistoricalRates(30, $page, $perPage, $sortField, $sortDir);
        $total = $this->repository->countHistoricalRates(30);
        $pages = (int) ceil($total / $perPage);

        $result = [
            'rates' => $rates,
            'total' => $total,
            'pages' => $pages,
        ];

        $this->cache->set($cacheKey, $result);

        return $result;
    }

    /**
     * Backfill rates for the last $days days using NBP range endpoint.
     * One API call for up to 93 days.
     *
     * @throws \RuntimeException
     */
    public function backfillRates(int $days = 30): void
    {
        $endDate   = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $tables = $this->apiClient->fetchRatesForRange($startDate, $endDate);

        if (empty($tables)) {
            return;
        }

        $toSave = $this->normalizeApiResponse($tables);
        $this->repository->saveRates($toSave);
        $this->cache->invalidateAll();
    }

    /**
     * Fetch today's rates from NBP API and save to DB.
     *
     * @return array<int, array{currency_code: string, currency_name: string, rate: float, date: string}>
     */
    public function fetchAndSaveToday(): array
    {
        try {
            $tables = $this->apiClient->fetchTodayRates();
        } catch (\RuntimeException $e) {
            PrestaShopLogger::addLog(
                'CurrencyRate: failed to fetch today rates - ' . $e->getMessage(),
                3,
                null,
                'CurrencyRateService'
            );
            return [];
        }

        if (empty($tables)) {
            return [];
        }

        $toSave = $this->normalizeApiResponse($tables);
        $this->repository->saveRates($toSave);
        $this->cache->invalidateAll();

        return $this->repository->getLatestRates();
    }

    /**
     * Clear all historical rates from DB and cache.
     */
    public function clearRates(): void
    {
        $this->repository->deleteAllRates();
        $this->cache->invalidateAll();
    }

    /**
     * Clear all rates and immediately re-fetch last 30 days.
     *
     * @throws \RuntimeException
     */
    public function clearAndRefetch(): void
    {
        $this->clearRates();
        $this->backfillRates(30);
    }

    /**
     * Get all currencies from config (for BO form).
     *
     * @return array<int, array{currency_code: string, currency_name: string, is_active: bool}>
     */
    public function getAllAvailableCurrencies(): array
    {
        return $this->repository->getAllCurrencies();
    }

    // -------------------------------------------------------------------------

    /**
     * Transform NBP API response into flat array ready for DB insert.
     *
     * @param array<mixed> $tables
     * @return array<int, array{currency_code: string, rate: float, date: string}>
     */
    private function normalizeApiResponse(array $tables): array
    {
        $result = [];

        foreach ($tables as $table) {
            $date  = $table['effectiveDate'] ?? null;
            $rates = $table['rates'] ?? [];

            if (!$date || empty($rates)) {
                continue;
            }

            foreach ($rates as $entry) {
                $code = $entry['code'] ?? null;
                $mid  = $entry['mid']  ?? null;

                if (!$code || !$mid) {
                    continue;
                }

                $result[] = [
                    'currency_code' => $code,
                    'rate'          => (float) $mid,
                    'date'          => $date,
                ];
            }
        }

        return $result;
    }

    public function updateActiveCurrencies(array $activeCodes): void
    {
        $this->repository->updateActiveCurrencies($activeCodes);
        $this->cache->invalidateAll();
    }

}
