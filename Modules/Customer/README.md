# Customer API

## Create a customer

`POST /api/v1/customers` creates a customer, its initial default address, its
default mobile contact point and, when one is selected, its primary industry in
one transaction, returning `201` in the standard `data` / `meta` /
`correlation_id` envelope. The response includes `data.customer_id`,
`data.mobile`, `data.industry_id`, `data.assignee_id` and the saved location
under `data.address`. IDs are decimal strings.

Required headers:

```http
Authorization: Bearer <access-token>
Content-Type: application/json
Idempotency-Key: <unique-key-of-16-to-128-characters>
```

The actor must have completed their password change, belong to a tenant with
the `Customer` entitlement enabled, and hold `customer.create` at `TENANT`
scope. The authorization catalog grants this permission to `hq_admin`.

Example for Iran (`province_id` comes from `GET /api/v1/reference/provinces`
and `city_id` from `GET /api/v1/reference/cities`; replace the example IDs with
an active province and a city belonging to it):

```json
{
  "first_name": "علی",
  "family_name": "احمدی",
  "display_name": "علی احمدی",
  "customer_code": "000123",
  "mobile": "09121234567",
  "industry_id": 1,
  "assignee_id": 1,
  "country_code": "IR",
  "province_id": 1,
  "city_id": 1,
  "postal_code": "1234567890",
  "address_text": "تهران، خیابان ولیعصر، پلاک ۱۰",
  "phase": "LEAD",
  "kind": "PERSON"
}
```

| Input | Validation |
| --- | --- |
| `first_name` | Required string, up to 120 characters |
| `family_name` | Required string, up to 120 characters |
| `display_name` | Required string, up to 200 characters |
| `customer_code` | Optional, nullable string up to 80 characters; unique within the tenant |
| `kind` | Required: `PERSON` or `COMPANY` |
| `phase` | Required: `LEAD` or `CUSTOMER` |
| `mobile` | Required string up to 32 characters; an Iranian number in any local spelling or another country's number in `+`/`00` international spelling |
| `industry_id` | Optional, nullable; an active industry of the tenant from `crm_industries` |
| `assignee_id` | Optional, nullable; an active user of the same tenant |
| `country_code` | Required active ISO alpha-2 code from `GET /api/v1/reference/countries`; normalized to uppercase |
| `province_id` | Required for `IR`; active province containing the selected city |
| `city_id` | Required for `IR`; active city belonging to `province_id` |
| `foreign_city` | Required for countries other than `IR`; string up to 200 characters |
| `postal_code` | Optional, nullable; exactly ten ASCII digits for `IR`, otherwise up to 10 characters |
| `address_text` | Optional, nullable string up to 1000 characters |

For another country, omit `province_id` and `city_id` and supply a city name, for
example `"country_code": "AE", "foreign_city": "دبی"`. Iranian reference
provinces and cities cannot be combined with a foreign country;
`foreign_city` cannot be used for Iran.

Names, customer code, kind, phase, and the assignee are stored in `crm_customers`.
Country, province, city, postal code, and the street address are stored in
`crm_customer_address`, with `purpose=MAIN` and `is_default=true`. For Iran, the
supplied `province_id` must match the selected city's province; a mismatch
returns `422` without creating any record. The postal code and the street address
can remain null at this stage. `data.address` carries the same entry shape the
address book returns, so the plaque, unit and coordinates this endpoint never
writes appear beside the rest as null.

The mobile number becomes one row in `crm_contact_points` with `type=MOBILE`,
`identifier_kind=PHONE`, `is_default=true`, `status=ACTIVE`, and `scope=WORK`
for a `COMPANY` or `PERSONAL` for a `PERSON`. `value` keeps the number exactly
as typed, with surrounding and repeated spaces collapsed; `normalized_value`
keeps the canonical `+<country><subscriber>` form, so `09121234567`,
`0912-123-4567`, `۰۹۱۲۱۲۳۴۵۶۷`, and `+98 912 123 4567` all normalize to
`+989121234567`. The number is not unique: several records may share it.

A selected industry becomes one row in `crm_customer_industry` with
`is_primary=true`. Without `industry_id`, no industry row is written.

Every row takes `hq_id` and `created_by` from the authenticated actor. The
initial lifecycle is `ACTIVE`; creating directly in phase `CUSTOMER` also sets
`converted_at` to the creation time.

Customer codes are operator-entered strings: leading zeros are preserved. An
empty code is normalized to null; multiple customers may have no code. Reusing a
non-null code within the same tenant returns `409` and creates no record at all.
Another tenant may use the same code.

