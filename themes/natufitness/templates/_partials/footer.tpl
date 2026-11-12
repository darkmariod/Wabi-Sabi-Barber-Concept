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
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" fill="currentColor"/></svg>
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

<a class="nf-whatsapp" href="https://wa.me/{$nf_whatsapp_number}" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp" title="Escríbenos por WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" fill="currentColor"/></svg>
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
