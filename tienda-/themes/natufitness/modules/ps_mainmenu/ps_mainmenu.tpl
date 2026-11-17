{*
 * NatuFitness main menu, syf.ec style:
 *  - NUESTROS PRODUCTOS: mega menu, top categories on the left, the hovered one's
 *    subcategories on the right (categories come from Módulos > Menú principal).
 *  - PROMOCIONES: products on offer with photo and struck price.
 *  - MARCAS: brands page.
 * The same markup becomes an accordion on phones (classic moves it into the mobile menu).
 * Behaviour: assets/js/custom.js (initMegaMenu).
 *}
{assign var='nf_promos' value=Product::getPricesDrop((int)$language.id, 0, 10)}
<div class="menu js-top-menu position-static hidden-sm-down" id="_desktop_top_menu">
  <ul class="nf-mega" id="top-menu" data-depth="0">
    <li class="nf-mega__item js-nf-mega">
      <button type="button" class="nf-mega__trigger" aria-expanded="false" aria-controls="nf-mega-products">Nuestros productos</button>
      <div class="nf-mega__panel" id="nf-mega-products">
        <ul class="nf-mega__cats">
          {foreach from=$menu.children item='nf_cat' name='nf_cats'}
            <li class="nf-mega__cat js-nf-mega-cat{if $smarty.foreach.nf_cats.first} is-active{/if}">
              <a href="{$nf_cat.url}" class="nf-mega__cat-link"{if $nf_cat.children|count} aria-haspopup="true"{/if}>{$nf_cat.label}</a>
              {if $nf_cat.children|count}
                <button type="button" class="nf-mega__cat-toggle js-nf-mega-toggle" aria-label="Ver {$nf_cat.label}" aria-expanded="false"></button>
                <div class="nf-mega__subs">
                  <a href="{$nf_cat.url}" class="nf-mega__all">Ver todo {$nf_cat.label}</a>
                  <ul>
                    {foreach from=$nf_cat.children item='nf_sub'}
                      <li><a href="{$nf_sub.url}">{$nf_sub.label}</a></li>
                    {/foreach}
                  </ul>
                </div>
              {/if}
            </li>
          {/foreach}
        </ul>
      </div>
    </li>
    <li class="nf-mega__item js-nf-mega">
      <button type="button" class="nf-mega__trigger" aria-expanded="false" aria-controls="nf-mega-promos">Promociones</button>
      <div class="nf-mega__panel nf-mega__panel--promos" id="nf-mega-promos">
        {if $nf_promos}
          <ul class="nf-promos">
            {foreach from=$nf_promos item='nf_p'}
              <li>
                <a href="{$nf_p.link}" class="nf-promos__card">
                  <span class="nf-promos__img">
                    {if $nf_p.id_image}
                      <img src="{$link->getImageLink($nf_p.link_rewrite, $nf_p.id_image, 'home_default')}" alt="{$nf_p.name}" loading="lazy" width="120" height="120">
                    {else}
                      <img src="{$urls.no_picture_image.bySize.home_default.url}" alt="{$nf_p.name}" loading="lazy" width="120" height="120">
                    {/if}
                  </span>
                  <span class="nf-promos__name">{$nf_p.name}</span>
                  <span class="nf-promos__price">
                    <b>{Tools::displayPrice($nf_p.price)}</b>
                    {if $nf_p.price_without_reduction > $nf_p.price}<s>{Tools::displayPrice($nf_p.price_without_reduction)}</s>{/if}
                  </span>
                </a>
              </li>
            {/foreach}
          </ul>
        {/if}
        <a class="nf-mega__all" href="{$urls.pages.prices_drop}">Ver todas las promociones</a>
      </div>
    </li>
    <li class="nf-mega__item">
      <a class="nf-mega__trigger" href="{$urls.pages.manufacturer}">Marcas</a>
    </li>
  </ul>
  <div class="clearfix"></div>
</div>
