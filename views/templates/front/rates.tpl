{**
 * Currency Rate - Historical rates page
 *}
{extends file='page.tpl'}

{block name='page_title'}
  {l s='Kursy walut NBP - ostatnie 30 dni' mod='currency_rate'}
{/block}

{block name='page_content'}
<div class="currency-rate-page">

  {if empty($currency_rate_rates)}
    <div class="alert alert-warning">
      {l s='Brak danych o kursach walut. Skontaktuj się z administratorem.' mod='currency_rate'}
    </div>
  {else}

    {* Sorting helpers *}
    {function name="sort_url" field="" label=""}
      {assign var="new_dir" value=($currency_rate_sort_field == $field && $currency_rate_sort_dir == 'ASC') ? 'DESC' : 'ASC'}
      <a href="{$currency_rate_page_url}?sort={$field}&dir={$new_dir}&page=1" class="sort-link">
        {$label}
        {if $currency_rate_sort_field == $field}
          <span class="sort-icon">{if $currency_rate_sort_dir == 'ASC'}▲{else}▼{/if}</span>
        {/if}
      </a>
    {/function}

    <div class="table-responsive">
      <table class="table table-striped table-bordered currency-rate-table">
        <thead class="thead-dark">
          <tr>
            <th>{call name="sort_url" field="date" label={l s='Data' mod='currency_rate'}}</th>
            <th>{call name="sort_url" field="currency_code" label={l s='Waluta' mod='currency_rate'}}</th>
            <th>{l s='Nazwa' mod='currency_rate'}</th>
            <th>{call name="sort_url" field="rate" label={l s='Kurs (PLN)' mod='currency_rate'}}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$currency_rate_rates item=rate}
          <tr>
            <td>{$rate.date|escape:'html':'UTF-8'}</td>
            <td><strong>{$rate.currency_code|escape:'html':'UTF-8'}</strong></td>
            <td>{$rate.currency_name|escape:'html':'UTF-8'}</td>
            <td class="rate-value">{$rate.rate|string_format:"%.4f"}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    {* Pagination *}
    {if $currency_rate_pages > 1}
    <nav class="currency-rate-pagination" aria-label="{l s='Nawigacja stron' mod='currency_rate'}">
      <ul class="pagination justify-content-center">

        {* Previous *}
        <li class="page-item {if $currency_rate_page <= 1}disabled{/if}">
          <a class="page-link" href="{$currency_rate_page_url}?sort={$currency_rate_sort_field}&dir={$currency_rate_sort_dir}&page={$currency_rate_page - 1}">
            &laquo;
          </a>
        </li>

        {* Pages *}
        {for $p = 1 to $currency_rate_pages}
          <li class="page-item {if $p == $currency_rate_page}active{/if}">
            <a class="page-link" href="{$currency_rate_page_url}?sort={$currency_rate_sort_field}&dir={$currency_rate_sort_dir}&page={$p}">
              {$p}
            </a>
          </li>
        {/for}

        {* Next *}
        <li class="page-item {if $currency_rate_page >= $currency_rate_pages}disabled{/if}">
          <a class="page-link" href="{$currency_rate_page_url}?sort={$currency_rate_sort_field}&dir={$currency_rate_sort_dir}&page={$currency_rate_page + 1}">
            &raquo;
          </a>
        </li>

      </ul>
      <p class="text-center text-muted">
        {l s='Łącznie rekordów:' mod='currency_rate'} <strong>{$currency_rate_total}</strong>
      </p>
    </nav>
    {/if}

  {/if}
</div>
{/block}
