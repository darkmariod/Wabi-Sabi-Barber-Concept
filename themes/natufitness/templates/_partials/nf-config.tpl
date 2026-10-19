{*
 * NatuFitness storefront settings.
 * nf_whatsapp_number: read from the NF_WHATSAPP_NUMBER setting of the nf_deuna module
 * (Back office > Módulos > DeUna (QR estático) > Configurar). The literal below is only a
 * fallback used when that module is disabled. Format: digits only, 593 + number without the leading 0.
 * Social URLs left empty are not rendered.
 *}
{if isset($modules.nf_deuna.whatsapp_number) && $modules.nf_deuna.whatsapp_number}
  {assign var='nf_whatsapp_number' value=$modules.nf_deuna.whatsapp_number scope='global'}
{else}
  {assign var='nf_whatsapp_number' value='593000000000' scope='global'}
{/if}
{assign var='nf_instagram_url' value='https://www.instagram.com/natufitness.ec/' scope='global'}
{assign var='nf_facebook_url' value='' scope='global'}
{assign var='nf_tiktok_url' value='' scope='global'}
{assign var='nf_free_shipping_amount' value='35' scope='global'}
