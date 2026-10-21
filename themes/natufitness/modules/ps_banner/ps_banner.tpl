{*
 * "Potencia tu rendimiento" block fed by ps_banner (Módulos > Banner > Configurar):
 * the banner image and link, and the description as the side copy.
 *}
<div class="nf-promo-block">
  <a class="nf-promo-block__image" href="{$banner_link}">
    {if isset($banner_img)}
      <img src="{$banner_img}" alt="{$banner_desc}" width="{$banner_width}" height="{$banner_height}" loading="lazy">
    {/if}
  </a>
  <div class="nf-promo-block__copy">
    <h3>Suplementos deportivos y medicina natural en un solo lugar</h3>
    {if $banner_desc}<p>{$banner_desc}</p>{/if}
    <a class="nf-btn-buy nf-btn-buy--inline" href="{$banner_link}">Ver productos</a>
  </div>
</div>
