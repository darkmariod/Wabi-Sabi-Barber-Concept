# NatuFitness delivery as Docker images

## Objective
Deliver the live store (natufitness.sevenbarber.app, behind Cloudflare) as two Docker
images plus a compose file, so it runs on any server/domain, without breaking what is live.

## Problem / why
The store runs on a shared VPS. The owner needs a self-contained copy (products, catalogs,
photos, settings) to deploy on his own server and domain. Email notifications wait for the
Gmail app password, so they are configured after delivery.

## Scope
- `natufitness-web` (PrestaShop + shop files) and `natufitness-db` (MySQL + store dump).
- Compose with an optional Caddy HTTPS profile; works behind Cloudflare.
- On start: database settings from env; automatic domain switch (scripts/change-domain.php).
- Spanish delivery guide (docs/entrega-docker.md → LEEME.md).

## Constraints
- Do not change anything deployed on the VPS (export is read-only).
- Data (dump, files, image tarball) stays outside git: ~/Desktop/natufitness-entrega/.
- Images are private (they hold the shop's secret keys); never push to a public registry.

## Tasks
- [x] T1 Read-only export from the VPS (db 320K gz, files 160M gz; 242 active products, 31 categories, 0 orders)
- [x] T2 Dockerfile.web / Dockerfile.db / init script / compose / .env.example / Caddyfile
- [x] T3 change-domain.php
- [x] T4 Local rehearsal from the images on an isolated stack (port 8099)
- [x] T5 Store checks: 27 menu catalogs 200, one product per catalog with photo + cart button, search, no HTTP/JS errors
- [x] T6 Owner panel checks: login, menu, header cleanup, product create with photo/edit/delete, catalog create/delete, Portada, Pedidos
- [x] T7 Purchase with bank transfer on desktop, Android 360 and iPhone 13 (WebKit); order reaches Pedidos; paid → delivered
- [ ] T8 amd64 images saved as natufitness-images-1.0.tar.gz + delivery folder
- [ ] T9 Commit

## Fixes found during the rehearsal
- Dump file copied with mode 600 → MySQL init could not read it (COPY --chmod=644).
- The base image already contains a fresh PrestaShop (install/, admin/) → admin login blocked
  ("delete /install"); the web image now empties /var/www/html before adding the export.
- First start imports the whole store → db healthcheck start_period 600s.

## Not covered
- Email notifications (pending Gmail app password; setup-mail.php after install).
- The amd64 image is built under emulation; it was not run on a real amd64 server.
- Paused alternative path (export/restore scripts, docker-compose.prod.yml) is left uncommitted and unverified.

## Route
- Parent inline: export, build, rehearsal and checks (command-driven, verified step by step).
- First writer delegation was paused by the user; its partial files were superseded.
