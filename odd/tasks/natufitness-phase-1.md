# NatuFitness store — Phase 1

## Objective
Launch an online store for NatuFitness (Riobamba, Ecuador) modeled on the structure of syf.ec, built on PrestaShop, selling with Datafast cards, DeUna static QR and bank transfer. Invoices are requested manually via WhatsApp (automatic SRI invoicing is Phase 2, blocked on the client's .p12 signature).

## Scope (Phase 1)
- Local PrestaShop 8.2 + MySQL 8 via Docker Compose.
- Spanish storefront, Ecuador, USD.
- Category tree matching the client's Instagram highlights.
- Free shipping from $35.
- Optional invoice fields (ID/RUC, company) at checkout.
- Child theme with NatuFitness branding (blue #17507d / green #6cbf3c) following mockups/home.html.
- Datafast (COPYandPAY) payment module, DeUna static QR and bank transfer as manual-confirmation methods.
- WhatsApp floating button and "request invoice" button on order confirmation.

## Out of scope
- SRI electronic invoicing automation (Phase 2).
- DeUna dynamic QR API (Phase 2).
- Loyalty points (Phase 2).

## Constraints
- Host port 3306 is already in use; MySQL is not exposed to the host.
- Secrets live in `.env` (gitignored); `.env.example` documents the keys.
- Datafast test credentials are pending from the client.

## Tasks
- [x] T1 — Docker Compose stack (PrestaShop 8.2 + MySQL 8) with automated Spanish/Ecuador install. Route: inline. Commit 2b0cc89.
- [x] T2 — Seed script: categories, free shipping $35, invoice fields enabled. Route: inline.
- [ ] T3 — NatuFitness child theme (header, nav, hero, product cards, categories, footer, WhatsApp). Route: delegated writer.
- [ ] T4 — Bank transfer + DeUna static QR payment methods. Route: TBD.
- [ ] T5 — Datafast COPYandPAY payment module (blocked on test credentials). Route: delegated writer.
- [ ] T6 — Order confirmation: "request invoice via WhatsApp" button. Route: TBD.

## Acceptance criteria
- `docker compose up -d` yields a working store at http://localhost:8088 in Spanish with USD prices.
- Back office reachable at the configured admin folder.
- Categories visible in the storefront menu; carts over $35 ship free.

## Checks
- Storefront and back office load in the browser (HTTP 200, visual check).
- Seed script is idempotent (running twice creates no duplicates).

## Progress
- 2026-10-19: Repository initialized, branch `feat/phase-1-store`. Docker 29.1.3 running, arm64 images available.

- 2026-10-19: T1 done. Host ports 8080 and 3306 were taken; store runs on 8088 (`PS_PORT`), MySQL unexposed. Install defaulted to EUR — fixed by the seed.
- 2026-10-19: T2 done. `scripts/seed.php` sets USD/Ecuador/Spanish-only, free shipping $35, optional dni/company address fields, 12 categories + main menu, removes demo catalog. Verified: second run creates nothing; storefront menu shows the categories; back office login loads in Spanish.

## Known gaps
- Classic theme strings (e.g. search placeholder) still show in English: Spanish theme translations need importing (T3).
- Demo banners/sliders from the classic theme remain until T3.

## Next step
T3 — NatuFitness child theme based on mockups/home.html.
