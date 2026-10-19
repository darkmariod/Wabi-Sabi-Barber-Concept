{*
 * Minimal checkout header: NatuFitness logo and a WhatsApp help link, no navigation
 * (keeps the customer focused on finishing the order, like syf.ec).
 *}
{include file='_partials/nf-config.tpl'}

{block name='header_nav'}{/block}

{block name='header_top'}
  <div class="nf-header nf-checkout-header">
    <div class="nf-header__inner">
      <div class="nf-logo-wrap">
        {include file='_partials/nf-logo.tpl'}
      </div>
      <a class="nf-checkout-help" href="https://wa.me/{$nf_whatsapp_number}" target="_blank" rel="noopener">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <span><span class="hidden-sm-down">¿Necesitas ayuda? </span>Escríbenos por WhatsApp</span>
      </a>
    </div>
  </div>
{/block}
