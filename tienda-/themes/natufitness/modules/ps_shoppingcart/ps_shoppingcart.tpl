<div id="_desktop_cart" class="nf-cart">
  <div class="blockcart cart-preview {if $cart.products_count > 0}active{else}inactive{/if}" data-refresh-url="{$refresh_url}">
    <div class="header">
      <a class="nf-icon-link" rel="nofollow" href="{$cart_url}" aria-label="Carrito con {$cart.products_count} producto(s)">
        <svg class="nf-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h3l2 11h11l2-8H7"/><circle cx="9" cy="19" r="1.5"/><circle cx="18" cy="19" r="1.5"/></svg>
        <span class="cart-products-count nf-cart__count">{$cart.products_count}</span>
      </a>
    </div>
  </div>
</div>
