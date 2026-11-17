{*
 * Mi Tienda panel layout: two big tabs (Productos / Pedidos) and the active section.
 *}
<div class="nfp" id="nfp">
  <nav class="nfp-tabs" aria-label="{l s='Secciones' d='Modules.Nfpanel.Admin'}">
    <a class="nfp-tab{if $nf_section == 'products'} is-active{/if}" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products">
      <i class="material-icons" aria-hidden="true">inventory_2</i>{l s='Productos' d='Modules.Nfpanel.Admin'}
    </a>
    <a class="nfp-tab{if $nf_section == 'orders'} is-active{/if}" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=orders">
      <i class="material-icons" aria-hidden="true">local_shipping</i>{l s='Pedidos' d='Modules.Nfpanel.Admin'}
    </a>
  </nav>
  {$nf_body nofilter}
</div>
