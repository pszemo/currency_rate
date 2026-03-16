<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use CurrencyRate\Cache\CacheInspector;
use CurrencyRate\Install\Installer;
use CurrencyRate\Service\CurrencyRateService;

class Currency_Rate extends Module
{
    public function __construct()
    {
        $this->name = 'currency_rate';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'HADNET';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Currency Rates', [], 'Modules.Currencyrate.Admin');
        $this->description = $this->trans(
            'Displays current and historical NBP currency exchange rates.',
            [],
            'Modules.Currencyrate.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayHeader')
            && (new Installer($this))->install()
            && $this->installTab();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && (new Installer($this))->uninstall()
            && $this->uninstallTab();
    }

    /**
     * Module configuration page in BO
     */
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitCurrencyRateConfig')) {
            $output .= $this->processConfiguration();
        }

        if (Tools::isSubmit('submitFetchNow')) {
            $output .= $this->processFetchNow();
        }

        if (Tools::isSubmit('submitClearRates')) {
            $output .= $this->processClearRates();
        }

        if (Tools::isSubmit('submitClearAndRefetch')) {
            $output .= $this->processClearAndRefetch();
        }

        if (Tools::isSubmit('submitInvalidateCache')) {
            $output .= $this->processInvalidateCache();
        }

        return $output . $this->renderConfigForm();
    }



    /**
     * Hook: product page - display prices in other currencies
     */
    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $product = $params['product'] ?? null;
        if (!$product) {
            return '';
        }

        $price = (float) $product['price_amount'];
        $service = $this->getService();
        $rates = $service->getTodayRates();

        if (empty($rates)) {
            return '';
        }

        $conversions = [];
        foreach ($rates as $rate) {
            $conversions[] = [
                'code'     => $rate['currency_code'],
                'name'     => $rate['currency_name'],
                'price'    => round($price / $rate['rate'], 2),
                'rate'     => $rate['rate'],
            ];
        }

        $this->context->smarty->assign([
            'currency_rate_conversions' => $conversions,
            'currency_rate_base_price'  => $price,
            'currency_rate_base_code'   => 'PLN',
        ]);

        return $this->display(__FILE__, 'views/templates/front/product_prices.tpl');
    }

    /**
     * Hook: add module CSS to front
     */
    public function hookDisplayHeader(): void
    {
        $this->context->controller->addCSS($this->_path . 'views/css/currency_rate.css');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function processConfiguration(): string
    {
        $activeCurrencies = Tools::getValue('active_currencies', []);
        $this->getService()->updateActiveCurrencies($activeCurrencies);

        return $this->displayConfirmation($this->trans('Settings saved.', [], 'Admin.Notifications.Success'));
    }

    private function processFetchNow(): string
    {
        try {
            $service = $this->getService();
            $service->backfillRates(30);

            return $this->displayConfirmation(
                $this->trans('Rates fetched successfully.', [], 'Modules.Currencyrate.Admin')
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'CurrencyRate fetch error: ' . $e->getMessage(),
                3,
                null,
                'Currency_Rate'
            );

            return $this->displayError(
                $this->trans('Error fetching rates: ', [], 'Modules.Currencyrate.Admin') . $e->getMessage()
            );
        }
    }

    private function processClearRates(): string
    {
        try {
            $this->getService()->clearRates();

            return $this->displayConfirmation(
                $this->trans('All rates have been deleted.', [], 'Modules.Currencyrate.Admin')
            );
        } catch (\Throwable $e) {
            return $this->displayError($e->getMessage());
        }
    }

    private function processClearAndRefetch(): string
    {
        try {
            $this->getService()->clearAndRefetch();

            return $this->displayConfirmation(
                $this->trans('Rates cleared and re-fetched successfully.', [], 'Modules.Currencyrate.Admin')
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'CurrencyRate clearAndRefetch error: ' . $e->getMessage(),
                3,
                null,
                'Currency_Rate'
            );

            return $this->displayError(
                $this->trans('Error: ', [], 'Modules.Currencyrate.Admin') . $e->getMessage()
            );
        }
    }

    private function processInvalidateCache(): string
    {
        (new CacheInspector())->invalidateAll();

        return $this->displayConfirmation(
            $this->trans('Cache modułu został wyczyszczony.', [], 'Modules.Currencyrate.Admin')
        );
    }

    private function renderConfigForm(): string
    {
        $service = $this->getService();
        $allCurrencies = $service->getAllAvailableCurrencies();
        $activeCodes = array_column(
            array_filter(
                $service->getAllAvailableCurrencies(),
                fn($c) => $c['is_active']
            ),
            'currency_code'
        );

        $inspector    = new CacheInspector();
        $cacheEnabled = $inspector->isCacheEnabled();
        $cacheEntries = $inspector->getEntries();
        $dbSummary    = !$cacheEnabled ? $inspector->getDbSummary() : [];

        $cronToken = Configuration::get('CURRENCY_RATE_CRON_TOKEN');
        $cronUrl   = $this->context->link->getModuleLink($this->name, 'cron') . '?token=' . $cronToken;

        $this->context->smarty->assign([
            'module_dir'         => $this->_path,
            'all_currencies'     => $allCurrencies,
            'active_codes'       => $activeCodes,
            'form_action'        => Context::getContext()->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
            'rates_page_url'     => $this->context->link->getModuleLink($this->name, 'rates'),
            'cache_entries'      => $cacheEntries,
            'cache_entry_count'  => count($cacheEntries),
            'cache_enabled'      => $cacheEnabled,
            'db_summary'         => $dbSummary,
            'cron_url'           => $cronUrl,
            'cron_token'         => $cronToken,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl')
             . $this->display(__FILE__, 'views/templates/admin/cache_inspector.tpl');
    }

    private function installTab(): bool
    {
        $tab = new Tab();
        $tab->class_name = 'AdminCurrencyRate';
        $tab->module     = $this->name;
        $tab->id_parent  = (int) Tab::getIdFromClassName('SELL');
        $tab->icon       = 'monetization_on';
        $tab->active     = 1;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Waluty NBP';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('AdminCurrencyRate');
        if ($idTab) {
            $tab = new Tab($idTab);
            return (bool) $tab->delete();
        }
        return true;
    }

    private function getService(): CurrencyRateService
    {
        return new CurrencyRateService();
    }
}
