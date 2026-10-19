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

## Project layout

- `docker-compose.yml` — PrestaShop + MySQL stack.
- `scripts/` — CLI scripts mounted read-only at `/opt/natufitness/scripts`.
- `mockups/` — static design references.
- `odd/tasks/` — feature task documents.
