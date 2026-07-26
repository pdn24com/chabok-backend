# Chabok backend workspace

S0-01 establishes the Laravel 13/PHP 8.3 modular-monolith toolchain and the non-production `ArchitectureProof` module required by ADR-008.

The workspace intentionally contains no IAM implementation, production migration, seed, controller, repository, authentication flow, or product endpoint.

## Toolchain proof

Run from the repository root:

```text
docker compose build backend
docker compose run --rm --no-deps backend composer install
docker compose run --rm --no-deps backend composer dump-autoload --optimize
docker compose run --rm --no-deps backend php artisan module:list
docker compose run --rm --no-deps backend php artisan test Modules/ArchitectureProof/tests/Unit/ArchitectureProofTest.php
```

The root Composer manifest loads `Modules/*/composer.json` through Composer Merge Plugin. It deliberately has no root `Modules\\` PSR-4 mapping.

## Local Branch Panel login

Production seeds intentionally create no users or reusable credentials. For a
local login, run the local-only command below and enter a password at its hidden
prompt:

```text
docker compose exec backend php artisan chabok:local-user
```

The default identifier is `branch.manager.local`. The command creates or
updates an active local HQ, branch, Branch Manager assignment, module
entitlements, and password credential. It is blocked outside `local` and
`testing` environments, is safe to rerun, and never writes the password into
source control.
