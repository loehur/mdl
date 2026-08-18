#!/usr/bin/env bash
#
# Cloud Agent start phase for the MDL (nalju) monorepo.
#
# Per-boot reconciliation: bring up the MariaDB daemon, ensure the mdl_*
# databases exist, and load the schemas that ship in the repo. Idempotent and
# safe to re-run — it does not start any application dev server (those live in
# the environment's terminals).
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

log() { printf '\n\033[1;32m[start]\033[0m %s\n' "$*"; }

# ---------------------------------------------------------------------------
# 1. Start MariaDB (skip if it is already accepting connections)
# ---------------------------------------------------------------------------
if ! sudo mariadb -e 'SELECT 1' >/dev/null 2>&1; then
  log "Starting MariaDB daemon"
  sudo mkdir -p /run/mysqld
  sudo chown mysql:mysql /run/mysqld
  sudo sh -c 'nohup mariadbd-safe --datadir=/var/lib/mysql >/var/log/mariadb-safe.log 2>&1 &'

  log "Waiting for MariaDB to accept connections"
  for _ in $(seq 1 30); do
    if sudo mariadb -e 'SELECT 1' >/dev/null 2>&1; then break; fi
    sleep 1
  done
  sudo mariadb -e 'SELECT 1' >/dev/null 2>&1 || { log "MariaDB failed to start"; exit 1; }
else
  log "MariaDB already running"
fi

# ---------------------------------------------------------------------------
# 2. Allow password-less root over TCP/socket (matches dev Env.php)
# ---------------------------------------------------------------------------
log "Reconciling root auth for local dev"
sudo mariadb <<'SQL'
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING '';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
# 3. Create the mdl_* databases
# ---------------------------------------------------------------------------
log "Ensuring mdl_* databases exist"
for db in mdl_main mdl_laundry mdl_resto mdl_water mdl_salon \
          mdl_investasi mdl_invoice mdl_wadesk mdl_jaggu_school; do
  mysql -u root -h 127.0.0.1 -e \
    "CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
done

# ---------------------------------------------------------------------------
# 4. Load repo schemas (idempotent: schema files use CREATE TABLE IF NOT EXISTS)
# ---------------------------------------------------------------------------
declare -A SCHEMAS=(
  [mdl_investasi]=api/database/investasi/schema.sql
  [mdl_invoice]=api/database/invoice/schema.sql
  [mdl_jaggu_school]=api/database/jaggu_school/schema.sql
  [mdl_wadesk]=api/database/wadesk/schema.sql
)
for db in "${!SCHEMAS[@]}"; do
  schema="${SCHEMAS[$db]}"
  if [ -f "$schema" ]; then
    log "Loading schema $schema -> $db"
    mysql -u root -h 127.0.0.1 "$db" < "$schema"
  fi
done

log "start phase complete — databases ready"
