<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCurrencyRateController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules') . '&configure=currency_rate'
        );
    }
}
