{*
 * Shop logo shared by the storefront and checkout headers.
 * Renders the image uploaded in Diseño > Tema y logotipo (PS_LOGO); the text logo with
 * leaf badge is only a fallback when no logo file is configured.
 *}
<a class="nf-logo{if !empty($shop.logo_details.src)} nf-logo--image{/if}" href="{$urls.pages.index}" title="{$shop.name}">
  {if !empty($shop.logo_details.src)}
    <img class="nf-logo__img" src="{$shop.logo_details.src}" alt="{$shop.name}" width="{$shop.logo_details.width}" height="{$shop.logo_details.height}">
  {else}
    <span class="nf-logo__badge" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24"><path d="M5 19c0-8 6-13 15-14-1 9-6 15-14 15" fill="#6cbf3c"/></svg>
    </span>
    <span class="nf-logo__text">Natu<b>Fitness</b></span>
  {/if}
</a>
