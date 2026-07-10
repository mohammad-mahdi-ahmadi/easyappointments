# Booking runtime (multi-tenant Easy!Appointments)

A shared Easy!Appointments runtime (one php-fpm+nginx app container + one MariaDB)
that serves many isolated tenants, one small database per tenant, mounted at
`/booking` under each business subdomain. This directory holds only the runtime
stack; the tenant resolution and lifecycle live under `../tenancy/`.

Design of record: [multi-tenant booking runtime design](../../docs/superpowers/specs/2026-07-10-multi-tenant-booking-runtime-design.md)
(in the Booking repo). This tree is the Easy!Appointments fork; the design/ADRs
are versioned separately in the Booking project.

## What is here

| File | Role |
| --- | --- |
| `Dockerfile` | Multi-stage build (node asset compile, composer vendor, `php:8.3-fpm` runtime) from the fork root. |
| `docker-compose.yml` | `app` (bound to `127.0.0.1:9101`) + `db` (`mariadb:11.8`), with per-service memory limits. |
| `nginx-app.conf` | In-container nginx; passes the request `Host` and `X-Forwarded-Proto` through to php-fpm. |
| `config.stub.php` | Copied to the git-ignored `/config.php` in the image; requires `deploy/tenancy/bootstrap.php`. |
| `.env.example` | Shape of the runtime `.env` (the real one holds `DB_ROOT_PASSWORD` and is never committed). |

## Build and run

All paths below are relative to `deploy/booking-runtime/` in the fork. The committed
`docker-compose.yml` builds the app image from the fork root (`context: ../..`) and creates
`registry/`, `storage/`, and `db-data/` here (all git-ignored).

```bash
cd deploy/booking-runtime
cp .env.example .env && chmod 600 .env      # then set DB_ROOT_PASSWORD
# The bind-mounted storage tree must be writable by the container's app user (php-fpm runs as
# www-data = uid 33); EA refuses to start otherwise.
mkdir -p storage/cache storage/logs storage/sessions storage/uploads registry db-data
chown -R 33:33 storage
docker compose up -d --build
```

The app answers only for provisioned tenants: an unmapped `Host` returns `404`
(`bootstrap.php`). Bind is loopback-only; a host nginx vhost proxies each
business's `/booking/` to `127.0.0.1:9101`.

## Provision / deprovision a tenant

Run the Provider Driver CLI with `BOOKING_RUNTIME_DIR` pointing at the runtime dir (the CLI
defaults to `/opt/booking-runtime`; set it explicitly when running from the fork tree):

```bash
cd deploy/booking-runtime
export BOOKING_RUNTIME_DIR="$(pwd)" BOOKING_BASE_DOMAIN=cyprusinfo.dev
CLI=../tenancy/provision-tenant
$CLI provision demo-salon --company "Aphrodite Beauty & Spa"
$CLI seed demo-salon ../tenancy/seed/demo-salon.sql
$CLI serving-fragment demo-salon   # emits the nginx /booking/ block
$CLI health demo-salon             # prints the HTTP status
$CLI list
```

`provision` is idempotent: re-running with an existing DB + schema is a no-op.

## Teardown (no platform impact)

```bash
cd deploy/booking-runtime
export BOOKING_RUNTIME_DIR="$(pwd)"
../tenancy/provision-tenant deprovision demo-salon   # drop DB + user + registry + storage
rm -f /etc/nginx/sites-enabled/booking-demo-demo-salon.conf \
      /etc/nginx/sites-available/booking-demo-demo-salon.conf
nginx -t && systemctl reload nginx
docker compose down -v                               # removes the stack + db-data
```

> **Live server layout differs.** On `test-web-01` the runtime lives at `/opt/booking-runtime`
> with this fork cloned to `./src`, the image pre-built, and the compose file hand-authored to
> reference that image (so `BOOKING_RUNTIME_DIR=/opt/booking-runtime` and the CLI is at
> `./src/deploy/tenancy/provision-tenant`). That exact procedure is the multi-tenant demo runbook
> in the Booking project's `docs/guides/`.
