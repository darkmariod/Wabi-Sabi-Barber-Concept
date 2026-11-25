#!/bin/sh
# Restore a NatuFitness export (made with scripts/export-store.sh) on the TARGET server.
#
# Run from the project directory (with .env present):
#   cd /opt/natufitness && sh scripts/restore-store.sh /path/natufitness-export-STAMP.tar.gz [--force]
#
# Steps: start the db, replace the store database with the dump, load the PrestaShop
# files into the ps_data volume, point app/config/parameters.php at the .env database,
# then start the full stack. Afterwards run scripts/change-domain.php (printed at the end).
#
# Safety: refuses when the prestashop container is running or the ps_data volume already
# has files, unless --force (which wipes ps_data and the store database first).
# Overrides:
#   COMPOSE="docker compose"                                  compose command
#   COMPOSE_PROJECT=name                                      compose project name
#   COMPOSE_FILES="docker-compose.yml docker-compose.prod.yml" files used (rehearsal: only docker-compose.yml)
# Passwords are read inside the containers from their environment; never printed.
set -eu

COMPOSE=${COMPOSE:-docker compose}
COMPOSE_PROJECT=${COMPOSE_PROJECT:-}
COMPOSE_FILES=${COMPOSE_FILES:-docker-compose.yml docker-compose.prod.yml}

ARCHIVE=""
FORCE=0
for arg in "$@"; do
  case "$arg" in
    --force) FORCE=1 ;;
    -*) echo "Unknown option: $arg" >&2; exit 2 ;;
    *) ARCHIVE=$arg ;;
  esac
done

if [ -z "$ARCHIVE" ] || [ ! -f "$ARCHIVE" ]; then
  echo "Usage: sh scripts/restore-store.sh /path/natufitness-export-STAMP.tar.gz [--force]" >&2
  exit 2
fi
if [ ! -f .env ]; then
  echo "FAILED: .env not found in $(pwd). Run from the project directory." >&2
  exit 1
fi

FILE_ARGS=""
for f in $COMPOSE_FILES; do
  [ -f "$f" ] || { echo "FAILED: compose file $f not found" >&2; exit 1; }
  FILE_ARGS="$FILE_ARGS -f $f"
done

dc() {
  # shellcheck disable=SC2086
  $COMPOSE $FILE_ARGS ${COMPOSE_PROJECT:+-p "$COMPOSE_PROJECT"} "$@"
}

PROJECT_NAME=$(dc config | sed -n 's/^name: *//p' | head -1 || true)
[ -n "$PROJECT_NAME" ] || { echo "FAILED: could not read the compose project name" >&2; exit 1; }
PS_VOLUME="${PROJECT_NAME}_ps_data"
PS_IMAGE=$(dc config --images | grep -m1 'prestashop' || true)
[ -n "$PS_IMAGE" ] || { echo "FAILED: could not read the prestashop image" >&2; exit 1; }

echo "==> Project $PROJECT_NAME (files:$FILE_ARGS)"

echo "==> Safety checks"
if [ -n "$(dc ps -q --status running prestashop 2>/dev/null)" ]; then
  if [ "$FORCE" -ne 1 ]; then
    echo "FAILED: the prestashop container is running. Stop it or use --force." >&2
    exit 1
  fi
  echo "    --force: stopping prestashop"
  dc stop prestashop
fi
if docker volume inspect "$PS_VOLUME" >/dev/null 2>&1; then
  if ! docker run --rm -v "$PS_VOLUME:/data" --entrypoint sh "$PS_IMAGE" -c '[ -z "$(ls -A /data)" ]'; then
    if [ "$FORCE" -ne 1 ]; then
      echo "FAILED: volume $PS_VOLUME already has files. Use --force to replace them." >&2
      exit 1
    fi
    echo "    --force: wiping $PS_VOLUME"
    docker run --rm -v "$PS_VOLUME:/data" --entrypoint sh "$PS_IMAGE" -c 'find /data -mindepth 1 -delete'
  fi
else
  # Labelled like compose would, so compose adopts it without warnings.
  docker volume create \
    --label "com.docker.compose.project=$PROJECT_NAME" \
    --label "com.docker.compose.volume=ps_data" \
    "$PS_VOLUME" >/dev/null
fi

echo "==> Extracting archive"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT INT TERM
tar -xzf "$ARCHIVE" -C "$TMP"
for part in db.sql.gz files.tar.gz; do
  [ -f "$TMP/$part" ] || { echo "FAILED: $part missing in the archive" >&2; exit 1; }
done
[ -f "$TMP/MANIFEST.txt" ] && cat "$TMP/MANIFEST.txt"
chmod 755 "$TMP"
chmod 644 "$TMP/files.tar.gz"

echo "==> Starting database"
dc up -d db
DB_ID=$(dc ps -q db)
i=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$DB_ID")" = "healthy" ]; do
  i=$((i + 1))
  [ "$i" -le 60 ] || { echo "FAILED: database not healthy after 5 minutes" >&2; exit 1; }
  sleep 5
done

echo "==> Importing database"
dc exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" 2>/dev/null -e "
  DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`;
  CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* TO \"$MYSQL_USER\"@\"%\";
  FLUSH PRIVILEGES;"'
gunzip -c "$TMP/db.sql.gz" \
  | dc exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" 2>/dev/null'

echo "==> Loading PrestaShop files into $PS_VOLUME"
# Plain docker run (no compose bind mounts), so the git-tracked theme/modules are never touched.
docker run --rm -v "$PS_VOLUME:/var/www/html" -v "$TMP:/restore:ro" --entrypoint sh "$PS_IMAGE" \
  -c 'tar -xzf /restore/files.tar.gz -C /var/www/html && chown -R www-data:www-data /var/www/html'

echo "==> Pointing parameters.php at the .env database"
# Values come from the service environment (DB_SERVER/DB_NAME/DB_USER/DB_PASSWD); only key names are printed.
dc run --rm --no-deps -T --user www-data --entrypoint php prestashop -r '
$f = "/var/www/html/app/config/parameters.php";
if (!is_file($f)) { fwrite(STDERR, "parameters.php not found\n"); exit(1); }
$p = require $f;
$want = ["database_host" => getenv("DB_SERVER"), "database_name" => getenv("DB_NAME"),
         "database_user" => getenv("DB_USER"), "database_password" => getenv("DB_PASSWD")];
$changed = [];
foreach ($want as $k => $v) {
    if ($v !== false && ($p["parameters"][$k] ?? null) !== $v) { $p["parameters"][$k] = $v; $changed[] = $k; }
}
if ($changed) {
    file_put_contents($f, "<?php return " . var_export($p, true) . ";\n");
    if (function_exists("opcache_invalidate")) { opcache_invalidate($f, true); }
}
echo "    " . ($changed ? "updated: " . implode(", ", $changed) : "already matches .env") . PHP_EOL;
'

echo "==> Starting the stack"
dc up -d

ADMIN_HINT=$(dc config | sed -n 's/^ *PS_FOLDER_ADMIN: *//p' | head -1 | tr -d '"')
echo
echo "OK store restored."
echo "Next step: set the new domain and HTTPS:"
echo "  $COMPOSE$FILE_ARGS exec -T -u www-data prestashop php /opt/natufitness/scripts/change-domain.php --domain=YOUR-DOMAIN"
[ -n "$ADMIN_HINT" ] && echo "Admin panel after that: https://YOUR-DOMAIN/$ADMIN_HINT/"
exit 0
