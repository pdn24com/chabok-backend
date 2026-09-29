# CRM catalog API

What the tenant sells, and the reference data the offer is filed under: the catalog
items (the "شناسنامه" of a good or a service), their categories, buyer personas and
sales models, and the shared industry knowledge base.

Every endpoint needs bearer authentication, a completed password change and the
`Customer` entitlement. Reading needs `crm.catalog.view` and every write needs
`crm.catalog.manage`, both at `TENANT` scope; the authorization catalog grants them
to `hq_admin`. The industry list keeps its own permission, `crm.industry.view`. All
`{id}` segments must be numeric. An item of another tenant is reported as
`404 RESOURCE_NOT_FOUND`, exactly like one that does not exist.

| Method | Path | Permission |
| --- | --- | --- |
| `GET` | `/api/v1/crm/catalog-items?kind=&status=&category_id=&q=` | `crm.catalog.view` |
| `GET` | `/api/v1/crm/catalog-items/{id}` | `crm.catalog.view` |
| `POST` | `/api/v1/crm/catalog-items` | `crm.catalog.manage` |
| `PATCH` | `/api/v1/crm/catalog-items/{id}` | `crm.catalog.manage` |
| `GET` | `/api/v1/crm/catalog/categories?active=1` | `crm.catalog.view` |
| `GET` | `/api/v1/crm/catalog/personas?active=1` | `crm.catalog.view` |
| `GET` | `/api/v1/crm/catalog/sales-models?active=1` | `crm.catalog.view` |
| `GET` | `/api/v1/crm/industries` | `crm.industry.view` |

## The table

`GET /catalog-items` answers the whole filtered set as a plain list in `data`, with
no paging, ordered by `code` and then id. `kind` is `GOOD` or `SERVICE`, `status` is
`ACTIVE`, `INACTIVE` or `ARCHIVED`, and `q` matches the code or the title (LIKE, with
`%` and `_` escaped). Each row:

```json
{
  "catalog_item_id": "1",
  "code": "SRV-001",
  "title": "…",
  "kind": "SERVICE",
  "status": "ACTIVE",
  "category": { "catalog_category_id": "1", "title": "…" },
  "industries": [{ "industry_id": "3", "title": "…" }]
}
```

`category` is `null` for an unfiled item. The categories and the industries of every
row are resolved in one batched query each, however many items the table shows.

## The identity card

`GET /catalog-items/{id}` (and the body of every write) is the whole card: the code,
title, kind and status; `category_id`, `buyer_persona_id` and `sales_model_id` with the
matching `category`, `buyer_persona` and `sales_model` objects
(`{catalog_category_id|catalog_persona_id|catalog_sales_model_id, title}` or `null`);
the seven text fields `description`, `delivery_terms`, `lead_time`,
`after_sales_policy`, `sla_description`, `warranty_description` and `legal_notes`;
`industry_ids`, `industries` and `created_at`.

## Writing one

```json
{
  "code": "SRV-CRM-CLOUD-01",
  "title": "…",
  "kind": "SERVICE",
  "status": "ACTIVE",
  "category_id": 1,
  "buyer_persona_id": 1,
  "sales_model_id": 2,
  "description": "…",
  "delivery_terms": "…",
  "lead_time": "…",
  "after_sales_policy": "…",
  "sla_description": "…",
  "warranty_description": "…",
  "legal_notes": "…",
  "industry_ids": [3, 4]
}
```

`POST` answers `201` with the card. `code` (at most 80 characters) and `title` (at most
200) and `kind` are required; `status` defaults to `ACTIVE`. The code is unique per
tenant: a repeat answers `409 CONFLICT` with the message on `field_errors.code`
(`catalog.code_already_exists`). The category, persona and sales model must exist in the
tenant and be active, and every industry must be an active industry of the tenant named
once; each failure is a `422` on its own field. The industries are written to
`crm_catalog_item_industry` in the same transaction as the item.

`PATCH` takes the same fields, all optional, and answers `200` with the card. A field
sent as an explicit `null` is cleared when it may be (the three references and the seven
text fields); the code, title, kind and status cannot be cleared. `industry_ids`, when
present, replaces the whole set (an empty list clears it) by its difference, so an
industry that stays keeps its link, its author and its date. A PATCH that names no
field answers `422` on `field_errors.*` (`catalog.change_is_empty`). Changing the code
re-checks its uniqueness. The `kind` may be changed at any time. A reference the item
already holds (or an industry it is already linked to) is not re-judged, so an unrelated
edit is never refused because that entry was retired since.

## Reference lists

The three lists return the tenant's rows by `sort_order` and then id, retired ones
included unless `?active=1` asks for the active only:

| List | Row |
| --- | --- |
| categories | `catalog_category_id, code, title, parent_id, is_active, sort_order` |
| personas | `catalog_persona_id, code, title, is_active, sort_order` |
| sales models | `catalog_sales_model_id, code, title, is_active, sort_order` |

## Out of scope until decision O01

The channels (`crm_catalog_channels`) and the reserved references `unit_id`,
`sales_commitment_id`, `after_sales_policy_id` and `sla_template_id` are neither accepted
nor emitted. The free-text `after_sales_policy` and `sla_description` fields are separate
columns and are supported.