Invalid input returns `422` with `field_errors`; unauthenticated requests return
`401`, and denied access returns `403`. Reusing the same idempotency key and
request returns the original customer. Reusing the key with a different request
returns `409`.

## List customers

`GET /api/v1/customers` requires bearer authentication, a completed password
change, the `Customer` entitlement, and `customer.view` at `TENANT` scope.
The authorization catalog grants `customer.view` to `hq_admin`.

Rows include `customer_id`, `kind`, `phase`, `lifecycle`, `display_name`,
`customer_code`, `assignee`, and `updated_at`. IDs and customer codes are strings.
Timestamps are ISO 8601 in UTC; nullable values stay null. Results always belong
to the actor's tenant and are ordered by `updated_at DESC, id DESC`.

`assignee` is null for an unassigned record and otherwise carries the owner's
`user_id` and `display_name`, read from `users` in one query for the whole page:

```json
{
  "customer_id": "11",
  "kind": "COMPANY",
  "phase": "CUSTOMER",
  "lifecycle": "ACTIVE",
  "display_name": "پارس‌گستر آریا",
  "customer_code": "C-1001",
  "assignee": { "user_id": "12", "display_name": "سارا احمدی" },
  "updated_at": "2026-09-28T12:00:00.000000Z"
}
```

The list carries no contact number. The mobile number now lives in
`crm_contact_points` (see the create endpoint above), so a phone column in the
list rows is a deliberate omission rather than a missing source.

Query parameters:

| Parameter | Meaning |
| --- | --- |
| `page` | Positive integer; default `1` |
| `per_page` | Integer from `1` to `100`; default `25` |
| `display_name` | Text search within the display name, up to 200 characters |
| `customer_code` | Text search within the customer code, up to 80 characters |
| `phase` | Exact match: `LEAD` or `CUSTOMER` |
| `kind` | Exact match: `PERSON` or `COMPANY` |
| `lifecycle` | Exact match: `ACTIVE`, `INACTIVE`, or `ARCHIVED` |
| `assignee_id` | Exact match on the owner; a positive integer up to 4294967295 |
| `updated_at` | A UTC calendar date in `YYYY-MM-DD` format |
| `updated_at_from` | Inclusive start date in `YYYY-MM-DD` format |
| `updated_at_to` | Inclusive end date in `YYYY-MM-DD` format; cannot precede the start date |

Filters combine with AND and run before pagination. Empty filters are ignored.
Either date-range bound can be omitted. Date filters include the entire UTC day,
including fractional seconds immediately before midnight. Invalid filters and
pagination values return `422` with `field_errors`.

```http
GET /api/v1/customers?page=1&per_page=25&phase=CUSTOMER&kind=COMPANY&customer_code=001&updated_at_from=2026-09-01&updated_at_to=2026-09-28
Authorization: Bearer <access-token>
```

