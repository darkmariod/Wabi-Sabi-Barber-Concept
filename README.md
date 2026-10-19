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
- **WhatsApp number, social links and free-shipping amount**: edit
  `themes/natufitness/templates/_partials/nf-config.tpl`. The number is currently the placeholder
  `593000000000` (format: `593` + mobile number without the leading `0`). It feeds the floating
  "Escríbenos" button and the footer icon. Clear the cache afterwards (re-run `activate-theme.php`).

## Project layout

- `docker-compose.yml` — PrestaShop + MySQL stack.
- `scripts/` — CLI scripts mounted read-only at `/opt/natufitness/scripts`.
- `themes/natufitness/` — storefront child theme.
- `mockups/` — static design references.
- `odd/tasks/` — feature task documents.
