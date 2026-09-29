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

## Country reference API

`php artisan migrate --seed` creates the `countries` table and automatically
seeds all 249 ISO 3166-1 countries and territories from the checked-in
[country dataset](Modules/Geography/database/data/countries.README.md).
The seed is safe to repeat and needs no network access. To seed countries alone
after migration:

```bash
php artisan db:seed --class='Modules\Geography\Infrastructure\Database\Seeders\CountrySeeder'
```

Both endpoints use the same Bearer authentication and password-change policy as
the existing province and city reference endpoints:

- `GET /api/v1/reference/countries` lists active countries, ordered by English
  name. It returns the full seeded list by default (`per_page=250`) and supports
  `page`, `per_page` (1–250), `active` (`0` or `1`), and `search` (Persian/English
  name or alpha-2, alpha-3, or numeric code).
- `GET /api/v1/reference/countries/{countryId}` returns one active country by
  its numeric database ID, or `404` for an unknown or inactive country.

Country fields are `country_id` (a decimal string), `country_code`,
`alpha3_code`, `numeric_code` (three characters, including leading zeros),
`name_fa`, `name_en`, and `is_active`. Responses use the standard `data`/`meta`
envelope; list responses include `meta.pagination`.

## Customer API

`POST /api/v1/customers` creates a customer and its initial default address.
`GET /api/v1/customers` lists the tenant's customers with pagination and field filters.
See the [Customer API contract](Modules/Customer/README.md) for inputs,
authentication, migration, and examples.

## Validation

Module layer roots (`Application`, `Domain`, `Infrastructure`, `Presentation`)
contain responsibility folders only; PHP files must not sit directly in a layer
root. The same rule applies to layers under `app/`. Place services in `Services`,
policies in `Policies`, validators in `Validators`, value objects in `ValueObjects`,
enums in `Enums`, exceptions in `Exceptions`, and adapters in `Adapters`.
Use the matching PSR-4 namespace and update imports when moving a class.
`ArchitectureBoundaryTest` enforces this directory boundary.

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
