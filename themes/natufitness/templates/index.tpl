{extends file='page.tpl'}

{block name='page_content_container'}
  <section id="content" class="page-home nf-home">
    {block name='page_content_top'}{/block}

    {block name='page_content'}
      {* ps_mainmenu widget variables expose the menu tree root: $children are the top-level items. *}
      {widget_block name='ps_mainmenu'}
        {assign var='nf_categories' value=$children|default:[]}
        {if $nf_categories|count}
          {assign var='nf_primary_url' value=$nf_categories[0].url}
        {else}
          {assign var='nf_primary_url' value=$urls.pages.prices_drop}
        {/if}

        {block name='nf_hero'}
          <section class="nf-hero js-nf-hero" aria-roledescription="carrusel" aria-label="Destacados">
            <div class="nf-hero__slide is-active nf-hero__slide--1">
              <svg class="nf-hero__jar" viewBox="0 0 120 150" aria-hidden="true" style="color:#17507d"><use href="#nf-jar"/></svg>
              <div class="nf-hero__text">
                <small>DISTRIBUIDORES AUTORIZADOS</small>
                <p class="nf-hero__title">SALUD, FUERZA<br>Y <em>VITALIDAD</em></p>
                <a class="nf-btn-dark" href="{$nf_primary_url}">VER MÁS ›</a>
              </div>
            </div>
            <div class="nf-hero__slide nf-hero__slide--2">
              <svg class="nf-hero__jar" viewBox="0 0 120 150" aria-hidden="true" style="color:#4e9a26"><use href="#nf-jar"/></svg>
              <div class="nf-hero__text">
                <small>MEDICINA NATURAL</small>
                <p class="nf-hero__title">LO MEJOR DE<br>LA <em>NATURALEZA</em></p>
                <a class="nf-btn-dark" href="{if $nf_categories|count > 1}{$nf_categories[1].url}{else}{$nf_primary_url}{/if}">VER MÁS ›</a>
              </div>
            </div>
            <div class="nf-hero__slide nf-hero__slide--3">
              <svg class="nf-hero__jar" viewBox="0 0 120 150" aria-hidden="true" style="color:#0f3a5c"><use href="#nf-jar"/></svg>
              <div class="nf-hero__text">
                <small>ENVÍO GRATIS</small>
                <p class="nf-hero__title">EN COMPRAS<br>DESDE <em>${$nf_free_shipping_amount}</em></p>
                <a class="nf-btn-dark" href="{$nf_primary_url}">VER MÁS ›</a>
              </div>
            </div>
            <button type="button" class="nf-hero__arrow nf-hero__arrow--prev js-nf-hero-prev" aria-label="Anterior">‹</button>
            <button type="button" class="nf-hero__arrow nf-hero__arrow--next js-nf-hero-next" aria-label="Siguiente">›</button>
            <div class="nf-hero__dots js-nf-hero-dots"></div>
          </section>
          <svg width="0" height="0" style="position:absolute" aria-hidden="true">
            <defs>
              <symbol id="nf-jar" viewBox="0 0 120 150">
                <rect x="22" y="6" width="76" height="20" rx="4" fill="#111"/>
                <rect x="14" y="24" width="92" height="120" rx="10" fill="currentColor"/>
                <rect x="14" y="58" width="92" height="52" fill="#fff" opacity=".92"/>
                <rect x="24" y="68" width="60" height="9" rx="2" fill="currentColor"/>
                <rect x="24" y="83" width="42" height="6" rx="2" fill="#999"/>
                <rect x="24" y="94" width="50" height="6" rx="2" fill="#999"/>
              </symbol>
            </defs>
          </svg>
        {/block}

        {block name='hook_home'}
          {capture name='nf_home_hook'}{$HOOK_HOME nofilter}{/capture}
          {if $smarty.capture.nf_home_hook|trim}
            {$smarty.capture.nf_home_hook nofilter}
          {else}
            <section class="nf-section nf-promos">
              <h2 class="nf-section-title">Promociones Exclusivas</h2>
              <p class="nf-empty">Muy pronto publicaremos nuestras promociones. Escríbenos por WhatsApp para conocer las ofertas del día.</p>
            </section>
          {/if}
        {/block}

        {block name='nf_brands'}
          <section class="nf-section">
            <h2 class="nf-section-title">Marcas Oficiales</h2>
            <div class="nf-band">
              <div class="nf-tiles nf-tiles--brands">
                {foreach from=['ON', 'BSN', 'MUSCLETECH', 'UNIVERSAL', 'DYMATIZE'] item='nf_brand'}
                  <a class="nf-tile nf-tile--brand" href="{$urls.pages.manufacturer}">{$nf_brand}</a>
                {/foreach}
              </div>
            </div>
          </section>
        {/block}

        {block name='nf_promo_block'}
          <section class="nf-section">
            <h2 class="nf-section-title">Potencia tu rendimiento</h2>
            <div class="nf-promo-block">
              <div class="nf-promo-block__image">
                <svg viewBox="0 0 120 150" aria-hidden="true" style="color:#0f3a5c"><use href="#nf-jar"/></svg>
              </div>
              <div class="nf-promo-block__copy">
                <h3>Suplementos deportivos y medicina natural en un solo lugar</h3>
                <p>Proteínas, creatinas, quemadores, vitaminas y productos naturistas originales. Asesoría personalizada en nuestro local de Riobamba o por WhatsApp.</p>
                <a class="nf-btn-buy nf-btn-buy--inline" href="{$nf_primary_url}">Ver productos</a>
              </div>
            </div>
          </section>
        {/block}

        {block name='nf_categories'}
          {if $nf_categories|count}
            <section class="nf-section">
              <h2 class="nf-section-title">Categorías</h2>
              <div class="nf-band">
                <div class="nf-tiles nf-tiles--categories">
                  {foreach from=$nf_categories item='nf_category'}
                    <a class="nf-tile nf-tile--category" href="{$nf_category.url}">{$nf_category.label}</a>
                  {/foreach}
                </div>
              </div>
            </section>
          {/if}
        {/block}
      {/widget_block}
    {/block}
  </section>
{/block}
