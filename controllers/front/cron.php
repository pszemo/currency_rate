<?php

declare(strict_types=1);

use CurrencyRate\Service\CurrencyRateService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cron controller for daily currency rate update.
 *
 * URL: http://yourdomain.com/module/currency_rate/cron?token=YOUR_TOKEN
 *
 * Recommended crontab (once daily at 6:00 AM):
 *   0 6 * * * curl -s "http://yourdomain.com/module/currency_rate/cron?token=YOUR_TOKEN" >> /var/log/currency_rate_cron.log 2>&1
 */
class Currency_RateCronModuleFrontController extends ModuleFrontController
{
    public function init(): void
    {
        parent::init();

        // Disable layout - return JSON only
        $this->ajax = true;
    }

    public function display(): void
    {
        $configuredToken = Configuration::get('CURRENCY_RATE_CRON_TOKEN');
        $providedToken   = Tools::getValue('token', '');

        if (empty($configuredToken) || !hash_equals($configuredToken, $providedToken)) {
            http_response_code(403);
            $this->ajaxRender(json_encode([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ]));
            exit;
        }

        try {
            $service = new CurrencyRateService();
            $service->backfillRates(1);

            $message = 'Currency rates updated successfully at ' . date('Y-m-d H:i:s');
            PrestaShopLogger::addLog($message, 1, null, 'CurrencyRateCron');

            $this->ajaxRender(json_encode([
                'status'  => 'ok',
                'message' => $message,
            ]));
        } catch (\Throwable $e) {
            $message = 'Currency rate cron error: ' . $e->getMessage();
            PrestaShopLogger::addLog($message, 3, null, 'CurrencyRateCron');

            http_response_code(500);
            $this->ajaxRender(json_encode([
                'status'  => 'error',
                'message' => $message,
            ]));
        }

        exit;
    }
}
