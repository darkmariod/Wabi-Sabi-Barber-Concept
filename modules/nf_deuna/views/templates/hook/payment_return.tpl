{*
 * Order confirmation block for DeUna payments: QR, exact amount, reference and
 * WhatsApp button to send the payment receipt.
 *}
<section class="nf-pay-return nf-pay-return--deuna">
  <h3 class="nf-pay-return__title">{l s='Paga con DeUna' d='Modules.Nfdeuna.Shop'}</h3>
  <div class="nf-pay-return__body">
    <figure class="nf-pay-return__qr">
      <img src="{$nf_deuna_qr_url|escape:'html':'UTF-8'}" alt="{l s='Código QR de DeUna' d='Modules.Nfdeuna.Shop'}" width="240" height="240" loading="lazy">
    </figure>
    <div class="nf-pay-return__info">
      <dl class="nf-pay-return__details">
        <dt>{l s='Monto exacto a pagar' d='Modules.Nfdeuna.Shop'}</dt>
        <dd class="nf-pay-return__amount">{$nf_deuna_total|escape:'html':'UTF-8'}</dd>
        <dt>{l s='Referencia del pedido' d='Modules.Nfdeuna.Shop'}</dt>
        <dd><strong>{$nf_deuna_reference|escape:'html':'UTF-8'}</strong></dd>
        {if $nf_deuna_holder}
          <dt>{l s='Titular' d='Modules.Nfdeuna.Shop'}</dt>
          <dd>{$nf_deuna_holder|escape:'html':'UTF-8'}</dd>
        {/if}
      </dl>
      {if $nf_deuna_instructions}
        <p class="nf-pay-return__instructions">{$nf_deuna_instructions|escape:'html':'UTF-8'|nl2br nofilter}</p>
      {/if}
      <a class="nf-btn-whatsapp" href="{$nf_deuna_whatsapp_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
        {l s='Enviar comprobante por WhatsApp' d='Modules.Nfdeuna.Shop'}
      </a>
      <p class="nf-pay-return__note">{l s='Tu pedido se procesará cuando confirmemos el pago.' d='Modules.Nfdeuna.Shop'}</p>
    </div>
  </div>
</section>
