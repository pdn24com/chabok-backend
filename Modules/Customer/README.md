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

## Customer contact points

`GET /api/v1/crm/customers/{id}/contact-points` returns the whole channel set of
a person, default entries first, then by `priority` (unset last) and ID. `PUT`
on the same path replaces the set. Both need bearer authentication, a completed
password change and the `Customer` entitlement; the read needs `customer.view`
and the write `customer.edit`. `{id}` must be numeric. Only a PERSON has
contact points: a `PUT` for a company answers `422` on `customer_id`. A customer
of another tenant is reported as `404 RESOURCE_NOT_FOUND`, exactly like one that
does not exist.

```json
{
  "items": [
    {
      "id": 8,
      "type": "MOBILE",
      "identifier_kind": "PHONE",
      "value": "0912 000 0123",
      "scope": "PERSONAL",
      "is_default": true,
      "status": "ACTIVE",
      "priority": 1,
      "relationship_id": null,
      "address_id": null,
      "verified_manually": false
    }
  ]
}
```

The response is `{ "items": [ ... ] }` with `contact_point_id`, `type`,
`identifier_kind`, `value`, `normalized_value`, `scope`, `is_default`, `status`,
`priority`, `subtype`, `work_context`, `relationship_id`, `address_id` and
`verified_manually_at` on every item.

