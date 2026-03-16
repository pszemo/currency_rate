<?php

declare(strict_types=1);

use CurrencyRate\Service\CurrencyRateService;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Currency_RateRatesModuleFrontController extends ModuleFrontController
{
    /**
     * @throws PrestaShopException
     */

    public function initContent(): void
    {
        parent::initContent();

        $service = new CurrencyRateService();

        $page      = max(1, (int) Tools::getValue('page', 1));
        $perPage   = 20;
        $sortField = Tools::getValue('sort', 'date');
        $sortDir   = Tools::getValue('dir', 'DESC');

        $data = $service->getHistoricalRates($page, $perPage, $sortField, $sortDir);

        $this->context->smarty->assign([
            'currency_rate_rates'      => $data['rates'],
            'currency_rate_total'      => $data['total'],
            'currency_rate_page'       => $page,
            'currency_rate_pages'      => $data['pages'],
            'currency_rate_per_page'   => $perPage,
            'currency_rate_sort_field' => $sortField,
            'currency_rate_sort_dir'   => $sortDir,
            'currency_rate_page_url'   => $this->context->link->getModuleLink('currency_rate', 'rates'),
        ]);

        $this->setTemplate('module:currency_rate/views/templates/front/rates.tpl');
    }
}
