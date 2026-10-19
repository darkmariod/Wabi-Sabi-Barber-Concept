{*
 * NatuFitness override of the ps_wirepayment order confirmation block.
 * Adds the "Enviar comprobante por WhatsApp" button. The number comes from the
 * NF_WHATSAPP_NUMBER setting (module nf_deuna), exposed as $modules.nf_deuna.
 *}
{if isset($modules.nf_deuna.whatsapp_number) && $modules.nf_deuna.whatsapp_number}
  {assign var='nf_wire_whatsapp' value=$modules.nf_deuna.whatsapp_number}
{else}
  {assign var='nf_wire_whatsapp' value='593000000000'}
{/if}
{capture assign='nf_wire_message'}Hola, adjunto comprobante de transferencia del pedido {$reference} por {$total}{/capture}
<section class="nf-pay-return nf-pay-return--wire">
  <h3 class="nf-pay-return__title">{l s='Paga por transferencia bancaria' d='Modules.Wirepayment.Shop'}</h3>
  <p>{l s='Your order on %s is complete.' sprintf=[$shop_name] d='Modules.Wirepayment.Shop'}<br>
    {l s='Please send us a bank wire with:' d='Modules.Wirepayment.Shop'}</p>
  {include file='module:ps_wirepayment/views/templates/hook/_partials/payment_infos.tpl'}
  <p>{l s='Please specify your order reference %s in the bankwire description.' sprintf=[$reference] d='Modules.Wirepayment.Shop'}</p>
  <a class="nf-btn-whatsapp" href="https://wa.me/{$nf_wire_whatsapp|escape:'url'}?text={$nf_wire_message|escape:'url'}" target="_blank" rel="noopener">
    {l s='Enviar comprobante por WhatsApp' d='Modules.Nfdeuna.Shop'}
  </a>
  <p class="nf-pay-return__note">{l s='Your order will be sent as soon as we receive payment.' d='Modules.Wirepayment.Shop'}</p>
</section>