**Replace semantics.** The body is the complete set the person should hold
afterwards, at most 50 items; `items` must be present, and an empty list removes
every channel. An item with an `id` updates that channel (the ID must be one of
this person's own), an item without one creates a channel, and **every existing
channel left out of the list is deleted** (nothing references a contact point).
`verified_manually: true` stamps `verified_manually_at` once and keeps the first
time on later saves; `false` clears it. The whole write runs in one transaction
that locks the person's row first, so two replacements run one after the other.

| Input | Validation |
| --- | --- |
| `type` | `MOBILE`, `PHONE`, `INSTAGRAM`, `WHATSAPP`, `TELEGRAM`, `BALE`, `EMAIL` or `ADDRESS_REFERENCE` |
| `identifier_kind` | Must be the kind the type carries: `PHONE` for MOBILE/PHONE/WHATSAPP, `USERNAME` for INSTAGRAM/TELEGRAM/BALE, `EMAIL` for EMAIL, `ADDRESS` for ADDRESS_REFERENCE |
| `value` | Required string up to 320 characters, kept as typed |
| `scope` | `PERSONAL` or `WORK` |
| `is_default`, `status` | Optional; `status` is `ACTIVE` (default) or another `ContactPointStatus` |
| `priority` | Optional, nullable integer |
| `relationship_id` | Optional; must be a relationship of the same person, otherwise `422` |
| `address_id` | Required for `ADDRESS_REFERENCE`, and must be an address of the same person; optional otherwise but checked the same way |

**Normalisation.** `normalized_value` is never accepted from the client; the
server derives it. MOBILE, PHONE and WHATSAPP go through the `MobileNumber` value
object (Persian and Arabic digits, spaces, dashes and a leading zero are
accepted; the result is `+98...` for Iranian numbers); an email is lower-cased
and must be a valid address; INSTAGRAM, TELEGRAM and BALE handles lose a leading
`@` and are lower-cased (`[a-z0-9._]`, 2 to 64 characters). An ADDRESS_REFERENCE
stores the address ID. A value that does not fit answers `422` on
`items.N.value`.

**Default rule.** At most one *active* channel per type and scope can be the
default; a second one in the same list is `422` on `items.N.is_default`, and an
inactive channel cannot be the default. The same channel twice in the list
(same type and normalised value; for a mobile number whatever the scope) is
`422 customer.contact_point_is_duplicated`.

**One mobile, one person.** An active MOBILE channel whose number is already an
active MOBILE channel of *another* customer of the tenant refuses the write with
`409 MOBILE_OWNED_BY_OTHER_PERSON` naming `items.N.value`; the rows are read for
update, so a concurrent writer waits instead of slipping the number in. The
rule guards MOBILE only. `POST /api/v1/customers` (CreateCustomer) does **not**
apply it: it records the number as given and lets the duplicate check
(below) warn the operator instead, so two records created that way may share a
number; the first later write that touches either of them through this
endpoint is what raises the `409`.

## Customer industries

`GET /api/v1/crm/customers/{id}/industries` returns `{ "items": [ { "industry_id",
"title", "is_primary" } ] }`, the primary industry first. `PUT` replaces the set.
The read needs `customer.view`, the write `customer.edit`; a customer of another
tenant is `404`.

```json
{ "items": [ { "industry_id": 2, "is_primary": true }, { "industry_id": 5, "is_primary": false } ] }
```

The body is the complete set, and `items` is required (an empty list clears every
industry). An industry left out is removed, a new one is added. At most one item
can be primary (none is allowed, which clears the flag), an industry cannot be
listed twice, and every industry must be an *active* industry of the tenant, each
refusal being a `422` naming `items.N.industry_id` or `items.N.is_primary`. The
primary flag is moved by the same manager `PATCH .../profile` uses for
`primary_industry_id`, so the two entry points always agree: after a `PUT` the
profile's `primary_industry_id` is the item flagged primary. The write runs in one
transaction that locks the customer first.

## Duplicate check

`GET /api/v1/crm/customers/duplicates?mobile=&email=` answers which customers
already hold a mobile number or an email address, so the lead form can warn before
it saves. It needs `customer.view`. At least one of the two is required (an empty
query parameter counts as absent), otherwise `422`; a `mobile` that is not a
usable number is `422` too. The mobile goes through `MobileNumber`, so
`0912 000 0123`, `+989120000123` and Persian digits find the same records; the
email is compared lower-cased. Only *active* contact points count, only customers
of the caller's tenant are returned, and there are at most 20 of them, newest
first.

```json
{ "items": [ { "customer_id": "12", "display_name": "نیما نمونه", "phase": "CUSTOMER", "matched_on": "MOBILE" } ] }
```

`matched_on` is `MOBILE` or `EMAIL`; a customer found by both is reported as
`MOBILE`, because the number is the identity the one-person-per-mobile rule
protects and the email is only a hint. The route is declared before every
`{customerId}` route, so `duplicates` is never read as an ID.

## Customer relationships

A relationship says that a person holds a role at a company. Both ends are
`crm_customers` rows: the person side must be a PERSON and the company side a
COMPANY. Per the repo convention, the routes hang off `/customers/{customerId}`
where the prototype uses `/companies/{id}`.

| Method and path | Permission |
| --- | --- |
| `GET /api/v1/crm/customers/{customerId}/relationships?active=true` | `customer.view` |
| `POST /api/v1/crm/customers/{customerId}/relationships` | `customer.edit` |
| `POST /api/v1/crm/relationships/{relationshipId}/end` | `customer.edit` |

**List.** `{customerId}` may be a person or a company; the answer holds every
relationship in which it is either end, primary first, then newest first. With
`active=true` only the relationships running today are returned (no end date or
one not before today, and no start date or one not after today); without it, or
with `active=false`, everything is returned, ended ones included. The names of
both ends and the post titles are read in one query each, never per row.

```json
{
  "items": [
    {
      "relationship_id": "21",
      "person": { "customer_id": "3", "display_name": "آرمان نمونه" },
      "company": { "customer_id": "11", "display_name": "پارس‌گستر آریا" },
      "position": { "position_id": "5", "title": "مدیر خرید" },
      "role_title": "مدیر خرید",
      "decision_level": "مدیریت ارشد",
      "signing_authority": "تا سقف قرارداد",
      "valid_from": "2026-01-01",
      "valid_to": null,
      "is_primary": true
    }
  ]
}
```

`position` is `null` when no post was chosen; dates are `Y-m-d` and stay `null`
while unknown.

**Create.** `{customerId}` must be a COMPANY (`422` on `customer_id` otherwise; an
unknown or foreign company is `404`). The success answer is `201` with the item
above. The route is idempotent (`Idempotency-Key`, scope
`customer.relationship.create`), because a new person and their number are
created with it.

| Input | Validation |
| --- | --- |
| `person_customer_id` | Integer; exactly one of this and `new_person` must be sent, otherwise `422`. Must be a PERSON of the tenant |
| `new_person` | `{ first_name (<=120), family_name (<=120), mobile (<=32) }`; all three required |
| `position_id` | Optional; must be a post of *this* company's org chart (through its department), otherwise `422` |
| `role_title` | Required string up to 200 characters |
| `decision_level` | Optional, up to 80 characters |
| `signing_authority` | Optional, up to 200 characters |
| `valid_from`, `valid_to` | Optional `Y-m-d`; `valid_to` before `valid_from` is `422` on `valid_to` (`customer.valid_to_is_before_valid_from`) |
| `is_primary`, `replace_primary` | Optional booleans |

With `new_person`, one transaction creates the PERSON customer (phase of the
company, lifecycle `ACTIVE`, display name `"first family"`, the company's assignee
as owner, the caller as `created_by`), its default MOBILE contact point (scope
`WORK`, normalised with `MobileNumber`, linked to the new relationship through
`relationship_id`) and the relationship. If any step fails, none of it is kept.
When the number is already an active MOBILE channel of another customer the
request is refused with `409 MOBILE_OWNED_BY_OTHER_PERSON` on `new_person.mobile`,
the same rule and the same error as the contact-points `PUT` (both call
`CustomerContactPointValidator::assertMobileIsFree`). An unusable number is `422`.

Conflicts: a person who already holds a relationship with the company that has
not ended (no end date, or one not before today) gets
`409 RELATIONSHIP_ALREADY_EXISTS`. The primary rule is "at most one active
primary per company" (the database holds the slot for an open-ended primary; the
application also honours a future end date): with `is_primary` and an existing
active primary, `replace_primary: true` demotes the old one (`is_primary=false`)
in the same transaction, otherwise the answer is `409 PRIMARY_RELATIONSHIP_EXISTS`.
An already ended relationship cannot be created as primary (`422` on `is_primary`).
The company row is locked first, so two concurrent requests cannot both take the
slot.

