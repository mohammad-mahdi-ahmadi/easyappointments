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

```bash
cp deploy/booking-runtime/.env.example /opt/booking-runtime/.env   # then set DB_ROOT_PASSWORD
cd /opt/booking-runtime
docker compose -f docker-compose.yml build app
docker compose up -d
```

The app answers only for provisioned tenants: an unmapped `Host` returns `404`
(`bootstrap.php`). Bind is loopback-only; a host nginx vhost proxies each
business's `/booking/` to `127.0.0.1:9101`.

## Provision / deprovision a tenant

Use the Provider Driver CLI (`../tenancy/provision-tenant`) from the runtime dir:

```bash
./src/deploy/tenancy/provision-tenant provision demo-salon --company "Aphrodite Beauty & Spa"
./src/deploy/tenancy/provision-tenant seed demo-salon ./src/deploy/tenancy/seed/demo-salon.sql
./src/deploy/tenancy/provision-tenant serving-fragment demo-salon   # emits the nginx /booking/ block
./src/deploy/tenancy/provision-tenant health demo-salon             # prints the HTTP status
./src/deploy/tenancy/provision-tenant list
```

`provision` is idempotent: re-running with an existing DB + schema is a no-op.

## Teardown (no platform impact)

```bash
./src/deploy/tenancy/provision-tenant deprovision demo-salon      # drop DB + user + registry + storage
rm -f /etc/nginx/sites-enabled/booking-demo-demo-salon.conf \
      /etc/nginx/sites-available/booking-demo-demo-salon.conf
nginx -t && systemctl reload nginx
docker compose -f /opt/booking-runtime/docker-compose.yml down -v  # removes the stack + db-data
```
