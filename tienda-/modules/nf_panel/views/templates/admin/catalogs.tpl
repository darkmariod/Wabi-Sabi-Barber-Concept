{*
 * Mi Tienda > Catálogos: create, rename, hide and delete product catalogs (categories)
 * and copy their store link.
 *}
<section class="nfp-catalogs">
  <h2 class="nfp-section-title"><i class="material-icons" aria-hidden="true">category</i>{l s='Catálogos de la tienda' d='Modules.Nfpanel.Admin'}</h2>
  <p class="nfp-help">{l s='Son los grupos de productos del menú (ej.: Proteínas, Belleza). Un catálogo principal aparece en "Nuestros productos"; uno dentro de otro aparece como subcatálogo.' d='Modules.Nfpanel.Admin'}</p>

  <form class="nfp-form nfp-catalog-form" method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=catalogs">
    <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
    <input type="hidden" name="nf_action" value="create_catalog">
    <div class="nfp-field">
      <label for="nfp-cat-name">{l s='Nombre del catálogo' d='Modules.Nfpanel.Admin'} <span class="nfp-req">*</span></label>
      <input id="nfp-cat-name" type="text" name="name" maxlength="64" required value="{$nf_form_name|escape:'html':'UTF-8'}" placeholder="{l s='Ej.: Snacks proteicos' d='Modules.Nfpanel.Admin'}">
    </div>
    <div class="nfp-field">
      <label for="nfp-cat-parent">{l s='Dónde va' d='Modules.Nfpanel.Admin'}</label>
      <select id="nfp-cat-parent" name="id_parent">
        <option value="0">{l s='Catálogo principal (sale en el menú)' d='Modules.Nfpanel.Admin'}</option>
        {foreach $nf_parents as $parent}
          <option value="{$parent.id|intval}">{l s='Dentro de %name%' sprintf=['%name%' => $parent.name] d='Modules.Nfpanel.Admin'}</option>
        {/foreach}
      </select>
    </div>
    <button class="nfp-btn nfp-btn--accent nfp-btn--big" type="submit"><i class="material-icons" aria-hidden="true">add</i>{l s='Crear catálogo' d='Modules.Nfpanel.Admin'}</button>
  </form>

  <ul class="nfp-list nfp-catalog-list">
    {foreach $nf_catalogs as $c}
      <li class="nfp-item nfp-catalog{if !$c.active} is-hidden{/if}" style="--nfp-level: {$c.level|intval};">
        <div class="nfp-item__info">
          <strong class="nfp-item__name">{if $c.level}<span class="nfp-muted" aria-hidden="true">↳ </span>{/if}{$c.name|escape:'html':'UTF-8'}</strong>
          <div class="nfp-item__meta">
            {if $c.active}
              <span class="nfp-badge nfp-badge--on">{l s='Visible' d='Modules.Nfpanel.Admin'}</span>
            {else}
              <span class="nfp-badge nfp-badge--off">{l s='Oculto' d='Modules.Nfpanel.Admin'}</span>
            {/if}
            <span class="nfp-muted">{l s='%n% productos' sprintf=['%n%' => $c.products] d='Modules.Nfpanel.Admin'}</span>
            {if $c.children}<span class="nfp-muted">· {l s='%n% subcatálogos' sprintf=['%n%' => $c.children] d='Modules.Nfpanel.Admin'}</span>{/if}
          </div>
          <div class="nfp-catalog__link">
            <a href="{$c.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$c.url|escape:'html':'UTF-8'}</a>
            <button class="nfp-btn nfp-btn--ghost nfp-btn--small js-nfp-copy" type="button" data-copy="{$c.url|escape:'html':'UTF-8'}"><i class="material-icons" aria-hidden="true">content_copy</i>{l s='Copiar enlace' d='Modules.Nfpanel.Admin'}</button>
          </div>
          <details class="nfp-catalog__rename">
            <summary>{l s='Cambiar nombre' d='Modules.Nfpanel.Admin'}</summary>
            <form class="nfp-inline-form" method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=catalogs">
              <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
              <input type="hidden" name="nf_action" value="rename_catalog">
              <input type="hidden" name="id" value="{$c.id|intval}">
              <input type="text" name="name" maxlength="64" required value="{$c.name|escape:'html':'UTF-8'}" aria-label="{l s='Nuevo nombre' d='Modules.Nfpanel.Admin'}">
              <button class="nfp-btn nfp-btn--primary" type="submit">{l s='Guardar' d='Modules.Nfpanel.Admin'}</button>
            </form>
          </details>
        </div>
        <div class="nfp-item__actions">
          <a class="nfp-btn nfp-btn--primary" href="{$c.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener"><i class="material-icons" aria-hidden="true">visibility</i>{l s='Ver en la tienda' d='Modules.Nfpanel.Admin'}</a>
          <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=catalogs">
            <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="nf_action" value="toggle_catalog">
            <input type="hidden" name="id" value="{$c.id|intval}">
            <button class="nfp-btn nfp-btn--ghost" type="submit">
              {if $c.active}<i class="material-icons" aria-hidden="true">visibility_off</i>{l s='Ocultar' d='Modules.Nfpanel.Admin'}{else}<i class="material-icons" aria-hidden="true">visibility</i>{l s='Mostrar' d='Modules.Nfpanel.Admin'}{/if}
            </button>
          </form>
          {if !$c.products && !$c.children}
            <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=catalogs" data-nfp-confirm="{l s='¿Eliminar el catálogo %name%? No se puede deshacer.' sprintf=['%name%' => $c.name] d='Modules.Nfpanel.Admin'}">
              <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
              <input type="hidden" name="nf_action" value="delete_catalog">
              <input type="hidden" name="id" value="{$c.id|intval}">
              <button class="nfp-btn nfp-btn--danger" type="submit"><i class="material-icons" aria-hidden="true">delete</i>{l s='Eliminar' d='Modules.Nfpanel.Admin'}</button>
            </form>
          {/if}
        </div>
      </li>
    {/foreach}
  </ul>
</section>
