{extends file='page.tpl'}

{block name='page_content_container'}
  <section id="content" class="page-home nf-home">
    {block name='page_content_top'}{/block}

    {block name='page_content'}
      {* ps_mainmenu widget variables expose the menu tree root: $children are the top-level items. *}
      {widget_block name='ps_mainmenu'}
        {assign var='nf_categories' value=$children|default:[]}

        {block name='nf_hero'}
          {* Slides come from ps_imageslider; markup in modules/ps_imageslider/views/templates/hook/slider.tpl. *}
          {widget name='ps_imageslider'}
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

        {block name='nf_bestsellers'}
          {* Real best sellers; until there are sales, the newest catalog products (same carousel). *}
          {capture assign='nf_best'}{widget name='ps_bestsellers'}{/capture}
          {if $nf_best|trim}{$nf_best nofilter}{else}{widget name='ps_newproducts'}{/if}
        {/block}

        {block name='nf_brands'}
          {* Active brands from Catálogo > Marcas y proveedores: logo when uploaded, name otherwise. *}
          {assign var='nf_brands' value=Manufacturer::getManufacturers(false, $language.id, true)}
          {if $nf_brands}
            <section class="nf-section">
              <h2 class="nf-section-title">Distribuidores autorizados</h2>
              <div class="nf-band nf-band--brands">
                {* Logos scroll like syf.ec partners; the list is repeated once so the loop is seamless. *}
                <div class="nf-marquee">
                  <div class="nf-marquee__track">
                    {foreach from=[1,2] item='nf_pass'}
                      {foreach from=$nf_brands item='nf_brand'}
                        {assign var='nf_brand_logo' value="`$smarty.const._PS_MANU_IMG_DIR_``$nf_brand.id_manufacturer`.jpg"}
                        <a class="nf-brand" href="{$link->getManufacturerLink($nf_brand.id_manufacturer, $nf_brand.link_rewrite|default:null)}" title="{$nf_brand.name}"{if $nf_pass == 2} aria-hidden="true" tabindex="-1"{/if}>
                          {if Tools::file_exists_cache($nf_brand_logo)}
                            <img src="{$urls.img_manu_url}{$nf_brand.id_manufacturer}.jpg" alt="{$nf_brand.name}" loading="lazy" width="200" height="100">
                          {else}
                            <span class="nf-brand__name">{$nf_brand.name}</span>
                          {/if}
                        </a>
                      {/foreach}
                    {/foreach}
                  </div>
                </div>
              </div>
            </section>
          {/if}
        {/block}

        {block name='nf_promo_block'}
          {* Image, link and side copy come from ps_banner; markup in modules/ps_banner/ps_banner.tpl. *}
          {capture name='nf_promo'}{widget name='ps_banner'}{/capture}
          {if $smarty.capture.nf_promo|trim}
            <section class="nf-section">
              <h2 class="nf-section-title">Potencia tu rendimiento</h2>
              {$smarty.capture.nf_promo nofilter}
            </section>
          {/if}
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
