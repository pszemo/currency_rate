<?php

declare(strict_types=1);

namespace CurrencyRate\Install;

use Configuration;
use Db;
use Module;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Installer
{
    private Module $module;

    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    public function install(): bool
    {
        return $this->createTables()
            && $this->seedAvailableCurrencies()
            && $this->installCronToken();
    }

    public function uninstall(): bool
    {
        return $this->dropTables()
            && $this->uninstallCronToken();
    }

    private function createTables(): bool
    {
        $db = Db::getInstance();

        $sql = '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'currency_rate` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `currency_code` VARCHAR(3)   NOT NULL,
                `rate`          DECIMAL(10,6) NOT NULL,
                `date`          DATE          NOT NULL,
                `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_currency_date` (`currency_code`, `date`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        if (!$db->execute($sql)) {
            return false;
        }

        $sql = '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'currency_rate_config` (
                `currency_code` VARCHAR(3)   NOT NULL,
                `currency_name` VARCHAR(100) NOT NULL,
                `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (`currency_code`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        return (bool) $db->execute($sql);
    }

    private function dropTables(): bool
    {
        $db = Db::getInstance();

        return $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'currency_rate`')
            && $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'currency_rate_config`');
    }

    private function seedAvailableCurrencies(): bool
    {
        $currencies = [
            ['EUR', 'Euro'],
            ['USD', 'Dolar amerykański'],
            ['GBP', 'Funt szterling'],
            ['CHF', 'Frank szwajcarski'],
            ['CZK', 'Korona czeska'],
            ['SEK', 'Korona szwedzka'],
            ['NOK', 'Korona norweska'],
            ['HUF', 'Forint węgierski'],
            ['JPY', 'Jen japoński'],
        ];

        $db = Db::getInstance();

        foreach ($currencies as [$code, $name]) {
            $db->execute('
                INSERT IGNORE INTO `' . _DB_PREFIX_ . 'currency_rate_config`
                    (`currency_code`, `currency_name`, `is_active`)
                VALUES
                    (\'' . pSQL($code) . '\', \'' . pSQL($name) . '\', 1)
            ');
        }

        return true;
    }

    private function installCronToken(): bool
    {
        return (bool) Configuration::updateValue(
            'CURRENCY_RATE_CRON_TOKEN',
            bin2hex(random_bytes(16))
        );
    }

    private function uninstallCronToken(): bool
    {
        return (bool) Configuration::deleteByName('CURRENCY_RATE_CRON_TOKEN');
    }
}
