#!/bin/sh
# Runs as root on every container start, before Apache.
# 1. Points app/config/parameters.php at the database given in the environment
#    (DB_SERVER, DB_NAME, DB_USER, DB_PASSWD), so the owner can choose new passwords.
# 2. When PS_DOMAIN differs from the shop domain in the database, moves the shop to it
#    (PS_SSL=1 for HTTPS, the default; 0 for plain HTTP such as local tests).
# Values are never printed.
set -eu
cd /var/www/html

runuser -u www-data -- php -r '
$f = "app/config/parameters.php";
$p = require $f;
$changed = [];
foreach (["database_host" => "DB_SERVER", "database_name" => "DB_NAME", "database_user" => "DB_USER", "database_password" => "DB_PASSWD"] as $key => $env) {
    $value = getenv($env);
    if ($value !== false && $value !== "" && ($p["parameters"][$key] ?? null) !== $value) {
        $p["parameters"][$key] = $value;
        $changed[] = $key;
    }
}
if ($changed) {
    file_put_contents($f, "<?php return " . var_export($p, true) . ";\n");
}
echo "* NatuFitness: database settings " . ($changed ? "updated (" . implode(", ", $changed) . ")" : "ok") . PHP_EOL;
'

[ -n "${PS_DOMAIN:-}" ] || { echo "* NatuFitness: PS_DOMAIN not set, keeping the shop domain"; exit 0; }

# Wait for the database (first start imports the store, which takes a moment).
i=0
until runuser -u www-data -- php -r '$p=(require "app/config/parameters.php")["parameters"]; try { new PDO("mysql:host=".$p["database_host"].";dbname=".$p["database_name"], $p["database_user"], $p["database_password"]); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
  i=$((i + 1))
  [ "$i" -le 60 ] || { echo "* NatuFitness: database not reachable, skipping domain check"; exit 0; }
  sleep 3
done

CURRENT=$(runuser -u www-data -- php -r '$p=(require "app/config/parameters.php")["parameters"]; $db=new PDO("mysql:host=".$p["database_host"].";dbname=".$p["database_name"], $p["database_user"], $p["database_password"]); echo $db->query("SELECT value FROM ".$p["database_prefix"]."configuration WHERE name=\"PS_SHOP_DOMAIN\" LIMIT 1")->fetchColumn();')
SSL_NOW=$(runuser -u www-data -- php -r '$p=(require "app/config/parameters.php")["parameters"]; $db=new PDO("mysql:host=".$p["database_host"].";dbname=".$p["database_name"], $p["database_user"], $p["database_password"]); echo (int) $db->query("SELECT value FROM ".$p["database_prefix"]."configuration WHERE name=\"PS_SSL_ENABLED\" LIMIT 1")->fetchColumn();')
if [ "$CURRENT" != "$PS_DOMAIN" ] || [ "$SSL_NOW" != "${PS_SSL:-1}" ]; then
  runuser -u www-data -- php /opt/natufitness/scripts/change-domain.php --domain="$PS_DOMAIN" --ssl="${PS_SSL:-1}"
else
  echo "* NatuFitness: domain $PS_DOMAIN ok"
fi
