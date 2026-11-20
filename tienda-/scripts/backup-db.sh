#!/bin/sh
# Daily database backup of the NatuFitness store (orders, customers, products, settings).
# Keeps the last 14 days in /opt/natufitness/backups as gzipped SQL.
# Product photos are not included: the catalog photos live in the git repository
# (scripts/catalog-2026, scripts/catalog-photos) and are reloaded by the import scripts.
#
# Installed on the VPS as a root cron job (03:30 every day):
#   30 3 * * * /opt/natufitness/scripts/backup-db.sh >> /opt/natufitness/backups/backup.log 2>&1
# Restore: gunzip -c FILE.sql.gz | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "${MYSQL_DATABASE:-prestashop}"'
set -eu

PROJECT_DIR=/opt/natufitness
BACKUP_DIR="$PROJECT_DIR/backups"
KEEP_DAYS=14
STAMP=$(date +%Y%m%d-%H%M)
TARGET="$BACKUP_DIR/natufitness-$STAMP.sql.gz"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
cd "$PROJECT_DIR"

# The password is read inside the db container from its own environment; it is never printed.
docker compose exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --routines --no-tablespaces "${MYSQL_DATABASE:-prestashop}" 2>/dev/null' \
  | gzip -9 > "$TARGET.tmp"

# A valid dump ends with "Dump completed"; refuse to keep a truncated file.
if gunzip -c "$TARGET.tmp" | tail -1 | grep -q 'Dump completed'; then
  mv "$TARGET.tmp" "$TARGET"
  chmod 600 "$TARGET"
  echo "$(date '+%F %T') OK $TARGET $(du -h "$TARGET" | cut -f1)"
else
  rm -f "$TARGET.tmp"
  echo "$(date '+%F %T') FAILED: incomplete dump" >&2
  exit 1
fi

find "$BACKUP_DIR" -name 'natufitness-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
