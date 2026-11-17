{*
 * Mi Tienda: single create/edit product form.
 *}
<div class="nfp-formhead">
  <a class="nfp-back" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products"><i class="material-icons" aria-hidden="true">arrow_back</i>{l s='Volver a productos' d='Modules.Nfpanel.Admin'}</a>
  <h2>{if $nf_product.id}{l s='Editar producto' d='Modules.Nfpanel.Admin'}{else}{l s='Nuevo producto' d='Modules.Nfpanel.Admin'}{/if}</h2>
</div>

{if $nf_product.has_combinations}
  <div class="nfp-empty">
    {l s='Este producto tiene variantes (tallas, sabores…) y solo se puede editar en el editor completo.' d='Modules.Nfpanel.Admin'}
    {if $nf_native_product_url}
      <p><a class="nfp-btn nfp-btn--primary" href="{$nf_native_product_url|escape:'html':'UTF-8'}">{l s='Abrir editor completo' d='Modules.Nfpanel.Admin'}</a></p>
    {else}
      <p>{l s='Pide ayuda al administrador de la tienda.' d='Modules.Nfpanel.Admin'}</p>
    {/if}
  </div>
{else}
<form class="nfp-form" method="post" action="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="token" value="{$nf_token|escape:'html':'UTF-8'}">
  <input type="hidden" name="nf_action" value="save_product">
  <input type="hidden" name="id_product" value="{$nf_product.id|intval}">

  <div class="nfp-field">
    <label for="nfp-name">{l s='Nombre' d='Modules.Nfpanel.Admin'} <span class="nfp-req">*</span></label>
    <input id="nfp-name" type="text" name="name" value="{$nf_product.name|escape:'html':'UTF-8'}" maxlength="128" required placeholder="{l s='Ej.: Proteína Whey Gold 5 lb' d='Modules.Nfpanel.Admin'}">
  </div>

  <div class="nfp-row">
    <div class="nfp-field">
      <label for="nfp-price">{l s='Precio de venta con IVA ($)' d='Modules.Nfpanel.Admin'} <span class="nfp-req">*</span></label>
      <input id="nfp-price" type="text" inputmode="decimal" name="price" value="{$nf_product.price|escape:'html':'UTF-8'}" required placeholder="0.00">
    </div>
    <div class="nfp-field">
      <label for="nfp-iva">{l s='IVA' d='Modules.Nfpanel.Admin'}</label>
      <select id="nfp-iva" name="iva">
        {foreach $nf_iva_options as $key => $label}
          <option value="{$key|escape:'html':'UTF-8'}"{if $nf_product.iva == $key} selected{/if}>{$label|escape:'html':'UTF-8'}</option>
        {/foreach}
      </select>
    </div>
  </div>

  <div class="nfp-row">
    <div class="nfp-field">
      <label for="nfp-offer">{l s='Precio de oferta ($)' d='Modules.Nfpanel.Admin'}</label>
      <input id="nfp-offer" type="text" inputmode="decimal" name="offer" value="{$nf_product.offer|escape:'html':'UTF-8'}" placeholder="{l s='Opcional' d='Modules.Nfpanel.Admin'}">
      <small>{l s='Déjalo vacío si no hay oferta.' d='Modules.Nfpanel.Admin'}</small>
    </div>
    <div class="nfp-field">
      <label for="nfp-stock">{l s='Stock (unidades)' d='Modules.Nfpanel.Admin'}</label>
      <input id="nfp-stock" type="number" inputmode="numeric" min="0" step="1" name="stock" value="{$nf_product.stock|escape:'html':'UTF-8'}">
    </div>
  </div>

  <div class="nfp-field">
    <label for="nfp-category">{l s='Categoría' d='Modules.Nfpanel.Admin'} <span class="nfp-req">*</span></label>
    <select id="nfp-category" name="id_category" required>
      <option value="">{l s='Elige una categoría' d='Modules.Nfpanel.Admin'}</option>
      {foreach $nf_categories as $category}
        <option value="{$category.id|intval}"{if $nf_product.id_category == $category.id} selected{/if}>{$category.label|escape:'html':'UTF-8'}</option>
      {/foreach}
    </select>
  </div>

  <label class="nfp-check">
    <input type="checkbox" name="show_home" value="1"{if $nf_product.show_home} checked{/if}>
    <span>{l s='Mostrar en Promociones de la portada' d='Modules.Nfpanel.Admin'}</span>
  </label>

  <div class="nfp-field">
    <label for="nfp-brand">{l s='Marca' d='Modules.Nfpanel.Admin'}</label>
    <select id="nfp-brand" name="id_manufacturer">
      <option value="0">{l s='Sin marca' d='Modules.Nfpanel.Admin'}</option>
      {foreach $nf_manufacturers as $brand}
        <option value="{$brand.id|intval}"{if $nf_product.id_manufacturer == $brand.id} selected{/if}>{$brand.name|escape:'html':'UTF-8'}</option>
      {/foreach}
    </select>
  </div>

  <fieldset class="nfp-field">
    <legend>{l s='Fotos' d='Modules.Nfpanel.Admin'}</legend>
    {if $nf_product.images}
      <div class="nfp-photos">
        {foreach $nf_product.images as $image}
          <div class="nfp-photo">
            <img src="{$image.url|escape:'html':'UTF-8'}" alt="" loading="lazy">
            <label class="nfp-photo__cover">
              <input type="radio" name="cover_image" value="{$image.id|intval}"{if $image.cover} checked{/if}>
              <span>{l s='Portada' d='Modules.Nfpanel.Admin'}</span>
            </label>
            <label class="nfp-photo__delete" title="{l s='Quitar foto' d='Modules.Nfpanel.Admin'}">
              <input type="checkbox" name="delete_images[]" value="{$image.id|intval}">
              <span aria-hidden="true">×</span><span class="sr-only">{l s='Quitar foto' d='Modules.Nfpanel.Admin'}</span>
            </label>
          </div>
        {/foreach}
      </div>
      <small>{l s='Toca × para quitar una foto (se borra al guardar).' d='Modules.Nfpanel.Admin'}</small>
    {/if}
    <label class="nfp-upload" for="nfp-photos-input">
      <i class="material-icons" aria-hidden="true">add_a_photo</i>
      <span data-nfp-upload-label>{l s='Agregar fotos' d='Modules.Nfpanel.Admin'}</span>
    </label>
    <input id="nfp-photos-input" class="nfp-upload__input" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    <small>{l s='JPG, PNG o WEBP, máximo %mb% MB cada una. La primera foto será la portada.' sprintf=['%mb%' => $nf_max_photo_mb] d='Modules.Nfpanel.Admin'}</small>
  </fieldset>

  <div class="nfp-field">
    <label for="nfp-description">{l s='Descripción' d='Modules.Nfpanel.Admin'}</label>
    <textarea id="nfp-description" name="description" rows="6" placeholder="{l s='Beneficios, modo de uso, sabor, tamaño…' d='Modules.Nfpanel.Admin'}">{$nf_product.description|escape:'html':'UTF-8'}</textarea>
  </div>

  <label class="nfp-switch">
    <input type="checkbox" name="active" value="1"{if $nf_product.active} checked{/if}>
    <span class="nfp-switch__track" aria-hidden="true"></span>
    <span>{l s='Visible en la tienda' d='Modules.Nfpanel.Admin'}</span>
  </label>

  <div class="nfp-formactions">
    <button class="nfp-btn nfp-btn--accent nfp-btn--big" type="submit"><i class="material-icons" aria-hidden="true">check</i>{l s='Guardar' d='Modules.Nfpanel.Admin'}</button>
    <a class="nfp-btn nfp-btn--ghost nfp-btn--big" href="{$nf_base_url|escape:'html':'UTF-8'}&amp;section=products">{l s='Cancelar' d='Modules.Nfpanel.Admin'}</a>
  </div>
</form>
{/if}
