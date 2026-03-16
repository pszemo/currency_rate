<?php

declare(strict_types=1);

namespace CurrencyRate\Repository;

use Db;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CurrencyRateRepository
{
    private Db $db;
    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    /**
     * Batch insert rates. Uses INSERT IGNORE to skip duplicates (idempotent).
     *
     * @param array<int, array{currency_code: string, rate: float, date: string}> $rates
     */
    public function saveRates(array $rates): bool
    {
        if (empty($rates)) {
            return true;
        }

        $values = [];
        foreach ($rates as $rate) {
            $values[] = sprintf(
                "('%s', '%s', '%s')",
                pSQL($rate['currency_code']),
                pSQL((string) $rate['rate']),
                pSQL($rate['date'])
            );
        }

        $sql = 'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'currency_rate`
                    (`currency_code`, `rate`, `date`)
                VALUES ' . implode(',', $values);

        return (bool) $this->db->execute($sql);
    }

    /**
     * Get rates for the last $days days, only for active currencies.
     *
     * @return array<int, array{currency_code: string, currency_name: string, rate: float, date: string}>
     */
    public function getHistoricalRates(int $days = 30, int $page = 1, int $perPage = 20, string $sortField = 'date', string $sortDir = 'DESC'): array
    {
        $allowedSortFields = ['date', 'currency_code', 'rate'];
        $sortField = in_array($sortField, $allowedSortFields, true) ? $sortField : 'date';
        $sortDir   = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($page - 1) * $perPage;

        $sql = '
            SELECT
                r.`currency_code`,
                c.`currency_name`,
                r.`rate`,
                r.`date`
            FROM `' . _DB_PREFIX_ . 'currency_rate` r
            INNER JOIN `' . _DB_PREFIX_ . 'currency_rate_config` c
                ON c.`currency_code` = r.`currency_code`
                AND c.`is_active` = 1
            WHERE r.`date` >= DATE_SUB(CURDATE(), INTERVAL ' . (int) $days . ' DAY)
            ORDER BY r.`' . bqSQL($sortField) . '` ' . $sortDir . '
            LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset . '
        ';

        return $this->db->executeS($sql) ?: [];
    }

    /**
     * Count total rows for pagination.
     */
    public function countHistoricalRates(int $days = 30): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'currency_rate` r
            INNER JOIN `' . _DB_PREFIX_ . 'currency_rate_config` c
                ON c.`currency_code` = r.`currency_code`
                AND c.`is_active` = 1
            WHERE r.`date` >= DATE_SUB(CURDATE(), INTERVAL ' . (int) $days . ' DAY)
        ';

        return (int) $this->db->getValue($sql);
    }

    /**
     * Get the most recent rate for each active currency.
     *
     * @return array<int, array{currency_code: string, currency_name: string, rate: float, date: string}>
     */
    public function getLatestRates(): array
    {
        $sql = '
            SELECT
                r.`currency_code`,
                c.`currency_name`,
                r.`rate`,
                r.`date`
            FROM `' . _DB_PREFIX_ . 'currency_rate` r
            INNER JOIN `' . _DB_PREFIX_ . 'currency_rate_config` c
                ON c.`currency_code` = r.`currency_code`
                AND c.`is_active` = 1
            INNER JOIN (
                SELECT `currency_code`, MAX(`date`) AS max_date
                FROM `' . _DB_PREFIX_ . 'currency_rate`
                GROUP BY `currency_code`
            ) latest ON latest.`currency_code` = r.`currency_code`
                     AND latest.`max_date`      = r.`date`
            ORDER BY r.`currency_code` ASC
        ';

        return $this->db->executeS($sql) ?: [];
    }

    /**
     * Get all currencies from config table.
     *
     * @return array<int, array{currency_code: string, currency_name: string, is_active: bool}>
     */
    public function getAllCurrencies(): array
    {
        $sql = 'SELECT `currency_code`, `currency_name`, `is_active`
                FROM `' . _DB_PREFIX_ . 'currency_rate_config`
                ORDER BY `currency_code` ASC';

        return $this->db->executeS($sql) ?: [];
    }

    /**
     * Delete all rates from DB.
     */
    public function deleteAllRates(): bool
    {
        return (bool) $this->db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'currency_rate`');
    }

    /**
     * Update active currencies in config table.
     *
     * @param string[] $activeCodes
     */
    public function updateActiveCurrencies(array $activeCodes): bool
    {
        // Deactivate all first
        $this->db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'currency_rate_config` SET `is_active` = 0'
        );

        if (empty($activeCodes)) {
            return true;
        }

        $inList = implode(',', array_map(fn($c) => "'" . pSQL($c) . "'", $activeCodes));

        return (bool) $this->db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'currency_rate_config`
             SET `is_active` = 1
             WHERE `currency_code` IN (' . $inList . ')'
        );
    }
}
