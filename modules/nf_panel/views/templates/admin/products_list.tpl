{*
 * Mi Tienda: product list with search, pagination and quick actions.
 *}
<div class="nfp-toolbar">
  <a class="nfp-btn nfp-btn--accent nfp-btn--big" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products&amp;edit=0">
    <i class="material-icons" aria-hidden="true">add</i>{l s='Nuevo producto' d='Modules.Nfpanel.Admin'}
  </a>
  <form class="nfp-search" method="get" action="index.php" role="search">
    <input type="hidden" name="controller" value="AdminNfPanel">
    <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
    <input type="hidden" name="section" value="products">
    <label class="sr-only" for="nfp-q">{l s='Buscar por nombre' d='Modules.Nfpanel.Admin'}</label>
    <input id="nfp-q" type="search" name="q" value="{$nf_search|escape:'html':'UTF-8'}" placeholder="{l s='Buscar por nombre…' d='Modules.Nfpanel.Admin'}" maxlength="64">
    <button class="nfp-btn nfp-btn--primary" type="submit"><i class="material-icons" aria-hidden="true">search</i><span class="sr-only">{l s='Buscar' d='Modules.Nfpanel.Admin'}</span></button>
  </form>
</div>

<p class="nfp-count">
  {if $nf_search}
    {l s='%count% productos encontrados para "%search%"' sprintf=['%count%' => $nf_total, '%search%' => $nf_search] d='Modules.Nfpanel.Admin'}
    · <a href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products">{l s='Ver todos' d='Modules.Nfpanel.Admin'}</a>
  {else}
    {l s='%count% productos' sprintf=['%count%' => $nf_total] d='Modules.Nfpanel.Admin'}
  {/if}
</p>

{if !$nf_products}
  <div class="nfp-empty">
    {if $nf_search}
      {l s='No hay productos con ese nombre.' d='Modules.Nfpanel.Admin'}
    {else}
      {l s='Todavía no tienes productos. Toca "Nuevo producto" para crear el primero.' d='Modules.Nfpanel.Admin'}
    {/if}
  </div>
{else}
  <ul class="nfp-list">
    {foreach $nf_products as $p}
      <li class="nfp-item{if !$p.active} is-hidden{/if}">
        <div class="nfp-item__thumb">
          {if $p.thumb}
            <img src="{$p.thumb|escape:'html':'UTF-8'}" alt="" loading="lazy" width="72" height="72">
          {else}
            <i class="material-icons" aria-hidden="true">image</i>
          {/if}
        </div>
        <div class="nfp-item__info">
          <strong class="nfp-item__name">{$p.name|escape:'html':'UTF-8'}</strong>
          <div class="nfp-item__meta">
            {if $p.offer !== null}
              <span class="nfp-price nfp-price--old">${$p.price|string_format:'%.2f'}</span>
              <span class="nfp-price nfp-price--offer">${$p.offer|string_format:'%.2f'}</span>
            {else}
              <span class="nfp-price">${$p.price|string_format:'%.2f'}</span>
            {/if}
            <span class="nfp-stock{if $p.stock <= 0} is-out{/if}">{l s='Stock: %qty%' sprintf=['%qty%' => $p.stock] d='Modules.Nfpanel.Admin'}</span>
          </div>
          <div class="nfp-item__meta">
            <span class="nfp-muted">{$p.category|escape:'html':'UTF-8'}</span>
            {if $p.active}
              <span class="nfp-badge nfp-badge--on">{l s='Visible' d='Modules.Nfpanel.Admin'}</span>
            {else}
              <span class="nfp-badge nfp-badge--off">{l s='Oculto' d='Modules.Nfpanel.Admin'}</span>
            {/if}
          </div>
          {if $p.has_combinations}
            <p class="nfp-note">{l s='Tiene variantes (tallas, sabores…): se edita en el editor completo.' d='Modules.Nfpanel.Admin'}</p>
          {/if}
        </div>
        <div class="nfp-item__actions">
          {if $p.has_combinations}
            {if $p.native_url}
              <a class="nfp-btn nfp-btn--primary" href="{$p.native_url|escape:'html':'UTF-8'}">{l s='Editor completo' d='Modules.Nfpanel.Admin'}</a>
            {/if}
          {else}
            <a class="nfp-btn nfp-btn--primary" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products&amp;edit={$p.id|intval}">
              <i class="material-icons" aria-hidden="true">edit</i>{l s='Editar' d='Modules.Nfpanel.Admin'}
            </a>
          {/if}
          <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products">
            <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="nf_action" value="toggle_product">
            <input type="hidden" name="id_product" value="{$p.id|intval}">
            <input type="hidden" name="q" value="{$nf_search|escape:'html':'UTF-8'}">
            <input type="hidden" name="p" value="{$nf_page|intval}">
            <button class="nfp-btn nfp-btn--ghost" type="submit">
              {if $p.active}
                <i class="material-icons" aria-hidden="true">visibility_off</i>{l s='Ocultar' d='Modules.Nfpanel.Admin'}
              {else}
                <i class="material-icons" aria-hidden="true">visibility</i>{l s='Mostrar' d='Modules.Nfpanel.Admin'}
              {/if}
            </button>
          </form>
          <form method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products" data-nfp-confirm="{l s='¿Eliminar este producto? No se puede deshacer.' d='Modules.Nfpanel.Admin'}">
            <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="nf_action" value="delete_product">
            <input type="hidden" name="id_product" value="{$p.id|intval}">
            <input type="hidden" name="q" value="{$nf_search|escape:'html':'UTF-8'}">
            <input type="hidden" name="p" value="{$nf_page|intval}">
            <button class="nfp-btn nfp-btn--danger" type="submit"><i class="material-icons" aria-hidden="true">delete</i>{l s='Eliminar' d='Modules.Nfpanel.Admin'}</button>
          </form>
        </div>
      </li>
    {/foreach}
  </ul>
  {include file="./pagination.tpl" nf_page_params="&section=products&q={$nf_search|escape:'url'}"}
{/if}
