# Chabok platform backend

## Stage PHP-FPM runtime

`Dockerfile.fpm` is the Stage runtime image (PHP-FPM plus the existing CLI and
extensions, with OPcache). The development `Dockerfile` is unchanged. Build it
through the infrastructure repository's `backend-php` service. HTTP traffic is
handled by the `backend` Nginx gateway; FPM port 9000 must never be host-published.
Composer and Artisan Stage commands now target `backend-php`.

Requests run as `www-data`; verify write access to `storage` and `bootstrap/cache`
before cutover. No source-wide chmod/chown is performed by the image. Initial
`PHP_FPM_MAX_CHILDREN=4` and `PHP_FPM_MAX_REQUESTS=500` are configurable, not a
capacity guarantee. OPcache timestamp validation remains enabled for the current
bind-mounted Stage layout. See sibling infrastructure `STAGE-FPM.md` for the
scoped cutover, smoke test and rollback procedure. No migrations are introduced.

Laravel modular-monolith backend for the Chabok logistics platform.

Implemented modules include Foundation/IAM, Consignment, Manifest, Dashboard,
Service Catalog and Pricing. The application uses MySQL, Redis, transactional
outbox processing, tenant-scoped authorization and versioned `/api/v1` routes.

## Local setup with Docker

Clone `chabok-platform-infrastructure` beside this repository, then run from
the infrastructure repository:

```powershell
docker compose build backend
docker compose run --rm --no-deps backend composer install
docker compose up -d backend
docker compose exec backend php artisan migrate --seed
```

Do not commit `.env`, application keys, JWT secrets, database credentials or
legacy-provider credentials. Use `.env.example` only as a variable reference.

## Validation

Inside the backend container:

```powershell
php artisan test
./vendor/bin/phpunit --configuration=phpunit.integration.xml
php artisan route:list --path=api/v1
```

The integration suite requires the isolated MySQL and Redis services from the
infrastructure repository's `testing` profile.

## Local Branch Panel account

Production seeds create no reusable users. For local inspection only:

```powershell
docker compose exec backend php artisan chabok:local-user
```

The command is blocked outside local/testing environments and accepts the
password through a hidden prompt.
