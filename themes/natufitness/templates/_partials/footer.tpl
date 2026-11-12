{include file='_partials/nf-config.tpl'}

<div class="nf-footer">
  <div class="nf-footer__left">
    <h4>Conectate con nosotros</h4>
    <div class="nf-socials">
      {if $nf_facebook_url}
        <a href="{$nf_facebook_url}" target="_blank" rel="noopener" aria-label="Facebook">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4h-3c-2.8 0-4 1.7-4 4.2V10H7v4h3v8h4v-8h3l1-4h-4V8.6c0-.4.3-.6.6-.6z" fill="currentColor"/></svg>
        </a>
      {/if}
      {if $nf_instagram_url}
        <a href="{$nf_instagram_url}" target="_blank" rel="noopener" aria-label="Instagram">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.3" fill="currentColor"/></svg>
        </a>
      {/if}
      {if $nf_tiktok_url}
        <a href="{$nf_tiktok_url}" target="_blank" rel="noopener" aria-label="TikTok">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h3c.3 2 1.6 3.4 4 3.6v3.2c-1.5 0-2.9-.4-4-1.1V15a6 6 0 11-6-6v3.3a2.7 2.7 0 102.9 2.7z" fill="currentColor"/></svg>
        </a>
      {/if}
      <a href="https://wa.me/{$nf_whatsapp_number}" target="_blank" rel="noopener" aria-label="WhatsApp">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
      </a>
    </div>
  </div>

  <div class="nf-footer__right">
    <div class="nf-footer__col">
      <h5>Ayuda e información</h5>
      <ul>
        <li><a href="{$urls.pages.contact}">Servicio al Cliente</a></li>
        <li><a href="{$urls.pages.stores}">Local Riobamba</a></li>
        <li><a href="{$urls.pages.order_follow}">Devoluciones</a></li>
        <li><a href="{$urls.pages.history}">Historial de pedidos</a></li>
      </ul>
    </div>
    <div class="nf-footer__col">
      <h5>Productos</h5>
      <ul>
        {widget_block name='ps_mainmenu'}
          {foreach from=$children|default:[] item='nf_node' name='nf_products'}
            {if $smarty.foreach.nf_products.iteration <= 5}
              <li><a href="{$nf_node.url}">{$nf_node.label}</a></li>
            {/if}
          {/foreach}
        {/widget_block}
      </ul>
    </div>
    <div class="nf-footer__col">
      <h5>Pagos seguros</h5>
      <ul>
        <li>Tarjetas vía Datafast</li>
        <li>DeUna QR</li>
        <li>Transferencia</li>
      </ul>
    </div>
    <div class="nf-footer__col">
      <h5>Políticas</h5>
      <ul>
        <li><a href="{$link->getCMSLink(3)}">Términos y condiciones</a></li>
        <li><a href="{$link->getCMSLink(1)}">Políticas de envío</a></li>
        <li><a href="{$link->getCMSLink(2)}">Aviso legal</a></li>
      </ul>
    </div>
    <div class="nf-footer__full">
      Bancos disponibles para transferencias
      <div class="nf-banks">
        <span>Pichincha</span><span>Guayaquil</span><span>Produbanco</span><span>Pacífico</span>
      </div>
    </div>
    <div class="nf-footer__full nf-footer__copy">
      © {'Y'|date} <b>NatuFitness</b>. Todos los derechos reservados.
      {assign var='nf_privacy_id' value=Configuration::get('NF_PRIVACY_CMS_ID')}
      {if $nf_privacy_id} · <a href="{$link->getCMSLink($nf_privacy_id)}">Privacidad y cookies</a>{/if}
      · Desarrollado por <a href="https://monkeycomputer.com" target="_blank" rel="noopener">MonkeyComputer</a>
    </div>
  </div>
</div>

{block name='hook_footer_after'}
  {hook h='displayFooterAfter'}
{/block}

<a class="nf-whatsapp" href="https://wa.me/{$nf_whatsapp_number}" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
  <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2z" fill="none" stroke="#fff" stroke-width="2"/></svg>
  Escríbenos
</a>

{* Cookie notice: shown until the visitor chooses; the choice lives in the nf_cookie_consent cookie (assets/js/custom.js). *}
{assign var='nf_privacy_id' value=Configuration::get('NF_PRIVACY_CMS_ID')}
<div class="nf-cookies" id="nf-cookies" role="dialog" aria-live="polite" aria-label="Aviso de cookies" hidden>
  <p class="nf-cookies__text">
    Usamos cookies necesarias para que la tienda funcione (carrito y sesión) y, con tu permiso, cookies de medición para mejorar tu experiencia.
    {if $nf_privacy_id}<a href="{$link->getCMSLink($nf_privacy_id)}">Política de privacidad y cookies</a>{/if}
  </p>
  <div class="nf-cookies__actions">
    <button type="button" class="nf-cookies__btn nf-cookies__btn--ghost" data-nf-consent="necessary">Solo necesarias</button>
    <button type="button" class="nf-cookies__btn" data-nf-consent="all">Aceptar todas</button>
  </div>
</div>
