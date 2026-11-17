{block name='product_miniature_item'}
<div class="js-product product{if !empty($productClasses)} {$productClasses}{/if}">
  <article class="product-miniature js-product-miniature nf-card" data-id-product="{$product.id_product}" data-id-product-attribute="{$product.id_product_attribute}">
    <div class="thumbnail-container nf-card__inner">
      <div class="thumbnail-top nf-card__image">
        {block name='product_thumbnail'}
          {if $product.cover}
            {assign var='nf_image' value=$product.cover.bySize.home_default}
            {assign var='nf_alt' value=$product.cover.legend|default:$product.name}
          {else}
            {assign var='nf_image' value=$urls.no_picture_image.bySize.home_default}
            {assign var='nf_alt' value=$product.name}
          {/if}
          <a href="{$product.url}" class="thumbnail product-thumbnail">
            <picture>
              {if !empty($nf_image.sources.avif)}<source srcset="{$nf_image.sources.avif}" type="image/avif">{/if}
              {if !empty($nf_image.sources.webp)}<source srcset="{$nf_image.sources.webp}" type="image/webp">{/if}
              <img src="{$nf_image.url}" alt="{$nf_alt}" loading="lazy" width="{$nf_image.width}" height="{$nf_image.height}">
            </picture>
          </a>
        {/block}
      </div>

      <div class="product-description nf-card__body">
        {block name='product_name'}
          {if $page.page_name == 'index'}
            <h3 class="h3 product-title nf-card__title"><a href="{$product.url}" title="{$product.name}">{$product.name}</a></h3>
          {else}
            <h2 class="h3 product-title nf-card__title"><a href="{$product.url}" title="{$product.name}">{$product.name}</a></h2>
          {/if}
        {/block}

        {block name='product_price_and_shipping'}
          {if $product.show_price && $product.price_amount > 0}
            <div class="product-price-and-shipping nf-card__price">
              {if $product.has_discount}
                {hook h='displayProductPriceBlock' product=$product type="old_price"}
                <s class="regular-price" aria-label="Precio regular">{$product.regular_price}</s>
              {/if}
              {hook h='displayProductPriceBlock' product=$product type="before_price"}
              <span class="price" aria-label="Precio">
                {capture name='custom_price'}{hook h='displayProductPriceBlock' product=$product type='custom_price' hook_origin='products_list'}{/capture}
                {if '' !== $smarty.capture.custom_price}
                  {$smarty.capture.custom_price nofilter}
                {else}
                  {$product.price}
                {/if}
              </span>
              {hook h='displayProductPriceBlock' product=$product type='unit_price'}
              {hook h='displayProductPriceBlock' product=$product type='weight'}
            </div>
          {/if}
        {/block}

        {block name='product_buy'}
          {if $product.price_amount > 0}
            <a class="nf-btn-buy" href="{$product.url}">Comprar</a>
          {else}
            <span class="nf-btn-buy nf-btn-buy--soon" aria-disabled="true">Precio próximamente</span>
          {/if}
        {/block}

        {block name='product_reviews'}
          {hook h='displayProductListReviews' product=$product}
        {/block}
      </div>

      {* Flags (Nuevo, Fuera de stock...) only once the product is priced: catalog items stay clean. *}
      {if $product.price_amount > 0}{include file='catalog/_partials/product-flags.tpl'}{/if}
    </div>
  </article>
</div>
{/block}
