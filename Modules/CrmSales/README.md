# Sales documents API

Estimates, proposals and proformas: the documents a price is quoted on. They are
deliberately apart from any financial invoice, and they carry their own CRM
numbering rather than drawing from the consignment number ranges.

A document has one identity and a chain of content revisions. The document points
at the revision it currently shows; every revision points back at the one it
superseded. An issued revision is frozen and is never edited — it is superseded.

Every endpoint needs bearer authentication, a completed password change and the
`Customer` entitlement. Reading needs `crm.sales_document.view` and every write
needs `crm.sales_document.manage`, both at `TENANT` scope; the authorization
catalog grants them to `hq_admin`. All `{id}` segments must be numeric. A document
of another tenant is reported as `404 RESOURCE_NOT_FOUND`, exactly like one that
does not exist.

| Method | Path |
| --- | --- |
| `GET` | `/api/v1/crm/sales-documents?customer_id=&status=` |
| `POST` | `/api/v1/crm/sales-documents` |
| `GET` | `/api/v1/crm/sales-documents/{id}` |
| `POST` | `/api/v1/crm/sales-documents/{id}/versions` |
| `POST` | `/api/v1/crm/sales-document-versions/{id}/issue` |
| `POST` | `/api/v1/crm/sales-document-versions/{id}/accept` |
| `POST` | `/api/v1/crm/sales-document-versions/{id}/cancel` |

## Drawing one up

```json
{
  "opportunity_id": 3,
  "document_type": "PROPOSAL",
  "document_no": null,
  "version": {
    "currency": "IRR",
    "total": 180000000,
    "expires_at": "2026-10-15T23:59:59+03:30",
    "terms": "شرایط پرداخت نقدی"
  }
}
```

The customer is **not** accepted as input: it is taken from the opportunity, so a
quote can never name a party the deal was not about. That record must already be
in phase `CUSTOMER` — a price is quoted to a buyer, not to a lead.

`document_type` is `ESTIMATE`, `PROPOSAL` or `PROFORMA`. A null or absent
`document_no` asks the server for the next number of that type and year, such as
`PR-2026-0012`; the counter runs per tenant, type and year, and the number is
unique within the tenant. `total` is a whole non-negative number in the currency's
own unit, and `expires_at` cannot already be in the past. The document opens on
revision 1 in status `DRAFT` and answers `201` with the whole document.

## Moving a revision

Only the revision a document currently shows can move; acting on a superseded one
answers `409`. Each move answers with the whole document.

| Move | From | What it does |
| --- | --- | --- |
| `issue` | `DRAFT` | Freezes the content |
| `accept` | `ISSUED` | Records the customer's acceptance |
| `cancel` | `DRAFT`, `ISSUED` | Closes the revision |

Issuing is the moment the content freezes: the server stamps `issued_at`, copies
the customer into `customer_snapshot`, and fingerprints the content into
`content_hash`. An issued document therefore stays readable and provable after the
live customer record moves on. Neither issuing nor accepting is possible once the
validity the document itself states has run out; cancelling stays open, because an
out-of-date document is exactly what an operator wants to close.

`content_hash` is a bare SHA-256 digest of the agreed content. The column is 64
characters wide, so the algorithm is implied by it rather than repeated as a
`sha256:` prefix in the value. Two revisions that say the same thing carry the same
hash on purpose: it fingerprints content, not identity.

## Correcting an issued document

`POST /sales-documents/{id}/versions` supersedes the current revision with a fresh
draft that points back at it, and the document starts showing the new one. The
frozen revision keeps everything issuing stamped on it.

```json
{ "total": 200000000, "terms": null }
```

Every field is optional and an omitted one is carried over unchanged, so restating
a frozen document costs an empty body. An explicit `null` clears the terms.
`status`, `issued_at`, `content_hash` and `customer_snapshot` always start blank on
the new revision. A draft is corrected in place rather than superseded, so asking
for a new revision while one is still a draft answers `409`.

## Open decisions

Line items (`crm_sales_lines`) and the discount approval flow are still undecided
upstream, so a document states one total rather than the rows behind it. `REVIEW`
exists in the status column but no transition reaches it yet.

## Customer contracts

The contract file (شناسنامه قرارداد) of a customer lives in `crm_contracts`. It is
served under the customer, apart from the sales documents, behind its own pair of
permissions: `crm.contract.view` for reading and `crm.contract.manage` for
recording (tenant scope, `Customer` module entitlement, like sales documents).

```
GET  /api/v1/crm/customers/{customerId}/contracts
POST /api/v1/crm/customers/{customerId}/contracts
```

The list is the whole file of that customer, without paging, newest first
(`created_at` desc, then id desc). Every item, and the `201` body of the POST, has
the same shape:

```json
{
  "contract_id": "9", "customer_id": "11", "reference_no": "CN-1405-01",
  "start_date": "2026-10-01", "end_date": "2027-09-30", "amount": 900000000,
  "commitments": "…", "status": "ACTIVE", "opportunity_id": "3",
  "proforma_version_id": "1", "documents": [{ "document_id": "1", "title": "…" }],
  "created_at": "2026-09-29T10:00:00.000000Z"
}
```

Body of the POST: `reference_no` (required, at most 120 characters, trimmed),
`opportunity_id?`, `proforma_version_id?`, `start_date?` and `end_date?` (`Y-m-d`),
`amount?` (whole rials, `>= 0`), `commitments?` (text) and `status?`.

- **Status is a free label.** The prototype says the status list is not final, so no
  enum is assumed: the value is a trimmed string matching `^[A-Z][A-Z0-9_]{0,39}$`
  and defaults to `DRAFT`.
- **Window.** An `end_date` before `start_date` answers `422` on `end_date`; the same
  day is a valid one-day window (the table has the same CHECK on MySQL).
- **Customer.** It must exist in the tenant (`404` otherwise; another tenant's
  customer is indistinguishable from a missing one) and be in phase `CUSTOMER`
  (`422` on `customer_id`), the same rule sales documents follow: a contract is signed
  with a buyer, not with a lead.
- **References.** `opportunity_id` must be an opportunity of the same customer in
  the same tenant, and `proforma_version_id` a sales document revision of a document
  of the same customer (what the MySQL trigger enforces); otherwise `422` on that field.
- **Reference number.** The schema only indexes it, so it is not unique in general.
  The one refusal is an exact repeat of `(tenant, customer, reference_no)`, which
  answers `409` with error code `CONTRACT_REFERENCE_EXISTS`. Another customer may
  reuse the number. The customer row is locked while the contract is written, so two
  concurrent posts cannot both pass the check.

### Documents

Files are not uploaded here. They are attached through the document archive,
`POST /api/v1/crm/documents/{id}/links` with `resource_type: "CONTRACT"` and the
`contract_id` as `resource_id` (`CONTRACT` joined the archive's link registry, and the
archive checks the contract exists in the tenant). The contract list reads those links
back for the whole list with one query on `document_links`, through DocumentStore's
`DocumentLinkRepositoryInterface`, and prints `documents: [{document_id, title}]`,
oldest link first.

### External invoices

`POST /api/v1/crm/customers/{customerId}/external-invoices` (CrmFinance) accepts an
optional `contract_id`, which must be a contract of the same customer and tenant
(`422` on `contract_id` otherwise). It is checked through this module's
`ContractRepositoryInterface`.
