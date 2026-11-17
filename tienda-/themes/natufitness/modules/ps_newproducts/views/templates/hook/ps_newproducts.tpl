{* Newest catalog products, shown until there are real sales, syf.ec style carousel. *}
{if $products}
<section class="nf-section nf-bestsellers">
  <h2 class="nf-section-title">Suplementos Más Vendidos</h2>
  <div class="nf-carousel js-nf-carousel">
    <div class="nf-carousel__track js-nf-carousel-track">
      {foreach from=$products item="product" key="position"}
        {include file="catalog/_partials/miniatures/product.tpl" product=$product position=$position productClasses="nf-carousel__item"}
      {/foreach}
    </div>
    <div class="nf-carousel__dots js-nf-carousel-dots" aria-hidden="true"></div>
  </div>
</section>
{/if}
