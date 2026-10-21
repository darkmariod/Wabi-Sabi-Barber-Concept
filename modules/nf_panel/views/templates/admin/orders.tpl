{*
 * Mi Tienda: order cards with one-tap state buttons.
 *}
<nav class="nfp-filters" aria-label="{l s='Filtrar pedidos' d='Modules.Nfpanel.Admin'}">
  {foreach $nf_filters as $key => $label}
    <a class="nfp-chip{if $nf_filter == $key} is-active{/if}" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=orders&amp;filter={$key|escape:'url'}">{$label|escape:'html':'UTF-8'}</a>
  {/foreach}
</nav>

<p class="nfp-count">{l s='%count% pedidos' sprintf=['%count%' => $nf_total] d='Modules.Nfpanel.Admin'}</p>

{if !$nf_orders}
  <div class="nfp-empty">{l s='No hay pedidos aquí por ahora.' d='Modules.Nfpanel.Admin'}</div>
{else}
  <div class="nfp-orders">
    {foreach $nf_orders as $o}
      <article class="nfp-order">
        <header class="nfp-order__head">
          <div>
            <strong class="nfp-order__ref">{$o.reference|escape:'html':'UTF-8'}</strong>
            <span class="nfp-muted">{$o.date|escape:'html':'UTF-8'}</span>
          </div>
          {if $o.state_name}
            <span class="nfp-state" style="background-color: {$o.state_color|escape:'html':'UTF-8'}">{$o.state_name|escape:'html':'UTF-8'}</span>
          {/if}
        </header>

        <div class="nfp-order__body">
          <p class="nfp-order__total">{$o.total|escape:'html':'UTF-8'}</p>
          <dl class="nfp-dl">
            <dt>{l s='Cliente' d='Modules.Nfpanel.Admin'}</dt>
            <dd>{$o.customer|escape:'html':'UTF-8'}{if $o.phone} · <a href="tel:{$o.phone|escape:'html':'UTF-8'}">{$o.phone|escape:'html':'UTF-8'}</a>{/if}</dd>
            <dt>{l s='Pago' d='Modules.Nfpanel.Admin'}</dt>
            <dd>{$o.payment|escape:'html':'UTF-8'}</dd>
            <dt>{l s='Entrega' d='Modules.Nfpanel.Admin'}</dt>
            <dd>
              {if $o.pickup}{l s='Retiro en local' d='Modules.Nfpanel.Admin'}{else}{l s='Envío a domicilio' d='Modules.Nfpanel.Admin'}{/if}
              {if !$o.pickup && $o.address}<br><span class="nfp-muted">{$o.address|escape:'html':'UTF-8'}</span>{/if}
            </dd>
            <dt>{l s='Productos' d='Modules.Nfpanel.Admin'}</dt>
            <dd>
              <ul class="nfp-lines">
                {foreach $o.products as $line}
                  <li>{$line.qty|intval} × {$line.name|escape:'html':'UTF-8'}</li>
                {/foreach}
              </ul>
            </dd>
          </dl>
          {if $o.invoice}
            <p class="nfp-invoice">
              <i class="material-icons" aria-hidden="true">receipt_long</i>
              {l s='Pidió factura:' d='Modules.Nfpanel.Admin'}
              {if $o.invoice.dni}{l s='Cédula/RUC %dni%' sprintf=['%dni%' => $o.invoice.dni] d='Modules.Nfpanel.Admin'}{/if}{if $o.invoice.dni && $o.invoice.company}, {/if}{if $o.invoice.company}{l s='Razón social %company%' sprintf=['%company%' => $o.invoice.company] d='Modules.Nfpanel.Admin'}{/if}
            </p>
          {/if}
        </div>

        <footer class="nfp-order__actions">
          {foreach $o.actions as $action}
            <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=orders&amp;filter={$nf_filter|escape:'url'}"{if $action == 'cancel'} data-nfp-confirm="{l s='¿Cancelar el pedido %ref%? Los productos vuelven al stock.' sprintf=['%ref%' => $o.reference] d='Modules.Nfpanel.Admin'}"{/if}>
              <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
              <input type="hidden" name="nf_action" value="order_state">
              <input type="hidden" name="id_order" value="{$o.id|intval}">
              <input type="hidden" name="state_action" value="{$action|escape:'html':'UTF-8'}">
              <input type="hidden" name="filter" value="{$nf_filter|escape:'html':'UTF-8'}">
              <input type="hidden" name="p" value="{$nf_page|intval}">
              {if $action == 'paid'}
                <button class="nfp-btn nfp-btn--accent" type="submit"><i class="material-icons" aria-hidden="true">paid</i>{l s='Pago recibido' d='Modules.Nfpanel.Admin'}</button>
              {elseif $action == 'ready'}
                <button class="nfp-btn nfp-btn--accent" type="submit"><i class="material-icons" aria-hidden="true">storefront</i>{l s='Listo para retirar' d='Modules.Nfpanel.Admin'}</button>
              {elseif $action == 'shipped'}
                <button class="nfp-btn nfp-btn--accent" type="submit"><i class="material-icons" aria-hidden="true">local_shipping</i>{l s='Enviado' d='Modules.Nfpanel.Admin'}</button>
              {elseif $action == 'delivered'}
                <button class="nfp-btn nfp-btn--primary" type="submit"><i class="material-icons" aria-hidden="true">done_all</i>{l s='Entregado' d='Modules.Nfpanel.Admin'}</button>
              {elseif $action == 'cancel'}
                <button class="nfp-btn nfp-btn--danger" type="submit"><i class="material-icons" aria-hidden="true">close</i>{l s='Cancelar' d='Modules.Nfpanel.Admin'}</button>
              {/if}
            </form>
          {/foreach}
          {if $o.whatsapp_url}
            <a class="nfp-btn nfp-btn--whatsapp" href="{$o.whatsapp_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer"><i class="material-icons" aria-hidden="true">chat</i>WhatsApp</a>
          {/if}
          {if $o.native_url}
            <a class="nfp-link" href="{$o.native_url|escape:'html':'UTF-8'}">{l s='Ver detalle completo' d='Modules.Nfpanel.Admin'}</a>
          {/if}
        </footer>
      </article>
    {/foreach}
  </div>
  {include file="./pagination.tpl" nf_page_params="&section=orders&filter={$nf_filter|escape:'url'}"}
{/if}
