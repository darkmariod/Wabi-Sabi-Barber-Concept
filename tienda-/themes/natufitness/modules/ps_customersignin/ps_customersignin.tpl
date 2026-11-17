<div id="_desktop_user_info" class="nf-user">
  <div class="user-info">
    {if $logged}
      <a class="account nf-icon-link" href="{$urls.pages.my_account}" title="Mi cuenta ({$customerName})" rel="nofollow">
        <svg class="nf-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 15-5 16 0"/></svg>
        <span class="sr-only">Mi cuenta</span>
      </a>
    {else}
      <a class="nf-icon-link" href="{$urls.pages.authentication}?back={$urls.current_url|urlencode}" title="Iniciar sesión" rel="nofollow">
        <svg class="nf-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 15-5 16 0"/></svg>
        <span class="sr-only">Iniciar sesión</span>
      </a>
    {/if}
  </div>
</div>