Pagination uses the standard response envelope:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "page": 1,
      "page_size": 25,
      "total": 0,
      "total_pages": 1
    }
  },
  "correlation_id": "<request-correlation-id>"
}
```

## Customer detail

`GET /api/v1/crm/customers/{id}/detail` returns one customer together with the
open work attached to it. It requires bearer authentication, a completed password
change, the `Customer` entitlement, and `customer.view` at `TENANT` scope — the
same access as the list. `{id}` must be numeric; anything else does not match the
route. A customer of another tenant is reported as `404 RESOURCE_NOT_FOUND`,
exactly like one that does not exist.

```json
{
  "customer": {
    "customer_id": "11",
    "kind": "COMPANY",
    "phase": "CUSTOMER",
    "lifecycle": "ACTIVE",
    "display_name": "پارس‌گستر آریا",
    "customer_code": "C-1001",
    "assignee": { "user_id": "12", "display_name": "سارا احمدی" },
    "converted_at": null,
    "open_opportunities_counts": 1,
    "open_tasks_counts": 2
  },
  "default_address": { "city": "تهران" },
  "primary_industry": { "industry_id": "2", "title": "بازرگانی" },
  "open_tasks": [
    { "task_id": "1", "title": "تماس اول", "status": "OPEN", "due_at": null }
  ],
  "opportunities": [
    {
      "opportunity_id": "3",
      "title": "قرارداد سالانه",
      "step": { "title": "مذاکره", "outcome_type": "OPEN" },
      "amount": 180000000
    }
  ],
  "missing": ["postal_code", "address_text"]
}
```

`assignee`, `default_address`, and `primary_industry` are null when the record
carries none; `open_tasks` and `opportunities` are then empty arrays. Only the
default address and the single primary industry are read, never the other rows of
either table. `default_address.city` is the reference city name for Iran and the
typed foreign city name for any other country. `amount` is an integer in the
stored currency unit and may be null.

`missing` names the core profile data this record still lacks, always in this
order: `customer_code`, `display_name`, `first_name`, `family_name`, `mobile`,
`assignee`, `primary_industry`, `default_address`, `postal_code`, `address_text`.
A value counts as missing when it is null or blank. A record with no address at
all reports `default_address` on its own rather than its three keys together,
because the operator adds an address once instead of a postal code that belongs
to nothing. A complete record returns an empty array.

A task counts as open until it is `COMPLETED` or `CANCELLED`, so `OPEN`,
`IN_PROGRESS`, and `WAITING_CUSTOMER` all appear. An opportunity counts as open
while its current funnel step carries `outcome_type=OPEN`; won and lost ones
appear in neither the list nor the count. `open_tasks_counts` and
`open_opportunities_counts` always equal the length of the matching array — both
lists are complete rather than truncated, so a badge never disagrees with the
rows beneath it. Tasks are ordered by `due_at` and opportunities by
`expected_close`, soonest first, with undated rows last.

The reads cross module boundaries through the owning module's repository
interface: `CrmTask`'s `TaskRepositoryInterface`, `CrmOpportunitie`'s
`OpportunityRepositoryInterface`, and `CrmCatalog`'s industry records. Both CRM
modules gained the minimal read layer and service provider this needs.

`Application/Services/CustomerProfileGaps` owns the one rule behind `missing`,
so the completeness definition sits next to the module's other rules rather than
inside the payload.

Each block of the payload has one resource that owns its shape:
`CustomerIdentityResource` names the customer and is shared with the list rows,
`CustomerAssigneeResource` the owner, `CustomerIndustryResource` the industry, and
`CustomerTaskResource` and `CustomerOpportunityResource` one row each.
`CustomerDetailResource` only assembles them, so a field is renamed in one file
and the list and the detail view cannot drift apart.

## Customer address book

Four endpoints own the addresses of one customer. All of them require bearer
authentication, a completed password change, and the `Customer` entitlement.
Reading needs `customer.view` at `TENANT` scope; writing needs `customer.edit`
at `TENANT` scope. Both IDs in the path must be numeric; anything else does not
match the route. A customer of another tenant, and an address of another
customer, are both reported as `404 RESOURCE_NOT_FOUND`, exactly like one that
does not exist.

| Operation | Route |
| --- | --- |
| List the whole book | `GET /api/v1/crm/customers/{customerId}/addresses` |
| Read one entry | `GET /api/v1/crm/customers/{customerId}/addresses/{addressId}` |
| Add an entry | `POST /api/v1/crm/customers/{customerId}/addresses` |
| Change an entry | `PATCH /api/v1/crm/customers/{customerId}/addresses/{addressId}` |

Every entry is one row of `crm_customer_address` and is returned in the same
shape by all four:

```json
{
  "customer_address_id": "1",
  "country_code": "IR",
  "country": { "country_id": "108", "name": "ایران" },
  "purpose": "نشانی اصلی",
  "province_id": "1",
  "city_id": "1",
  "province": { "province_id": "1", "name": "تهران" },
  "city": { "city_id": "1", "name": "تهران" },
  "foreign_region": null,
  "foreign_city": null,
  "address_text": "تهران، نشانی نمایشی",
  "postal_code": "1234567890",
  "plaque": "12",
  "unit": "3",
  "latitude": "35.7000000",
  "longitude": "51.4000000",
  "is_default": true,
  "created_at": "2026-09-29T10:00:00.000000Z",
  "updated_at": "2026-09-29T10:00:00.000000Z"
}
```

`country`, `province` and `city` are the reference names beside the stored code
and the two IDs; they appear only where the reference rows were read, which is
every address endpoint. The create-customer response echoes the same entry
without them, because it names no place it did not just look up. The address
stores the ISO code rather than a second foreign key, so the country row is
reached through `countries.country_code`. Coordinates are decimal strings at
the stored precision of seven places, so the pin the operator dropped is never
rounded.

The list returns the book whole rather than by the page: a customer owns a
handful of addresses, and the form shows all of them. The default entry comes
first, then the rest in the order they were added.

### Writing an entry

| Input | Validation |
| --- | --- |
| `country_code` | Required active ISO alpha-2 code from `GET /api/v1/reference/countries`; normalized to uppercase |
| `purpose` | Required free text up to 60 characters, for example `نشانی اصلی`; the column carries no fixed vocabulary |
| `address_text` | Required string up to 1000 characters |
| `province_id` | Optional; only for `IR`; an active province, and the one the selected city belongs to |
| `city_id` | Optional; only for `IR`; an active city, and `province_id` must accompany it |
| `foreign_region` | Optional; only for countries other than `IR`; up to 200 characters |
| `foreign_city` | Optional; only for countries other than `IR`; up to 200 characters |
| `postal_code` | Optional; exactly ten ASCII digits for `IR`, otherwise up to 10 characters |
| `plaque`, `unit` | Optional text up to 40 characters; text, so a leading zero or a letter survives |
| `latitude`, `longitude` | Optional; between -90/90 and -180/180, and required together |
| `is_default` | Optional boolean; see below |

Unlike the address captured while a customer is created, the address book asks
only for the country, the purpose and the written address. An Iranian entry may
name no province and no city at all, because the columns behind them are
nullable and a lead is often recorded before the operator knows the city. What
is named still has to be real, and reference geography stays out of a foreign
entry exactly as a foreign region or city stays out of an Iranian one.

`POST` returns `201` with the stored entry. It takes no `Idempotency-Key`: like
the other sub-resource writes of the API, and unlike the create-customer
endpoint, it is not idempotency-guarded.

`PATCH` carries only the fields the operator changed. `country_code`, `purpose`
and `address_text` cannot be cleared, so omitting one leaves it alone; every
other field accepts an explicit `null` to clear it. Latitude and longitude move
as a pair: sending one without the other is refused, and sending both as null
clears the pin. A change that names no field at all returns `422`.

The change is judged as the whole address it leaves behind, not as the fields it
carries. Moving an Iranian entry abroad with `{"country_code": "AE"}` alone is
refused, because the reference city it would leave behind cannot sit outside
Iran; the same request also clearing `province_id` and `city_id` is accepted.

### The default entry

A customer with addresses always has exactly one default, so the rest of the
system always has somewhere to point. The first entry added to an empty book
becomes the default whatever `is_default` said. A later entry with
`is_default: true` takes the flag, and the entry that held it loses it in the
same transaction. Clearing the flag on the current default is refused with
`422`: the operator makes another entry the default instead.

Invalid input returns `422` with `field_errors`, unauthenticated requests
return `401`, and denied access returns `403`.

### Implementation

`Application/Validators/CustomerAddressValidator` owns both address rule sets
behind `CustomerAddressValidatorInterface`: `validate` for the location a
customer draft carries, and `validateEntry` for one book entry. The two share
the country, city and province checks, so a reference rule is written once.

`Application/Dto/CustomerAddressDraftDto` is the whole entry, with `fromRecord`
to read a stored one and `toAttributes` to write it back, and
`Application/Dto/CustomerAddressChangesDto` is a `PATCH` of it, with `applyTo`
producing the address the change leaves behind. The handlers therefore validate
and store one complete address rather than assembling a partial column list.

Run the address tests with:

```bash
vendor/bin/phpunit tests/Unit/CustomerAddressEntryTest.php tests/Feature/CustomerAddressBookTest.php
```

`CustomerAddressEntryTest` covers the merge semantics and the entry rules with
no database at all; `CustomerAddressBookTest` drives the four endpoints.

## Customer registration details

`GET /api/v1/crm/customers/{id}/extended-details` reads the registration identity
of a customer and `PUT` on the same path writes it. Both need bearer
authentication, a completed password change and the `Customer` entitlement; the
read needs `customer.view` and the write `customer.edit`. `{id}` must be numeric.
A customer of another tenant is reported as `404 RESOURCE_NOT_FOUND`, exactly
like one that does not exist.

```json
{
  "customer_id": "11",
  "trade_name": "پارس‌گستر",
  "legal_form": "سهامی خاص",
  "legal_name": "شرکت پارس‌گستر آریا",
  "registration_no": "123456",
  "registration_date": 1790640000,
  "registration_place": "تهران",
  "need_summary": "نیاز به حمل یخچالی",
  "updated_at": "2026-09-29T12:00:00.000000Z"
}
```

| Input | Validation |
| --- | --- |
| `trade_name` | Optional, nullable string up to 200 characters |
| `legal_form` | Optional, nullable string up to 120 characters; free text, for example `سهامی خاص` |
| `legal_name` | Optional, nullable string up to 200 characters |
| `registration_no` | Optional, nullable string up to 80 characters; the registration number, which is not the national ID |
| `registration_date` | Optional, nullable integer; a unix timestamp in seconds between `-2208988800` (1900) and `4102444800` (2100) |
| `registration_place` | Optional, nullable string up to 200 characters |
| `need_summary` | Optional, nullable string up to 2000 characters |

The write is a `PUT` and replaces the whole set: **a field left out of the body is
cleared**, so the form always sends every field it owns. The response of the write
is the same payload the read serves, so the form can rebind from it.

`registration_date` travels as a numeric unix timestamp in both directions. The
column stores a calendar day, so only the date part survives: a timestamp at
midday comes back as midnight UTC of that same day. The read answers `null` while
the date was never set.

All seven values live in one `crm_customer_extended_details` row, unique per
customer. The row is created by the first write and updated afterwards in a single
statement, so two writers racing on that first save cannot both insert. Everything
else on that row — salutation, birth date and the qualification columns — belongs
to other concerns and is never touched by this endpoint. A customer whose row does
not exist yet reads back every field as `null`, with `updated_at` null too, rather
than a `404`: the form has somewhere to load from either way.

## CRM module conventions

The CRM modules — `Customer`, `CrmFinance`, `CrmCatalog`, `CrmTask` and
`CrmOpportunitie` — follow three rules, so a reader who knows one use case knows
them all. The rest of the codebase already follows most of them; these three are
where the CRM code had drifted.

**Every use case returns its own `<UseCase>Result`.** No handler returns a bare
Eloquent record, a `Collection`, or a paginator any more, and none returns a DTO
shared with another use case. The result class is a `final readonly` class in the
use-case folder beside its command, and it holds whatever the handler produced,
Eloquent records included — the same shape `Iam`, `Consignment`, `Outbox` and
`Dashboard` already use. A list use case is no exception:
`ListCustomersResult` carries the paginator, and the controller hands
`$result->customers` to `ApiResponder::paginated`. Two composite DTOs,
`CustomerDetailDto` and `CustomerOrgStructureDto`, became
`GetCustomerDetailResult` and `GetCustomerOrgStructureResult`; nothing else held
them, so no DTO is shared between a use case and its caller.

**One repository per table.** `CustomerRepositoryInterface` had grown to 36
methods across eight tables; it is now eight contracts, one per table, and
`FinanceRepositoryInterface` is four. Because a repository is scoped to a single
record, its methods drop the record name: `createAddress` is
`CustomerAddressRepositoryInterface::create`, `findEntryForTenant` is
`FinancialEntryRepositoryInterface::findForTenant`. A use case now injects only
the tables it touches — `UpdateCustomerAddressHandler` takes one repository where
it used to take the whole customer aggregate. The bindings live in one
`REPOSITORIES` map per service provider.

**A dependency is named after what it is.** A repository parameter is
`<record>Repository`, never a bare plural: `$customerRepository`, not
`$customers`; `$cityRepository`, not `$cities`. This was already the rule
everywhere else (`userRepository` in 22 places, `catalogRepository` in 21); only
CRM used the plural form. Guards, writers and readers already followed it and
are unchanged (`$accessGuard`, `$auditWriter`).

## Setup and implementation

Apply the nullable-assignee migration and refresh the authorization catalog in
the configured backend environment:

```bash
php artisan migrate
php artisan db:seed --class='Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder'
```

The migration preserves existing assignments and the foreign key. Reverting it
requires assigning any rows whose `assignee_id` is null first.

Address validation lives in `Application/Validators/CustomerAddressValidator`
and accepts `CustomerAddressDto` through `CustomerAddressValidatorInterface`.
The mobile number, the industry, and the assignee are validated by
`Application/Validators/CustomerDraftValidator` through
`CustomerDraftValidatorInterface`, which returns the accepted
`Domain/ValueObjects/MobileNumber`; the industry is read through
`CrmCatalog`'s `IndustryRepositoryInterface` and the assignee through `Iam`'s
`UserRepositoryInterface`. `CreateCustomerHandler` invokes both validators
inside the transaction before writing any record, including when called directly
from a job or command. The HTTP input remains flat; `CustomerCommandMapper`
assembles the address DTO.

`Application/Services/CustomerAccessGuard` checks tenant identity, the module
entitlement, permission, and tenant scope through `CustomerAccessGuardInterface`.
The handler calls `assertCanCreate` before opening the transaction and uses the
returned tenant ID for both records. Direct job or command calls use the same guard.

Run the feature tests, including direct use-case validation, with:

```bash
vendor/bin/phpunit tests/Feature/CreateCustomerTest.php tests/Feature/ListCustomersTest.php
```