**End.** `valid_to` (`Y-m-d`) is required; the server never defaults it to today.
The relationship gets that end date and always gives up the primary flag, which
releases the company's primary slot. `valid_to` before the relationship's
`valid_from` is `422`. A relationship counts as *already ended* when its end date
lies before today; ending it again is `409 RELATIONSHIP_ALREADY_ENDED`. One whose
end date is today or later still runs, so its end date may be set again. An
unknown or foreign relationship is `404`. The answer is the updated item.

## Converting a lead

`POST /api/v1/crm/customers/{id}/convert` turns a LEAD into a CUSTOMER. The
identity does not change; only `phase`, the name fields and the code that the
form completed do. It needs `customer.edit`; a customer of another tenant is
`404`.

| Input | Meaning |
| --- | --- |
| `first_name`, `family_name` | Optional; when sent they replace the stored names |
| `display_name` | Optional; when omitted it is rebuilt as `"first family"` only if a name part actually changed, otherwise kept |
| `customer_code` | Optional, up to 80 characters; sent empty or omitted keeps the stored code |
| `merge_into_customer_id`, `confirm_merge` | Product decision O12 is open: a non-null id or `confirm_merge: true` answers `422` (`customer.merging_into_an_existing_customer_is_not_available_yet`) and writes nothing |

The record must be a LEAD (a record that is already a customer is
`409 CUSTOMER_ALREADY_CONVERTED`) and its lifecycle must be `ACTIVE` (an inactive
or archived lead is `422` on `customer_id`; reopen it first). After the merge of
the sent and stored values a PERSON must have `first_name`, `family_name` and a
`display_name`, a COMPANY a `display_name`; what is missing is `422` per field.
A code another customer already uses is `409 CUSTOMER_CODE_EXISTS` (checked before
the write; the unique key remains the last line of defence). `converted_at` is set
to now, but, like the profile writer, a date of an earlier promotion is kept.

```json
{ "customer_id": "501", "phase": "CUSTOMER", "converted_at": "2026-09-29T09:00:00.000000Z", "customer_code": "C-1042", "merged_into": null }
```

## Lifecycle change and closing a lead

`POST /api/v1/crm/customers/{id}/lifecycle` moves a customer to `ACTIVE`,
`INACTIVE` or `ARCHIVED`. The lead-close button ("close without result") makes
exactly this call with `INACTIVE`; the endpoint itself is generic and accepts a
record of any phase. It needs `customer.edit`.

| Input | Validation |
| --- | --- |
| `lifecycle` | Required; one of the `CustomerLifecycle` values |
| `reason` | Required (up to 1000 characters) when the target is `INACTIVE` or `ARCHIVED`; optional when reopening with `ACTIVE` |

A lifecycle equal to the current one is `422` on `lifecycle`. In one transaction
the customer row is locked, `lifecycle` and `updated_at` are written, and the
reason is recorded as a `NOTE` in `crm_activities` (`customer_id` set, `body` =
the trimmed reason, `occurred_at` now, the caller as `created_by`) through CrmTask's
`ActivityRepositoryInterface`. The answer is
`{ "customer_id": "501", "lifecycle": "INACTIVE", "activity_id": "3301" }`;
`activity_id` is `null` when a reopen carried no reason. `PATCH .../profile` can
still change the lifecycle without a reason; it is left as it was.

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
