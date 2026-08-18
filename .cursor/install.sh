#!/usr/bin/env bash
#
# Cloud Agent install phase for the MDL (nalju) monorepo.
#
# Idempotent repository bootstrap: system toolchain (guarded), PHP dev config,
# and dependency installation for the Node microservices and Vue frontends.
# Per-boot service startup lives in start.sh, not here.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

log() { printf '\n\033[1;34m[install]\033[0m %s\n' "$*"; }

# ---------------------------------------------------------------------------
# 1. System toolchain (no-op when the base image / snapshot already has it)
# ---------------------------------------------------------------------------
if ! command -v php >/dev/null 2>&1 || ! command -v mariadbd-safe >/dev/null 2>&1; then
  log "Installing PHP 8.3 + MariaDB (missing from base image)"
  export DEBIAN_FRONTEND=noninteractive
  sudo apt-get update -y
  sudo apt-get install -y \
    php8.3-cli php8.3-mysqli php8.3-mbstring php8.3-curl \
    php8.3-gd php8.3-xml php8.3-zip php8.3-bcmath \
    mariadb-server
else
  log "PHP and MariaDB already present — skipping apt install"
fi

# ---------------------------------------------------------------------------
# 2. API PHP config (dev mode, local MariaDB over TCP loopback)
# ---------------------------------------------------------------------------
if [ ! -f api/app/Config/Env.php ]; then
  log "Creating api/app/Config/Env.php from template"
  cp api/app/Config/Env.example.php api/app/Config/Env.php
fi
# Use TCP loopback so the DB connection does not depend on the unix-socket path.
sed -i "s/const DB_HOST = '[^']*';/const DB_HOST = '127.0.0.1';/" api/app/Config/Env.php

# ---------------------------------------------------------------------------
# 3. Node microservice dependencies + local env files
# ---------------------------------------------------------------------------
for dir in node/*/; do
  [ -f "${dir}package.json" ] || continue
  log "npm install ${dir}"
  ( cd "$dir" && { npm ci || npm install; } )
  if [ -f "${dir}.env.example" ] && [ ! -f "${dir}.env" ]; then
    cp "${dir}.env.example" "${dir}.env"
  fi
done

# ---------------------------------------------------------------------------
# 4. Vue frontend dependencies (build is done via the vite dev server / on demand)
# ---------------------------------------------------------------------------
for dir in frontend/*/; do
  [ -f "${dir}package.json" ] || continue
  log "npm install ${dir}"
  ( cd "$dir" && { npm ci || npm install; } )
done

log "install phase complete"
