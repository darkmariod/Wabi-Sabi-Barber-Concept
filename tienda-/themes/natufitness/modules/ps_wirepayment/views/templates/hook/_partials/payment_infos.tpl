{*
 * NatuFitness: transfer data shown at checkout and on the order confirmation.
 * Amount and the bank accounts (Módulos > Transferencia bancaria > Detalles, one per line);
 * the account holder only when it is filled in (scripts/setup-bank-accounts.php).
 *}
<dl class="nf-wire-infos">
  <dt>{l s='Amount' d='Modules.Wirepayment.Shop'}</dt>
  <dd>{$total}</dd>
  {if $bankwireOwner|trim && $bankwireOwner|strpos:'PENDIENTE' === false}
    <dt>{l s='Name of account owner' d='Modules.Wirepayment.Shop'}</dt>
    <dd>{$bankwireOwner}</dd>
  {/if}
  <dt>Cuentas para transferir</dt>
  <dd>{$bankwireDetails nofilter}</dd>
</dl>
