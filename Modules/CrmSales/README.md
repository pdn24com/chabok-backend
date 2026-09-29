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
