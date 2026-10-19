{*
 * Order confirmation: adds a "request invoice via WhatsApp" card (Phase 1).
 * Electronic SRI invoicing is issued manually until Phase 2 automates it.
 *}
{extends file='parent:checkout/order-confirmation.tpl'}

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
