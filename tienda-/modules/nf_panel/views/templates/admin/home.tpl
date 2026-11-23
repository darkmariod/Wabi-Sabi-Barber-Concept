{*
 * Mi Tienda > Portada: home banners (carousel) and the products in "Promociones Exclusivas".
 *}
<section class="nfp-home">
  <h2 class="nfp-section-title"><i class="material-icons" aria-hidden="true">view_carousel</i>{l s='Banners de la portada' d='Modules.Nfpanel.Admin'}</h2>
  <p class="nfp-help">{l s='Son las fotos grandes que pasan solas al entrar a la tienda. Medida ideal: 1920 × 600 px (horizontal), JPG o PNG de hasta %mb% MB.' sprintf=['%mb%' => $nf_max_banner_mb] d='Modules.Nfpanel.Admin'}</p>

  <form class="nfp-form nfp-banner-form" method="post" enctype="multipart/form-data" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=home">
    <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
    <input type="hidden" name="nf_action" value="add_banner">
    <div class="nfp-field">
      <label for="nfp-banner">{l s='Imagen del banner' d='Modules.Nfpanel.Admin'} <span class="nfp-req">*</span></label>
      <input id="nfp-banner" type="file" name="banner" accept="image/jpeg,image/png,image/webp" required>
    </div>
    <div class="nfp-field">
      <label for="nfp-banner-title">{l s='Título (opcional, solo para ti)' d='Modules.Nfpanel.Admin'}</label>
      <input id="nfp-banner-title" type="text" name="title" maxlength="120" placeholder="{l s='Ej.: Promo proteínas noviembre' d='Modules.Nfpanel.Admin'}">
    </div>
    <div class="nfp-field">
      <label for="nfp-banner-url">{l s='Al tocar el banner, ir a (opcional)' d='Modules.Nfpanel.Admin'}</label>
      <input id="nfp-banner-url" type="url" name="url" maxlength="255" placeholder="https://natufitness.sevenbarber.app/11-proteinas">
    </div>
    <button class="nfp-btn nfp-btn--accent nfp-btn--big" type="submit"><i class="material-icons" aria-hidden="true">add_photo_alternate</i>{l s='Agregar banner' d='Modules.Nfpanel.Admin'}</button>
  </form>

  {if !$nf_banners}
    <div class="nfp-empty">{l s='No hay banners. Agrega el primero arriba.' d='Modules.Nfpanel.Admin'}</div>
  {else}
    <ul class="nfp-list nfp-banners">
      {foreach $nf_banners as $b}
        <li class="nfp-item{if !$b.active} is-hidden{/if}">
          <div class="nfp-item__thumb nfp-item__thumb--wide">
            {if $b.image_url}<img src="{$b.image_url|escape:'html':'UTF-8'}" alt="" loading="lazy" width="192" height="60">{/if}
          </div>
          <div class="nfp-item__info">
            <strong class="nfp-item__name">{if $b.title}{$b.title|escape:'html':'UTF-8'}{else}{l s='Banner %n%' sprintf=['%n%' => $b@iteration] d='Modules.Nfpanel.Admin'}{/if}</strong>
            <div class="nfp-item__meta">
              {if $b.active}
                <span class="nfp-badge nfp-badge--on">{l s='Visible' d='Modules.Nfpanel.Admin'}</span>
              {else}
                <span class="nfp-badge nfp-badge--off">{l s='Oculto' d='Modules.Nfpanel.Admin'}</span>
              {/if}
              {if $b.url}<span class="nfp-muted">{l s='Lleva a:' d='Modules.Nfpanel.Admin'} {$b.url|truncate:48:'…'|escape:'html':'UTF-8'}</span>{/if}
            </div>
          </div>
          <div class="nfp-item__actions">
            {foreach [['up', -1, 'arrow_upward', 'Subir'], ['down', 1, 'arrow_downward', 'Bajar']] as $m}
              {if ($m[0] == 'up' && !$b.first) || ($m[0] == 'down' && !$b.last)}
                <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=home">
                  <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
                  <input type="hidden" name="nf_action" value="move_banner">
                  <input type="hidden" name="id" value="{$b.id|intval}">
                  <input type="hidden" name="direction" value="{$m[1]|intval}">
                  <button class="nfp-btn nfp-btn--ghost" type="submit" title="{$m[3]}"><i class="material-icons" aria-hidden="true">{$m[2]}</i>{$m[3]}</button>
                </form>
              {/if}
            {/foreach}
            <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=home">
              <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
              <input type="hidden" name="nf_action" value="toggle_banner">
              <input type="hidden" name="id" value="{$b.id|intval}">
              <button class="nfp-btn nfp-btn--ghost" type="submit">
                {if $b.active}
                  <i class="material-icons" aria-hidden="true">visibility_off</i>{l s='Ocultar' d='Modules.Nfpanel.Admin'}
                {else}
                  <i class="material-icons" aria-hidden="true">visibility</i>{l s='Mostrar' d='Modules.Nfpanel.Admin'}
                {/if}
              </button>
            </form>
            <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=home" data-nfp-confirm="{l s='¿Eliminar este banner? No se puede deshacer.' d='Modules.Nfpanel.Admin'}">
              <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
              <input type="hidden" name="nf_action" value="delete_banner">
              <input type="hidden" name="id" value="{$b.id|intval}">
              <button class="nfp-btn nfp-btn--danger" type="submit"><i class="material-icons" aria-hidden="true">delete</i>{l s='Eliminar' d='Modules.Nfpanel.Admin'}</button>
            </form>
          </div>
        </li>
      {/foreach}
    </ul>
  {/if}

  <h2 class="nfp-section-title"><i class="material-icons" aria-hidden="true">local_offer</i>{l s='Promociones de la portada' d='Modules.Nfpanel.Admin'}</h2>
  <p class="nfp-help">{l s='Salen en "Promociones Exclusivas". Para agregar un producto: Catálogo > Editar > marca "Mostrar en Promociones de la portada" y pon su precio de oferta.' d='Modules.Nfpanel.Admin'}</p>
  {if !$nf_promos}
    <div class="nfp-empty">{l s='No hay productos en promociones de la portada.' d='Modules.Nfpanel.Admin'}</div>
  {else}
    <ul class="nfp-list">
      {foreach $nf_promos as $p}
        <li class="nfp-item{if !$p.active} is-hidden{/if}">
          <div class="nfp-item__thumb">
            {if $p.image_url}<img src="{$p.image_url|escape:'html':'UTF-8'}" alt="" loading="lazy" width="72" height="72">{else}<i class="material-icons" aria-hidden="true">image</i>{/if}
          </div>
          <div class="nfp-item__info">
            <strong class="nfp-item__name">{$p.name|escape:'html':'UTF-8'}</strong>
            <div class="nfp-item__meta">
              {if $p.offer}
                <span class="nfp-price nfp-price--old">{$p.price|escape:'html':'UTF-8'}</span>
                <span class="nfp-price nfp-price--offer">{$p.offer|escape:'html':'UTF-8'}</span>
              {else}
                <span class="nfp-price">{$p.price|escape:'html':'UTF-8'}</span>
                <span class="nfp-muted">{l s='Sin precio de oferta' d='Modules.Nfpanel.Admin'}</span>
              {/if}
            </div>
          </div>
          <div class="nfp-item__actions">
            <a class="nfp-btn nfp-btn--primary" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products&amp;edit={$p.id|intval}"><i class="material-icons" aria-hidden="true">edit</i>{l s='Editar oferta' d='Modules.Nfpanel.Admin'}</a>
            <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=home">
              <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
              <input type="hidden" name="nf_action" value="remove_promo">
              <input type="hidden" name="id" value="{$p.id|intval}">
              <button class="nfp-btn nfp-btn--ghost" type="submit"><i class="material-icons" aria-hidden="true">remove_circle_outline</i>{l s='Quitar de la portada' d='Modules.Nfpanel.Admin'}</button>
            </form>
          </div>
        </li>
      {/foreach}
    </ul>
  {/if}
</section>
