#!/bin/sh
# Export the full NatuFitness store (database + PrestaShop files) into one archive,
# to move it to another server with scripts/restore-store.sh.
#
# Run on the SOURCE server, from anywhere:
#   sh /opt/natufitness/scripts/export-store.sh
# Result: $PROJECT_DIR/exports/natufitness-export-STAMP.tar.gz (chmod 600) containing
#   db.sql.gz     mysqldump of the store database (verified complete)
#   files.tar.gz  /var/www/html of the prestashop container, without caches/logs and
#                 without the theme and nf_* modules (those come from git)
#   MANIFEST.txt  date, PrestaShop version, counts and sizes
#
# Read-only on the source: it only runs mysqldump and tar inside the running containers.
# Overrides (to run it locally, e.g. for a rehearsal):
#   PROJECT_DIR=$PWD COMPOSE="docker compose --env-file /path/.env" COMPOSE_PROJECT=name sh scripts/export-store.sh
# The database password is read inside the db container from its environment; never printed.
set -eu

PROJECT_DIR=${PROJECT_DIR:-/opt/natufitness}
COMPOSE=${COMPOSE:-docker compose}
COMPOSE_PROJECT=${COMPOSE_PROJECT:-}
EXPORT_DIR="$PROJECT_DIR/exports"
STAMP=$(date +%Y%m%d-%H%M)
NAME="natufitness-export-$STAMP"
TARGET="$EXPORT_DIR/$NAME.tar.gz"

dc() {
  # shellcheck disable=SC2086
  $COMPOSE ${COMPOSE_PROJECT:+-p "$COMPOSE_PROJECT"} "$@"
}

sql() {
  # Runs one query in the store database and prints the raw value(s).
  dc exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -B "${MYSQL_DATABASE:-prestashop}" -e "$0" 2>/dev/null' "$1"
}

cd "$PROJECT_DIR"
mkdir -p "$EXPORT_DIR"
chmod 700 "$EXPORT_DIR"
WORK=$(mktemp -d "$EXPORT_DIR/.work.XXXXXX")
trap 'rm -rf "$WORK"' EXIT INT TERM
umask 077

echo "==> Checking containers"
dc exec -T db true
dc exec -T prestashop true

echo "==> Dumping database"
dc exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --no-tablespaces "${MYSQL_DATABASE:-prestashop}" 2>/dev/null' \
  | gzip -9 > "$WORK/db.sql.gz"
if ! gunzip -c "$WORK/db.sql.gz" | tail -1 | grep -q 'Dump completed'; then
  echo "FAILED: incomplete database dump" >&2
  exit 1
fi

echo "==> Archiving PrestaShop files"
dc exec -T prestashop tar -czf - -C /var/www/html \
  --exclude='./var/cache/*' \
  --exclude='./var/logs/*' \
  --exclude='./themes/natufitness' \
  --exclude='./modules/nf_deuna' \
  --exclude='./modules/nf_panel' \
  --exclude='./modules/nf_orderalerts' \
  . > "$WORK/files.tar.gz"
gzip -t "$WORK/files.tar.gz"

echo "==> Writing manifest"
{
  echo "NatuFitness store export"
  echo "date: $(date '+%F %T %z')"
  echo "prestashop_version: $(sql "SELECT value FROM ps_configuration WHERE name='PS_VERSION_DB' LIMIT 1")"
  echo "shop_domain: $(sql "SELECT value FROM ps_configuration WHERE name='PS_SHOP_DOMAIN' LIMIT 1")"
  echo "products: $(sql 'SELECT COUNT(*) FROM ps_product')"
  echo "orders: $(sql 'SELECT COUNT(*) FROM ps_orders')"
  echo "customers: $(sql 'SELECT COUNT(*) FROM ps_customer')"
  echo "db_sql_gz_bytes: $(wc -c < "$WORK/db.sql.gz" | tr -d ' ')"
  echo "files_tar_gz_bytes: $(wc -c < "$WORK/files.tar.gz" | tr -d ' ')"
} > "$WORK/MANIFEST.txt"

tar -czf "$TARGET.tmp" -C "$WORK" MANIFEST.txt db.sql.gz files.tar.gz
mv "$TARGET.tmp" "$TARGET"
chmod 600 "$TARGET"

cat "$WORK/MANIFEST.txt"
echo "OK $TARGET $(du -h "$TARGET" | cut -f1)"
