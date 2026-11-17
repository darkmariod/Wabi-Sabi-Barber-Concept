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

7. Install the default homepage content (idempotent; also clears caches):
   ```bash
   docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-content.php
   ```
   See [Owner-editable content](#owner-editable-content).

8. Install the owner panel "Mi Tienda" and the "Dueño" profile (idempotent):
   ```bash
   docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-owner.php
   ```
   See [Panel Mi Tienda](#panel-mi-tienda) to create the owner's user.

Setup order: `seed.php` → `activate-theme.php` → `setup-payments.php` → `setup-branding.php` → `setup-content.php` → `setup-owner.php`.

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
- Homepage: `templates/index.tpl` (hero, brands, promo block, categories); the "Promociones Exclusivas"
  carousel shows the products of the Home category via `ps_featuredproducts`. Hero, promo block, brands and
  logo come from back-office data, see [Owner-editable content](#owner-editable-content).
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
  `PS_FAVICON` the first time only (see [Owner-editable content](#owner-editable-content));
- sets the Spanish customer-facing texts of `ps_dataprivacy` (`CUSTPRIV_MSG_AUTH`, "Privacidad de los datos
  del cliente"), `psgdpr` (`PSGDPR_CUSTOMER_FORM`, `PSGDPR_CREATION_FORM`) and `ps_emailsubscription`
  (`NW_CONDITIONS`) for every language;
- replaces the Customer Reassurance (`blockreassurance`) placeholders with three items shown in checkout and on
  product pages: *Pago seguro*, *Envío a todo Ecuador*, *Productos originales*. The module is configured
  (table `psreassurance`) rather than replaced by static markup, so the client can still edit the items in
  *Módulos > Gestor de módulos > Customer Reassurance > Configurar*.

Re-running it overwrites these texts with the values in the script; edit the script to change them permanently.

The checkout uses a minimal header (logo + WhatsApp help link, no navigation) and a footer without the
PrestaShop credit: `themes/natufitness/templates/checkout/_partials/header.tpl` and `footer.tpl`. The logo
(`PS_LOGO` image, text logo only as a fallback) is shared with the storefront header via
`templates/_partials/nf-logo.tpl`.

## Owner-editable content

The visual content of the storefront is managed in the back office; no code changes are needed. Changes show up
immediately (the modules clear their own cache when saved).

| Content | Back-office path | Recommended image |
| --- | --- | --- |
| Logo (header and checkout) | *Diseño > Tema y logotipo > Logotipo de la cabecera* | PNG with transparent background, about 300×80 px |
| Homepage hero slides | *Módulos > Gestor de módulos > Image slider > Configurar* | JPG 1920×600 px, under 300 KB; keep the important content in the center (mobile crops the sides) |
| "Potencia tu rendimiento" block | *Módulos > Gestor de módulos > Banner > Configurar* | JPG or PNG 1200×600 px |
| "Marcas Oficiales" | *Catálogo > Marcas y proveedores* | PNG with transparent background, 400×200 px |
| Product photos | *Catálogo > Productos > (producto) > Imágenes* | Square, at least 800×800 px, white background |

- **Hero slides**: each slide has an image, *Título* (large uppercase line), *Leyenda* (small line above the
  title, also the image alt text), *Descripción* (optional extra text), *URL* (target of the "VER MÁS" button;
  without URL the button is hidden), position (drag and drop) and enabled switch. Do not write text on the image
  itself: the theme draws the title and button on top. The module's *Velocidad* and *Pausa al pasar el ratón*
  settings drive the carousel.
- **Promo block**: *Imagen* is shown on the left, *Enlace* is used by the image and the "Ver productos" button,
  *Descripción* is the paragraph next to the image. The heading stays in the theme.
- **Brands**: every enabled brand is shown, ordered by name, with its logo when one is uploaded and its name
  otherwise; each tile links to the brand page. The section is hidden when no brand is enabled.

`scripts/setup-content.php` installs the defaults once:
- generates the default images with PHP GD into `themes/natufitness/assets/img/defaults/` (only when missing)
  and copies them to `modules/ps_imageslider/images/` and `modules/ps_banner/img/`;
- removes the PrestaShop demo slides and creates three NatuFitness slides only when no other slide exists, so
  slides edited by the owner are never overwritten (deleting every slide and re-running restores the defaults);
- sets the banner image, link (Nutrición deportiva) and description only while the module is still at its
  defaults;
- creates five placeholder brands (ON, BSN, MuscleTech, Universal, Dymatize, without logos) only when no brand
  other than the PrestaShop demo ones exists; the demo brands *Studio Design* and *Graphic Corner* are then
  disabled, not deleted.

`setup-branding.php` applies the default logo and favicon only once (flag `NF_BRANDING_LOGO_APPLIED`) and never
when `PS_LOGO` already points to a logo uploaded in the back office, so re-running it is safe after the owner
changes the logo.

## Panel Mi Tienda

Custom module `modules/nf_panel/` (bind-mounted) adds one back-office page, **Mi Tienda**, first in the side menu.
It is built for the store owner on a phone: big buttons, plain Spanish, two tabs.

- **Productos**: list with photo, price with IVA, offer price, stock, category and Visible/Oculto badge; search by
  name; 20 per page. One form creates and edits a product: name, sale price **with IVA** (the tax-excluded price is
  calculated from the "EC IVA 15%" / "EC IVA 0%" group), optional offer price (stored as a product-wide discount;
  empty removes it), stock, category, "Mostrar en Promociones de la portada" (adds the product to the Inicio
  category shown on the homepage), brand, photos (JPG/PNG/WEBP up to 5 MB each, all image sizes generated; choose
  the cover, × removes a photo), description and visibility. Buttons to hide/show and delete (with confirmation).
  Products with variants are listed but must be edited in the native editor.
- **Pedidos**: cards filtered by Pendientes (default: not delivered and not cancelled), Todos, Entregados and
  Cancelados, with customer, phone, payment, delivery type and address, products and the invoice data the
  customer entered (Cédula/RUC, razón social). Each card has a WhatsApp button (prefilled greeting with the order
  reference) and one-tap state buttons.

State buttons use the core order history (stock, invoices and customer emails behave as in the native order page):

| Button | Native order state | Shown when |
| --- | --- | --- |
| Pago recibido | Pago aceptado | the order is not paid yet |
| Enviado | Enviado | paid, home delivery (`Envío a domicilio`) |
| Listo para retirar | Preparación en curso | paid, store pickup (carrier name contains "Retiro"); avoids the "shipped" email |
| Entregado | Entregado | paid, prepared or shipped |
| Cancelar (asks first) | Cancelado | not delivered yet; stock goes back per core rules |

If the customer email cannot be sent (no SMTP configured), the state still changes and the panel shows a warning.

### Owner access

`setup-owner.php` installs the module and creates the **Dueño** profile, which can only open Mi Tienda and lands on
it after login. SuperAdmin keeps the full back office and also sees Mi Tienda. Create (or update) the owner's
user; keep the password in a shell variable so it is not stored in the shell history:

```bash
read -rs OWNER_PASSWORD
docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-owner.php \
  --email=dueno@ejemplo.com --firstname=Nombre --lastname=Apellido --password="$OWNER_PASSWORD"
```

The owner logs in at http://localhost:8088/admin-nf. "Ver detalle completo" (native order page) and the native
product editor only appear for employees whose profile has access to those pages.

Local cleanup of demo/test data (deletes **all** orders, the demo customer `pub@prestashop.com` and guest test
customers `*@natufitness.test` / `test-t4-*@example.com` with their carts and addresses; add `--dry-run` to only
list them). Never run it on a store with real orders:

```bash
docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-owner.php --purge-demo-orders
```

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
- `modules/nf_deuna/`, `modules/nf_panel/` — custom modules (DeUna QR payment, Mi Tienda owner panel).
- `mockups/` — static design references.
- `odd/tasks/` — feature task documents.
