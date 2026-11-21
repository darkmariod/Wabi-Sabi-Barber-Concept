{*
 * Order confirmation: adds a "request invoice via WhatsApp" card (Phase 1).
 * Electronic SRI invoicing is issued manually until Phase 2 automates it.
 *}
{extends file='parent:checkout/order-confirmation.tpl'}

{*
 * Header: the customer sends the full order to the store's WhatsApp with one tap, so the
 * owner gets it instantly (no API needed). Replaces "Se ha enviado un email", because the
 * shop does not send emails yet.
 *}
{block name='order_confirmation_header'}
  {include file='_partials/nf-config.tpl'}
  {assign var='nf_ship' value=$order.addresses.delivery|default:[]}
  {assign var='nf_ship_phone' value=$nf_ship.phone_mobile|default:$nf_ship.phone|default:''}
  {capture assign='nf_order_message'}Hola NatuFitness, acabo de hacer un pedido en la tienda:
Pedido: {$order.details.reference}
{foreach from=$order.products item='nf_line'}• {$nf_line.quantity} x {$nf_line.name}
{/foreach}Total: {$order.totals.total.value}
Pago: {$order.details.payment}
Entrega: {$order.carrier.name}{if $order.carrier.name|lower|strpos:'retiro' === false} - {$nf_ship.address1|default:''}, {$nf_ship.city|default:''}{/if}

Nombre: {$nf_ship.firstname|default:''} {$nf_ship.lastname|default:''}{if $nf_ship_phone} - Teléfono: {$nf_ship_phone}{/if}{/capture}
  <h3 class="h1 card-title nf-confirm__title">
    <i class="material-icons rtl-no-flip done" aria-hidden="true">&#xE876;</i>¡Gracias! Tu pedido está registrado
  </h3>
  <p class="nf-confirm__text">Último paso: <b>envíanos tu pedido por WhatsApp</b> para confirmarlo y coordinar el pago y la entrega.</p>
  <a class="nf-confirm__wa" href="https://wa.me/{$nf_whatsapp_number}?text={$nf_order_message|trim|escape:'url'}" target="_blank" rel="noopener">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2a9.9 9.9 0 0 0-8.5 14.95L2 22l5.2-1.5A9.9 9.9 0 1 0 12.04 2zm0 18.1a8.2 8.2 0 0 1-4.2-1.15l-.3-.18-3.1.9.9-3-.2-.31a8.2 8.2 0 1 1 6.9 3.74zm4.5-6.14c-.25-.12-1.46-.72-1.69-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48a.92.92 0 0 0-.66.31 2.78 2.78 0 0 0-.87 2.07c0 1.22.89 2.4 1.01 2.57.12.16 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.6.19 1.14.16 1.57.1.48-.07 1.46-.6 1.67-1.18.2-.58.2-1.07.14-1.18-.06-.1-.22-.16-.47-.29z"/></svg>
    Enviar mi pedido por WhatsApp
  </a>
{/block}

{block name='hook_payment_return' append}
  {include file='_partials/nf-config.tpl'}

  {assign var='nf_invoice_address' value=$order.addresses.invoice|default:[]}
  {assign var='nf_invoice_id' value=$nf_invoice_address.dni|default:''}
  {assign var='nf_invoice_company' value=$nf_invoice_address.company|default:''}
  {assign var='nf_invoice_name' value="{$nf_invoice_address.firstname|default:''} {$nf_invoice_address.lastname|default:''}"|trim}

  {capture assign='nf_invoice_message'}Hola, solicito factura del pedido {$order.details.reference} por {$order.totals.total.value}{if $nf_invoice_id} - Cédula/RUC: {$nf_invoice_id} - Nombre o razón social: {if $nf_invoice_company}{$nf_invoice_company}{else}{$nf_invoice_name}{/if}{else} - Te envío mis datos: cédula/RUC, nombre o razón social y dirección{/if} - Correo: {$customer.email}{/capture}

  <section class="card nf-invoice-request">
    <div class="card-block">
      <h3 class="nf-invoice-request__title">¿Necesitas factura?</h3>
      {if $nf_invoice_id}
        <p class="nf-invoice-request__text">
          Pídela por WhatsApp con tus datos de facturación (Cédula/RUC {$nf_invoice_id}). Te la enviamos a tu correo.
        </p>
      {else}
        <p class="nf-invoice-request__text">
          Escríbenos por WhatsApp con tu cédula o RUC, nombre o razón social y dirección. Te la enviamos a tu correo.
        </p>
      {/if}
      <a
        class="nf-btn-whatsapp"
        href="https://wa.me/{$nf_whatsapp_number}?text={$nf_invoice_message|trim|escape:'url'}"
        target="_blank"
        rel="noopener"
      >
        Solicitar factura por WhatsApp
      </a>
    </div>
  </section>
{/block}
