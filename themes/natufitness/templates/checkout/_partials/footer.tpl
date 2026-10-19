{*
 * Checkout footer: keeps the classic modal used by the terms link, replaces the
 * PrestaShop credit with the NatuFitness copyright and policy links.
 *}
<div class="modal fade js-checkout-modal" id="modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
        <span aria-hidden="true">&times;</span>
      </button>
      <div class="js-modal-content"></div>
    </div>
  </div>
</div>

<div class="nf-checkout-footer">
  {if $tos_cms != false}
    <span class="d-block js-terms">{$tos_cms nofilter}</span>
  {/if}
  {block name='copyright_link'}
    <p class="nf-checkout-footer__copy">© {'Y'|date} <b>NatuFitness</b>. Todos los derechos reservados.</p>
    <p class="nf-checkout-footer__links">
      {if $tos_cms == false}
        <a href="{$link->getCMSLink(3)}">Términos y condiciones</a>
        <span aria-hidden="true">·</span>
      {/if}
      <a href="{$link->getCMSLink(1)}">Políticas de envío</a>
    </p>
  {/block}
</div>
