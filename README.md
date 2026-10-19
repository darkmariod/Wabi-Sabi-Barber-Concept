# NatuFitness store

PrestaShop 8.2 store for NatuFitness (Riobamba, Ecuador).

## Local setup

1. Copy `.env.example` to `.env` and set real passwords.
2. Start the stack:
   ```bash
   docker compose up -d
   ```
   The first start runs the automated PrestaShop install (Spanish, Ecuador).
3. Apply the base configuration (idempotent):
   ```bash
   docker compose exec prestashop php /opt/natufitness/scripts/seed.php
   ```

4. Activate the NatuFitness theme (idempotent; also clears caches):
   ```bash
   docker compose exec prestashop php /opt/natufitness/scripts/activate-theme.php
   ```
   Run it again after editing `themes/natufitness/config/theme.yml` so the hook layout is re-applied.
   Template and CSS edits only need a browser refresh (the theme folder is bind-mounted).

5. Configure payment methods (idempotent; also clears caches):
   ```bash
   docker compose exec prestashop php /opt/natufitness/scripts/setup-payments.php
   ```
   See [Payments](#payments) for what it does and what the client still has to fill in.

6. Apply branding (idempotent; also clears caches):
   ```bash
   docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-branding.php
   ```
   See [Branding](#branding).

Setup order: `seed.php` → `activate-theme.php` → `setup-payments.php` → `setup-branding.php`.

| URL | Purpose |
| --- | --- |
| http://localhost:8088 | Storefront |
| http://localhost:8088/admin-nf | Back office (credentials in `.env`) |

MySQL is not exposed to the host. Use `docker compose exec db mysql ...` for direct access.

## What the seed configures

- Ecuador as the only active country, USD as the only currency, `America/Guayaquil` timezone.
- Spanish as the only active language.
- Free shipping from $35.
- Address format with optional ID/RUC (`dni`) and company fields for invoices.
- Category tree and main menu; demo catalog removed.

## Theme

`themes/natufitness/` is a child theme of `classic`, mounted at `/var/www/html/themes/natufitness`.

- Styles: `assets/css/custom.css`; behaviour (hero and product carousels): `assets/js/custom.js`.
- Homepage: `templates/index.tpl` (hero, brands, categories); the "Promociones Exclusivas" carousel shows the
  products of the Home category via `ps_featuredproducts`.
- **WhatsApp number**: set it once in the back office, `NF_WHATSAPP_NUMBER` (see [Payments](#payments)).
  The theme reads it in `themes/natufitness/templates/_partials/nf-config.tpl`; the literal there is only
  a fallback used if the `nf_deuna` module is disabled.
- **Social links and free-shipping amount**: edit `themes/natufitness/templates/_partials/nf-config.tpl`.
  Clear the cache afterwards (re-run `activate-theme.php`).

## Branding

`scripts/setup-branding.php`:
- renames the shop entity (installer default "PrestaShop") to `PS_SHOP_NAME`;
- copies `themes/natufitness/assets/img/logo-natufitness.png` (300×80) and `favicon-natufitness.ico` to
  `/var/www/html/img/` and sets `PS_LOGO` (emails, invoices and back office fall back to it) and
  `PS_FAVICON`. To change the logo, replace the PNG in the theme and re-run the script;
- sets the Spanish customer-facing texts of `ps_dataprivacy` (`CUSTPRIV_MSG_AUTH`, "Privacidad de los datos
  del cliente"), `psgdpr` (`PSGDPR_CUSTOMER_FORM`, `PSGDPR_CREATION_FORM`) and `ps_emailsubscription`
  (`NW_CONDITIONS`) for every language;
- replaces the Customer Reassurance (`blockreassurance`) placeholders with three items shown in checkout and on
  product pages: *Pago seguro*, *Envío a todo Ecuador*, *Productos originales*. The module is configured
  (table `psreassurance`) rather than replaced by static markup, so the client can still edit the items in
  *Módulos > Gestor de módulos > Customer Reassurance > Configurar*.

Re-running it overwrites these texts with the values in the script; edit the script to change them permanently.

The checkout uses a minimal header (logo + WhatsApp help link, no navigation) and a footer without the
PrestaShop credit: `themes/natufitness/templates/checkout/_partials/header.tpl` and `footer.tpl`. The text logo
is shared with the storefront header via `templates/_partials/nf-logo.tpl`.

## Payments

Active methods: **DeUna (QR)** (custom module `modules/nf_deuna/`, bind-mounted into the container) and
**bank transfer** (`ps_wirepayment`). Both create the order in a "waiting for payment" state; the
customer sends the receipt via WhatsApp and the order is marked paid manually in
*Pedidos > Pedidos*. Cheque (`ps_checkpayment`) and cash on delivery (`ps_cashondelivery`) are disabled.

`scripts/setup-payments.php`:
- installs/enables `nf_deuna` (restricted to Ecuador and USD; creates the order state
  "Esperando pago DeUna");
- fills the bank transfer fields with `PENDIENTE` placeholders **only when they are empty** (real values
  are never overwritten), sets the Spanish custom text and restricts the module to USD;
- disables cheque and cash on delivery (they stay installed).

| Setting | Where (back office) | Config key(s) |
| --- | --- | --- |
| Real bank account (owner, account number/type, bank) | *Módulos > Gestor de módulos > Transferencia bancaria > Configurar* | `BANK_WIRE_OWNER`, `BANK_WIRE_DETAILS`, `BANK_WIRE_ADDRESS`, `BANK_WIRE_CUSTOM_TEXT` |
| DeUna QR image, account holder, instructions | *Módulos > Gestor de módulos > DeUna (QR estático) > Configurar* | `NF_DEUNA_QR_IMAGE`, `NF_DEUNA_HOLDER`, `NF_DEUNA_INSTRUCTIONS` |
| WhatsApp number (payment buttons, floating button, footer) | same DeUna page, field "Número de WhatsApp" | `NF_WHATSAPP_NUMBER` (default placeholder `593000000000`; format `593` + number without the leading `0`) |

**Uploading the real DeUna QR**: in the DeUna app (Banco Pichincha) download/screenshot your static
collection QR, crop it to the QR only, and upload it (PNG, JPG or WEBP) on the DeUna configuration page.
It is stored in `img/nf_deuna/` (Docker volume). Until then the checkout shows a grey "QR DE EJEMPLO"
placeholder that is **not payable** (`modules/nf_deuna/views/img/qr-placeholder.svg`).

The order confirmation pages of both methods show the amount, the order reference and a green
"Enviar comprobante por WhatsApp" button with a prefilled message. The bank transfer block is a theme
override: `themes/natufitness/modules/ps_wirepayment/views/templates/hook/payment_return.tpl`.

Re-enable cash on delivery (one line):
```bash
docker compose exec -u www-data prestashop php /var/www/html/bin/console prestashop:module enable ps_cashondelivery
```
Run `bin/console` as `www-data` (as above): running it as root leaves root-owned files in `var/cache`
and the storefront returns HTTP 500 until ownership is restored.

## Project layout

- `docker-compose.yml` — PrestaShop + MySQL stack.
- `scripts/` — CLI scripts mounted read-only at `/opt/natufitness/scripts`.
- `themes/natufitness/` — storefront child theme.
- `mockups/` — static design references.
- `odd/tasks/` — feature task documents.
