# Fresh-install database identity

Every application table uses an unsigned integer, auto-incrementing primary key named `id`. Entity tables no longer store a second UUID identifier. For example, `crm_customer_address` declares `$table->increments('id')` and has no `customer_address_id` column.

## Relationships and application contracts

Foreign keys store the numeric parent ID. Ordinary references target `id`; tenant-scoped composite references target `(hq_id, id)` and retain their supporting unique indexes. Existing constraints, checks, and workflow triggers remain in place.

Models use Eloquent's default incrementing integer key. Inserts and clones receive their identity from the database. Creation repositories return that generated ID; bulk writers obtain generated IDs before creating dependent rows. Seeders find existing rows by their business keys so repeated seeding preserves IDs and edits.

Some application DTOs and JSON documents retain descriptive field names such as `customer_id`, `area_id`, and `parcel_id`. For a record's own identity these names expose the decimal value of `id`; they do not represent another stored key. `HasNumericIdentity`, `RecordSchema`, and `RecordQueryBuilder` provide these aliases without identity lookups or a translation cache. Raw SQL uses physical column names.

Resource route parameters, request validation, and `X-Node-Id` accept numeric identifiers. Validated JSON numbers are normalized to decimal strings where existing application contracts require strings. Model serialization includes the integer `id`; explicit response DTOs retain their documented field names.

## Tokens

Correlation IDs, session token families, claim tokens, cached quote/option tokens, and matrix document tokens are opaque strings. New tokens use random bytes, rather than UUID generation. They are not substitutes for a persisted record's primary key. Failed queue jobs use Laravel's numeric database provider.

## Verification

`tests/Feature/NumericRecordIdentityTest.php` checks removal of all former secondary identity columns, generated IDs, queries, projections, bulk writes, eager relationships, cloning, repeatable seeding, numeric HTTP inputs, and customer-address tenant constraints. `InternalDatabaseIdentityTest` covers status revisions and lazy catalog locks.

`tests/Integration/NumericForeignKeysTest.php` covers installed MySQL column types, model relationship keys, numeric round trips, polymorphic scope collisions, invalid parents, and rollback behavior. These integration tests require an isolated MySQL/Redis environment.

## Existing installations

These changes update the fresh-install migrations and application code. They do not convert a populated database in place. Existing installations need a separate data conversion before running this version; editing an already-applied create migration does not change that database. No application database was reset or migrated as part of this change.
