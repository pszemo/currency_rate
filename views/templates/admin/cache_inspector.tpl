{**
 * Currency Rate - Cache Inspector panel
 *}
<div class="panel currency-rate-cache-inspector" style="margin-top: 20px;">
  <div class="panel-heading">
    <i class="icon-database"></i>
    {l s='Diagnostyka cache' mod='currency_rate'}
  </div>

  <div class="panel-body">

    {if !$cache_enabled}

      {* Cache wyłączony - pokazujemy stan bazy danych *}
      <div class="alert alert-warning">
        <i class="icon-warning-sign"></i>
        {l s='Cache PrestaShop jest wyłączony. Poniżej widoczny jest aktualny stan bazy danych - dane te są pobierane przy każdym żądaniu bez buforowania.' mod='currency_rate'}
        <br/>
        <small>{l s='Aby włączyć cache: Zaawansowane → Wydajność → Użyj pamięci podręcznej.' mod='currency_rate'}</small>
      </div>

      <h5>{l s='Stan bazy danych' mod='currency_rate'}</h5>

      <table class="table table-bordered" style="max-width: 500px;">
        <tr>
          <td><strong>{l s='Łączna liczba rekordów kursów' mod='currency_rate'}</strong></td>
          <td>{$db_summary.total_rates}</td>
        </tr>
        <tr>
          <td><strong>{l s='Aktywne waluty' mod='currency_rate'}</strong></td>
          <td>{$db_summary.active_currencies}</td>
        </tr>
        <tr>
          <td><strong>{l s='Najnowszy kurs' mod='currency_rate'}</strong></td>
          <td>
            {$db_summary.latest_date}
            {if $db_summary.has_today_rates}
              <span class="label label-success">{l s='dzisiejszy' mod='currency_rate'}</span>
            {else}
              <span class="label label-warning">{l s='brak dzisiejszego' mod='currency_rate'}</span>
            {/if}
          </td>
        </tr>
        <tr>
          <td><strong>{l s='Najstarszy kurs' mod='currency_rate'}</strong></td>
          <td>{$db_summary.oldest_date}</td>
        </tr>
        <tr>
          <td><strong>{l s='TTL cache (gdy włączony)' mod='currency_rate'}</strong></td>
          <td>{$db_summary.ttl_hours}h</td>
        </tr>
      </table>

      {if !empty($db_summary.latest_rates)}
        <h5 style="margin-top: 15px;">{l s='Ostatnie kursy w bazie' mod='currency_rate'}</h5>
        <table class="table table-bordered table-sm table-striped" style="max-width: 400px;">
          <thead>
          <tr>
            <th>{l s='Waluta' mod='currency_rate'}</th>
            <th>{l s='Kurs (PLN)' mod='currency_rate'}</th>
            <th>{l s='Data' mod='currency_rate'}</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$db_summary.latest_rates item=rate}
            <tr>
              <td><strong>{$rate.currency_code|escape:'html':'UTF-8'}</strong></td>
              <td>{$rate.rate|string_format:"%.4f"}</td>
              <td>{$rate.date|escape:'html':'UTF-8'}</td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      {/if}

    {else}

      {* Cache włączony - pokazujemy klucze cache *}
      <p class="help-block">
        {l s='Poniżej widoczne są klucze cache związane z modułem Currency Rate.' mod='currency_rate'}
      </p>

      {if empty($cache_entries)}
        <div class="alert alert-info">
          <i class="icon-info-circle"></i>
          {l s='Cache jest pusty. Kursy zostaną zapisane do cache przy pierwszym żądaniu.' mod='currency_rate'}
        </div>
      {else}
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-striped">
            <thead>
            <tr>
              <th>{l s='Klucz' mod='currency_rate'}</th>
              <th>{l s='Podgląd wartości' mod='currency_rate'}</th>
              <th>{l s='Rozmiar' mod='currency_rate'}</th>
              <th>{l s='Wygasa' mod='currency_rate'}</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$cache_entries item=entry}
              <tr>
                <td><code style="font-size:11px;">{$entry.key|escape:'html':'UTF-8'}</code></td>
                <td><span class="text-muted" style="font-size:12px;">{$entry.preview|escape:'html':'UTF-8'}</span></td>
                <td class="text-right">
                  {if $entry.size_bytes > 1024}
                    {math equation="round(x/1024, 1)" x=$entry.size_bytes} KB
                  {else}
                    {$entry.size_bytes} B
                  {/if}
                </td>
                <td style="font-size:11px;">{$entry.expires_at|escape:'html':'UTF-8'}</td>
              </tr>
            {/foreach}
            </tbody>
          </table>
        </div>
      {/if}

      <form action="{$form_action|escape:'html':'UTF-8'}" method="post" style="margin-top:10px;">
        <button type="submit" name="submitInvalidateCache" class="btn btn-warning btn-sm">
          <i class="icon-trash"></i>
          {l s='Wyczyść cache modułu' mod='currency_rate'}
        </button>
      </form>

    {/if}

  </div>
</div>