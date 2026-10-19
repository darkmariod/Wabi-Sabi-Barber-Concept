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
- [x] T3 — NatuFitness child theme replicating syf.ec layout (header, nav, hero, product cards, categories, footer, WhatsApp). Route: delegated writer (writer trigger: 15 files).
- [x] T4 — Bank transfer + DeUna static QR payment methods, plus Ecuador carriers and IVA groups found missing during browser test. Route: delegated writer (module, 8 files) + inline seed fix.
- [ ] T5 — Datafast COPYandPAY payment module (blocked on test credentials). Route: delegated writer.
- [x] T6 — Order confirmation: "request invoice via WhatsApp" button. Route: inline (1 template + CSS).
- [x] T7 — Checkout branding polish: NatuFitness logo in checkout header, Spanish privacy text, reassurance items, NatuFitness footer, shop logo/favicon, shop name. Route: delegated writer + inline fixes (icon URL, duplicate terms link).

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

- 2026-10-19: T3 done. Child theme `themes/natufitness` (bind-mounted) + `scripts/activate-theme.php` (idempotent, installs es pack, enables theme, clears caches). Verified: home 200, no demo strings, category page 200, desktop 1280px and mobile 375px (scrollWidth 375) visually match syf.ec structure.

- 2026-10-19: T4 done. `modules/nf_deuna` (static QR, order state "Esperando pago DeUna", WhatsApp receipt button, NF_WHATSAPP_NUMBER config shared with theme) + `scripts/setup-payments.php` (wire placeholders, cheque/COD disabled) + wirepayment return override. Browser test found no carrier for Ecuador (zone 6) and a $2 handling fee: seed now configures "Retiro en local Riobamba" (free) and "Envío a domicilio" ($4 placeholder, free from $35), handling 0, and IVA 15% / 0% tax groups. Verified in browser: guest checkout order IXYZZBPEW (DeUna, $24, QR + WhatsApp link) and GROKYSIBJ (transfer, $24, WhatsApp link); seed reruns are no-ops.

- 2026-10-19: T7 done. `scripts/setup-branding.php` (shop name, PS_LOGO/favicon, Spanish CUSTPRIV/PSGDPR/NW texts, 3 reassurance items) + checkout header/footer overrides + shared `nf-logo.tpl`. Browser review found reassurance icons with `//modules/...` URLs (protocol-relative, broken) — fixed to `/modules/...`; and a duplicated terms link in the checkout footer — fixed. Verified in browser through the payment step at 1280px and 375px (scrollWidth 375), all Spanish, no demo strings.

- 2026-10-19: T6 done. `themes/natufitness/templates/checkout/order-confirmation.tpl` extends classic and appends a "¿Necesitas factura?" card to `hook_payment_return`; WhatsApp message prefilled with order ref, total, email and — when the invoice address has them — Cédula/RUC and company. Verified in browser: order OVQXEJRXX (DeUna, with RUC) and XXEUUMGNV (transfer + store pickup, without RUC).

## Known gaps
- WhatsApp number is placeholder 593000000000 in `themes/natufitness/templates/_partials/nf-config.tpl`.
- Brand tiles (ON, BSN, ...) are placeholders; client must confirm brands carried.
- Hero/promo use CSS illustrations; replace with real NatuFitness banners.
- Facebook/TikTok URLs unknown (icons hidden).
- Custom header/footer drop displayNav1/2, displayTop, displayFooterBefore, displayFooter hooks.
- Checkout uses classic partials (no WhatsApp button there; handled in T6).

- Bank details, DeUna QR and home delivery rate ($4) are placeholders.
- Products need the "EC IVA 15%" or "EC IVA 0%" tax group when loaded.
- Test product `test_t4` (id 20) and test orders 7–13 exist in the local DB.

## Next step
T5 — Datafast COPYandPAY module (blocked on the client's Dataweb test credentials).
