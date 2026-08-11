# Chabok platform backend

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
