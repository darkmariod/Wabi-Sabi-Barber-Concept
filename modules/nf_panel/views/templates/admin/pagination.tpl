{*
 * Mi Tienda: previous / next pagination. Expects $nf_page, $nf_pages, $nf_base_url, $nf_page_params.
 *}
{if $nf_pages > 1}
  <nav class="nfp-pager" aria-label="{l s='Páginas' d='Modules.Nfpanel.Admin'}">
    {if $nf_page > 1}
      <a class="nfp-btn nfp-btn--ghost" href="{$nf_base_url|escape:'html':'UTF-8'}{$nf_page_params|escape:'html':'UTF-8'}&amp;p={$nf_page-1}">‹ {l s='Anterior' d='Modules.Nfpanel.Admin'}</a>
    {/if}
    <span class="nfp-muted">{l s='Página %page% de %pages%' sprintf=['%page%' => $nf_page, '%pages%' => $nf_pages] d='Modules.Nfpanel.Admin'}</span>
    {if $nf_page < $nf_pages}
      <a class="nfp-btn nfp-btn--ghost" href="{$nf_base_url|escape:'html':'UTF-8'}{$nf_page_params|escape:'html':'UTF-8'}&amp;p={$nf_page+1}">{l s='Siguiente' d='Modules.Nfpanel.Admin'} ›</a>
    {/if}
  </nav>
{/if}
