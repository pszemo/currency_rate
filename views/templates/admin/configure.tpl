{**
 * Currency Rate - Back Office configuration panel
 *}
<div class="panel currency-rate-config">
  <div class="panel-heading">
    <i class="icon-money"></i>
    {l s='Konfiguracja modułu Currency Rate' mod='currency_rate'}
  </div>

  <div class="panel-body">

    {* Active currencies form *}
    <form action="{$form_action|escape:'html':'UTF-8'}" method="post">
      <h4>{l s='Aktywne waluty' mod='currency_rate'}</h4>
      <p class="help-block">
        {l s='Wybierz waluty, które będą wyświetlane w tabelach kursów.' mod='currency_rate'}
      </p>

      <div class="row">
        {foreach from=$all_currencies item=currency}
          <div class="col-xs-6 col-sm-4 col-md-3">
            <div class="checkbox">
              <label>
                <input
                        type="checkbox"
                        name="active_currencies[]"
                        value="{$currency.currency_code|escape:'html':'UTF-8'}"
                        {if in_array($currency.currency_code, $active_codes)}checked="checked"{/if}
                />
                <strong>{$currency.currency_code|escape:'html':'UTF-8'}</strong>
                - {$currency.currency_name|escape:'html':'UTF-8'}
              </label>
            </div>
          </div>
        {/foreach}
      </div>

      <div class="panel-footer">
        <button type="submit" name="submitCurrencyRateConfig" class="btn btn-default pull-right">
          <i class="process-icon-save"></i>
          {l s='Zapisz' mod='currency_rate'}
        </button>
      </div>
    </form>

    <hr />

    {* Manual fetch button *}
    <form action="{$form_action|escape:'html':'UTF-8'}" method="post">
      <h4>{l s='Ręczne pobieranie kursów' mod='currency_rate'}</h4>
      <p class="help-block">
        {l s='Pobierz kursy z ostatnich 30 dni z API NBP. Operacja może chwilę potrwać.' mod='currency_rate'}
      </p>
      <button type="submit" name="submitFetchNow" class="btn btn-primary">
        <i class="icon-refresh"></i>
        {l s='Pobierz teraz (30 dni)' mod='currency_rate'}
      </button>
    </form>

    <hr />

    {* Clear rates *}
    <h4>{l s='Zarządzanie danymi' mod='currency_rate'}</h4>
    <p class="help-block">
      {l s='Usuń wszystkie zapisane kursy z bazy danych.' mod='currency_rate'}
    </p>
    <form action="{$form_action|escape:'html':'UTF-8'}" method="post" onsubmit="return confirm('{l s='Czy na pewno chcesz usunąć wszystkie kursy?' mod='currency_rate'}');">
      <button type="submit" name="submitClearRates" class="btn btn-danger">
        <i class="icon-trash"></i>
        {l s='Wyczyść kursy' mod='currency_rate'}
      </button>
      &nbsp;
      <button type="submit" name="submitClearAndRefetch" class="btn btn-warning">
        <i class="icon-refresh"></i>
        {l s='Wyczyść i pobierz ponownie (30 dni)' mod='currency_rate'}
      </button>
    </form>

    <hr />
    <h4>{l s='Linki' mod='currency_rate'}</h4>
    <p>
      <a href="{$rates_page_url|escape:'html':'UTF-8'}" target="_blank" class="btn btn-default btn-sm">
        <i class="icon-external-link"></i>
        {l s='Strona z kursami walut (FO)' mod='currency_rate'}
      </a>
    </p>

    <hr />
    <h4>{l s='Automatyczna aktualizacja (Cron)' mod='currency_rate'}</h4>
    <p class="help-block">
      {l s='Dodaj poniższe polecenie do crontaba serwera, aby kursy były aktualizowane automatycznie raz dziennie o 6:00.' mod='currency_rate'}
    </p>
    <pre style="background:#f5f5f5; padding:10px; border-radius:4px; font-size:12px; word-break:break-all;">0 6 * * * curl -s "{$cron_url|escape:'html':'UTF-8'}" &gt;&gt; /var/log/currency_rate_cron.log 2&gt;&amp;1</pre>
    <p class="help-block">
      {l s='Token:' mod='currency_rate'} <code>{$cron_token|escape:'html':'UTF-8'}</code>
    </p>

  </div>
</div>
