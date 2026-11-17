<div id="search_widget" class="search-widgets nf-search" data-search-controller-url="{$search_controller_url}">
  <form method="get" action="{$search_controller_url}">
    <input type="hidden" name="controller" value="search">
    <i class="material-icons search" aria-hidden="true">search</i>
    <input type="text" name="s" value="{$search_string}" placeholder="Buscar..." aria-label="Buscar productos">
    <i class="material-icons clear" aria-hidden="true">clear</i>
  </form>
</div>
