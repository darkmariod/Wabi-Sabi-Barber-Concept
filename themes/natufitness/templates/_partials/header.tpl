{include file='_partials/nf-config.tpl'}

{block name='header_banner'}
  <div class="header-banner">
    {hook h='displayBanner'}
  </div>
{/block}

{block name='header_nav'}{/block}

{block name='header_top'}
  <div class="nf-header">
    <div class="nf-header__inner">
      <button type="button" id="menu-icon" class="nf-burger hidden-md-up" aria-label="Menú">
        <i class="material-icons" aria-hidden="true">&#xE5D2;</i>
      </button>

      <div class="nf-logo-wrap">
        {if $page.page_name == 'index'}<h1 class="nf-logo-title">{/if}
        {include file='_partials/nf-logo.tpl'}
        {if $page.page_name == 'index'}</h1>{/if}
      </div>

      <div class="nf-header__right">
        <div class="nf-header__search">
          {widget name='ps_searchbar'}
        </div>
        <a class="nf-icon-link hidden-sm-down" href="{$urls.pages.stores}" title="Local Riobamba">
          <svg class="nf-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l2-5h14l2 5M4 9v11h16V9M3 9h18M9 20v-6h6v6"/></svg>
        </a>
        {widget name='ps_customersignin'}
        {widget name='ps_shoppingcart'}
      </div>
    </div>
  </div>

  <nav class="nf-nav" aria-label="Categorías">
    {widget name='ps_mainmenu'}
  </nav>

  <div id="mobile_top_menu_wrapper" class="nf-mobile-menu hidden-md-up" style="display:none;">
    <div class="js-top-menu mobile" id="_mobile_top_menu"></div>
  </div>

  <div class="nf-ship">
    <svg width="20" height="14" viewBox="0 0 20 14" aria-hidden="true"><path d="M0 1h12v9H0zM12 4h4l3 3v3h-7z" fill="#222"/><circle cx="4" cy="12" r="2" fill="#222"/><circle cx="15" cy="12" r="2" fill="#222"/></svg>
    Envío GRATIS a los ${$nf_free_shipping_amount}
  </div>

  {hook h='displayNavFullWidth'}
{/block}
