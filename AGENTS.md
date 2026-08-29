# AGENTS.md

## Cursor Cloud specific instructions

This is the **NALJU / MDL** monorepo: a shared PHP REST API (`api/`), several Vue 3 + Vite
frontends (`frontend/*`), Node.js sidecar servers (`node/*`), legacy PHP POS apps
(`laundry/`, `resto/`, `water/`), and Android companions (`android/`). There is no Docker,
Makefile, or CI in the repo — the dev stack is Apache + PHP 8.3 + MariaDB + Node 22.

### What the startup update script already does
Runs `npm install` in every `node/*` and `frontend/*` package. You do **not** need to
reinstall JS deps manually.

### Pre-provisioned in the VM snapshot (do not reinstall)
- Apache2 + `libapache2-mod-php` (mod_php 8.3), `mod_rewrite` enabled.
- PHP 8.3 CLI + extensions: `mysqli`, `curl`, `mbstring`, `gd`, `xml`, `zip`.
- MariaDB 10.11 with all 9 app databases created and the committed schemas loaded
  (`mdl_main`, `mdl_laundry`, `mdl_resto`, `mdl_water`, `mdl_salon`, `mdl_investasi`,
  `mdl_invoice`, `mdl_wadesk`, `mdl_jaggu_school`). MariaDB data persists in the snapshot.
- `api/app/Config/Env.php` (git-ignored) copied from `Env.example.php` — `MODE=dev`,
  DB user `root` with empty password.
- `.env` files (git-ignored) for each `node/*` server, copied from their `.env.example`.

### Non-obvious gotchas
- **MariaDB socket path mismatch:** `/var/run` is a real directory here (NOT a symlink to
  `/run`), so PHP's default `mysqli.default_socket` (`/var/run/mysqld/mysqld.sock`) does not
  match MariaDB's actual socket (`/run/mysqld/mysqld.sock`). This is fixed by
  `/etc/php/8.3/mods-available/mdl-socket.ini` (symlinked into the apache2 + cli `conf.d`),
  which points PHP at `/run/mysqld/mysqld.sock`. If DB calls fail with
  "No such file or directory" after a snapshot change, verify that ini is still in place.
- **`root@localhost` uses `mysql_native_password` with an empty password** (changed from the
  default `unix_socket`) so `www-data` under Apache can connect with the dev creds. Dev only.
- **The API front controller uses relative `require`/`file_exists` paths** (e.g.
  `require_once 'app/init.php'`, `file_exists('app/Controllers/...')`), so PHP's CWD must be
  `api/`. Apache mod_php sets CWD to the script dir automatically; if you serve the API with
  `php -S`, launch it from inside `api/` or it will 404/500.
- **Frontends expect the repo mounted at `http://localhost/mdl/`.** Vite dev proxies forward
  `/api` → `http://localhost/mdl` and `/Invoice` etc. → `http://localhost/mdl/api`. Apache is
  configured with `Alias /mdl /workspace` (see `/etc/apache2/conf-available/mdl.conf`).

### Starting services (not started automatically)
Services are NOT auto-started on boot. Start what you need:
- **MariaDB:** `sudo mysqld_safe &` (then `sudo mysqladmin ping` to confirm).
- **Apache (serves API + built SPAs at `/mdl`):** `sudo apachectl start` (or `graceful` to
  reload). Verify: `curl http://localhost/mdl/api/` → JSON `"NALJU Backends API"`.
- **A frontend dev server:** `cd frontend/<app> && npm run dev -- --host 0.0.0.0`
  (Vite, default port 5173).
- **Node sidecars (optional):** `cd node/<server> && npm start` (or `npm run dev`).
  Default ports: `wa_server` 3003, `qr_server` 3001, `mail_server` 3002, `wadesk_server`
  3010, `cron_server` 3011.

### Standard commands
Per-app scripts live in each `package.json` (`dev` / `build` / `preview` for Vite frontends;
`start` / `dev` for Node servers). Frontend `build` outputs to `public/<app>/`. There is no
lint or automated-test tooling configured in this repo.

### Known limitations for local E2E
- **CRM frontend** (`frontend/crm`) hardcodes production URLs (`https://api.nalju.com`,
  `wss://waserver.nalju.com`) instead of using a Vite proxy, so it is not wired for pure
  local E2E without code changes.
- **Legacy PHP apps** (`laundry/`, `resto/`, `water/`) need git-ignored `app/Config/*`
  (`URL.php`, `DBC.php`) files and `.htaccess` that are NOT in the repo, and their MySQL
  schemas are not committed — they cannot run without team-provided config/schema.
- Only `investasi`, `invoice`, `wadesk`, `jaggu_school` (+ a `crm` migration on `mdl_main`)
  have committed SQL schemas; the other databases are created empty.
- The **Invoice** login is allow-listed to specific emails (see
  `api/app/Controllers/Invoice/Auth.php`); seed a matching row in `mdl_invoice.users` with a
  `password_hash()` password to log in during dev.
