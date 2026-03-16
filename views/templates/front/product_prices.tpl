{**
 * Currency Rate - Product page price conversions widget
 *}
{if !empty($currency_rate_conversions)}
<section class="currency-rate-widget product-additional-info">
  <h4 class="currency-rate-widget__title">
    {l s='Cena w innych walutach' mod='currency_rate'}
  </h4>
  <div class="table-responsive">
    <table class="table table-sm currency-rate-widget__table">
      <thead>
        <tr>
          <th>{l s='Waluta' mod='currency_rate'}</th>
          <th>{l s='Cena' mod='currency_rate'}</th>
          <th class="d-none d-md-table-cell">
            {l s='Kurs' mod='currency_rate'} ({$currency_rate_base_code})
          </th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$currency_rate_conversions item=conv}
        <tr>
          <td>
            <strong>{$conv.code|escape:'html':'UTF-8'}</strong>
            <span class="text-muted"> - {$conv.name|escape:'html':'UTF-8'}</span>
          </td>
          <td class="font-weight-bold">
            {$conv.price|string_format:"%.2f"} {$conv.code|escape:'html':'UTF-8'}
          </td>
          <td class="d-none d-md-table-cell text-muted">
            {$conv.rate|string_format:"%.4f"}
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
  </div>
  <p class="currency-rate-widget__note text-muted small">
    {l s='Przeliczenie według kursu NBP. Ceny mają charakter informacyjny.' mod='currency_rate'}
  </p>
</section>
{/if}
