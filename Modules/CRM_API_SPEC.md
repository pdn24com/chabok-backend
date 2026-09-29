# Chabok CRM API — spec for OpenAPI/Swagger

Base path: `/api/v1`. All endpoints require `Authorization: Bearer <access_token>` (middleware `access.auth` + `password.changed`).
Endpoints marked **[Idempotency-Key]** accept an optional `Idempotency-Key` header (string, length is validated; 422 if invalid).
Every request/response carries `X-Correlation-ID` response header.
Path ids are numeric (`whereNumber`). Timestamps named `*_on`, `expected_close`, `valid_from/valid_to (request)`, `occurred_at (opportunity transition request)` are **integer Unix seconds** (min -2208988800, max 4102444800). Fields named `*_at` in responses are ISO-8601 strings. Money = integer rials (no minor unit).

## Common envelopes
Success: `{ "data": <payload>, "meta": {}, "correlation_id": "string" }`
Paginated success: `meta.pagination = { page, page_size, total, total_pages }`, `data` is an array.
Error: `{ "error_code": "string", "message": "string", "locale": "string", "field_errors": { "field": ["msg"] }, "details": {}, "correlation_id": "string" }`
Common error statuses: 401, 403 (missing permission), 404, 409/422 (domain rule / validation). Validation = 422 with `field_errors`.
Create endpoints return **201**, others **200**.

## Enums
- BankAccountStatus: ACTIVE, INACTIVE
- FinancialEntryKind: REVENUE, RECEIPT, ADJUSTMENT
- SalesDocumentType: ESTIMATE, PROPOSAL, PROFORMA
- SalesDocumentStatus: DRAFT, REVIEW, ISSUED, ACCEPTED, CANCELLED
- FunnelStepOutcome: OPEN, WON, LOST
- TaskStatus: OPEN, COMPLETED, CANCELLED
- TaskPriority: HIGH, MEDIUM, LOW
- TaskScope: mine, accessible
- TaskBucket: overdue, today, future, undated, waiting, closed
- TaskAssignmentEventType: CREATE, REFER, CLAIM, REASSIGN
- ActivityType: CALL, MESSAGE, MEETING, REFERRAL, NOTE
- ActivityDirection: INBOUND, OUTBOUND
- ActivityChannel: PHONE, EMAIL, SMS, WHATSAPP, TELEGRAM, BALE, INSTAGRAM, POST
- CallOutcome: ANSWERED, BUSY
- MeetingMode: ONLINE
- TeamStatus: ACTIVE, INACTIVE
- MembershipStatus: ACTIVE, ENDED
- BulkMembershipOperation: ADD, END, TRANSFER
- CustomerKind: PERSON, COMPANY
- CustomerPhase: LEAD, CUSTOMER
- CustomerLifecycle: ACTIVE, INACTIVE, ARCHIVED
- CreditRating: LOW_RISK, MEDIUM_RISK, HIGH_RISK
- CustomerHistoryCategory: work, finance, correspondence, tickets, operations, changes

## Permissions (per module; named as middleware/gates)
crm.industry.view · crm.finance.view / crm.finance.manage · crm.opportunity.view / crm.opportunity.manage · crm.sales_document.view / crm.sales_document.manage · crm.task.view / crm.task.manage · crm.team.view / crm.customer.view / crm.customer.manage (financial-details endpoints use the finance permissions instead) · crm.team.manage (exact view-vs-manage mapping per endpoint: GET = view, POST/PATCH = manage; bulk/end membership = crm.team.manage).

---
## Tag: CRM - Catalog
### GET /crm/industries  (paginated)
Query: page int≥1; per_page int 1–100; search string ≤200; is_active bool.
Item: `Industry { industry_id, code, title, description, is_active(bool), sort_order(int) }`

## Tag: CRM - Sales Funnels & Opportunities
### GET /crm/sales-funnels
Query: active (bool). No pagination. data: `SalesFunnel[]`
`SalesFunnel { sales_funnel_id, code, title, description, catalog_item_id, is_active, steps: FunnelStep[] }`
`FunnelStep { sales_funnel_step_id, code, title, sort_order(int), outcome_type(FunnelStepOutcome), is_active }`

### GET /crm/opportunities  (kanban board)
Query: funnel_id* int; customer_id int; assignee_id int.
data: `{ funnel: SalesFunnel, columns: [ { step: FunnelStep, items: OpportunityCard[] } ] }` (every step is a column, even empty).
`OpportunityCard { opportunity_id, title, customer_id, customer?{customer_id, display_name, phase}, assignee_id, assignee?{user_id, display_name}, amount(int|null), probability(decimal string|null), expected_close(int unix|null), next_task?{task_id, title, status, due_at} }`

### POST /crm/opportunities  → 201
Body: customer_id* int; funnel_id* int; title* string ≤200; assignee_id* int; amount int 0..int64 nullable; probability number 0–100, max 2 decimals, nullable; expected_close int unix nullable. `current_step_id` is PROHIBITED (server picks first step).
data: `{ opportunity: Opportunity, event: OpportunityEvent }`
`Opportunity { opportunity_id, customer_id, funnel_id, title, assignee_id, amount, probability, expected_close, close_reason, current_step_id, current_step?: FunnelStep, created_at }`

### POST /crm/opportunities/{opportunityId}/step-transitions → 201
Body: from_step_id* int (concurrency guard); to_step_id* int; reason string ≤2000 nullable; occurred_at int unix nullable (default now); evidence_activity_id int nullable; close_reason string ≤2000 nullable.
data: `{ opportunity: Opportunity, event: OpportunityEvent }`. 409/422 if from_step_id is stale.

### GET /crm/opportunities/{opportunityId}/events
data: `OpportunityEvent[]`
`OpportunityEvent { opportunity_event_id, opportunity_id, from_step_id|null, from_step_code, from_step_title, from_outcome_type|null, to_step_id, to_step_code, to_step_title, to_outcome_type, funnel_id, funnel_title, reason, evidence_activity_id, occurred_at(ISO), actor?{user_id, display_name} }`

## Tag: CRM - Sales Documents
### GET /crm/sales-documents
Query: customer_id int; status SalesDocumentStatus (status of current revision). data: `SalesDocumentListItem[]`
`SalesDocumentListItem { sales_document_id, document_no, document_type, customer{customer_id, display_name}|null, current_version{sales_document_version_id, version_no, status, currency, total, expires_at}|null, created_at }`

### POST /crm/sales-documents → 201
Body: opportunity_id* int (customer derived from it); document_type* SalesDocumentType; document_no string ≤80 nullable (null = server generates next number for type+year); version*: { currency* string, 3 uppercase letters e.g. IRR; total* int ≥0; expires_at* date (ISO); terms string ≤5000 nullable }.
data: `SalesDocument`

### GET /crm/sales-documents/{documentId}
data: `SalesDocument { sales_document_id, document_no, document_type, customer{customer_id, display_name, customer_code}|null, opportunity{opportunity_id, title}|null, current_version: SalesDocumentVersion|null, versions: SalesDocumentVersion[], created_at }`
`SalesDocumentVersion { sales_document_version_id, version_no(int), previous_version_id, status, currency, total(int), terms, expires_at, issued_at, content_hash, customer_snapshot(object), created_at }`

### POST /crm/sales-documents/{documentId}/versions → 201
New revision. Body all optional: currency (3 upper letters); total int ≥0; expires_at date; terms string ≤5000 nullable. Empty body restates previous revision. data: `SalesDocument`

### POST /crm/sales-document-versions/{versionId}/issue | /accept | /cancel
No body. State transitions on a version. data: SalesDocument/Version as returned by handler (document the response as `SalesDocumentVersion` wrapped in envelope; invalid transition → 409/422).

## Tag: CRM - Finance
### GET /crm/customers/{customerId}/bank-accounts
data: `BankAccount[]`. `BankAccount { bank_account_id, customer_id, bank_name, iban_masked, card_number_masked, account_no_masked, is_primary, status, created_at }`
### POST /crm/customers/{customerId}/bank-accounts → 201
Body: bank_name* ≤120; status* BankAccountStatus; iban string `^IR\d{24}$` nullable; card_number string `^\d{16}$` nullable; account_no string ≤40 nullable; is_primary bool. (At least one of iban/card_number/account_no is required — domain rule.)
### GET /crm/customers/{customerId}/external-invoices
data: `ExternalInvoice[]`. `ExternalInvoice { external_invoice_id, customer_id, external_system, reference_no, amount, issued_on(unix), due_on(unix|null), contract_id, opportunity_id, created_at }`
### POST /crm/customers/{customerId}/external-invoices → 201
Body: external_system* ≤80; reference_no* ≤120; amount* int ≥1; issued_on* unix; due_on unix nullable (must not precede issued_on); opportunity_id int nullable.
### GET /crm/customers/{customerId}/financial-entries
Query: kind FinancialEntryKind. Whole ledger, no pagination. data: `FinancialEntry[]`
`FinancialEntry { financial_entry_id, customer_id, kind, amount(int), effective_on(unix), source_ref, invoice_id, reverses_id, allocated(int), created_at }`
### POST /crm/customers/{customerId}/financial-entries → 201
Body: kind* FinancialEntryKind; amount* int ≠0 (signed; reversal is negative); effective_on* unix; source_ref* ≤120; invoice_id int nullable; reverses_id int nullable.
### POST /crm/financial-entries/{entryId}/allocations → 201
Body: invoice_id* int; amount* int ≥1 (must not exceed remaining of receipt).
data: `FinancialAllocation { financial_allocation_id, receipt_entry_id, invoice_id, amount, remaining, created_at }`

## Tag: CRM - Tasks  (base path /api/v1/tasks)
### GET /tasks
Query: scope TaskScope; bucket TaskBucket; q string ≤200.
data: `{ summary: { open, waiting_customer, today, overdue, internal }, items: Task[] }`
`Task { task_id, title, description, status, priority, due_at, remind_at, completed_at, completion_result, assignee{user_id, display_name}|null, customer{customer_id, display_name}|null, opportunity{opportunity_id, title}|null, is_overdue(bool), created_at }`
### POST /tasks → 201
Body: title* ≤200; description ≤5000 nullable; priority TaskPriority; due_at date nullable; remind_at date nullable (must be before due_at); customer_id, opportunity_id, assignee_id, team_context_id int nullable; operation_key string ≤120 nullable (idempotency).
data: `{ task: Task, assignment_event: TaskAssignmentEvent }`
`TaskAssignmentEvent { task_assignment_event_id, event_type, from_user_id, to_user_id, to_team_id, reason, occurred_at }`
### POST /tasks/{taskId}/complete
Body: completion_result* string ≤5000. data: `Task`
### POST /tasks/{taskId}/assignments → 201
Body: event_type* TaskAssignmentEventType; to_team_id int nullable; to_user_id int nullable (explicit null = assign to nobody); reason ≤2000 nullable; due_at, remind_at date nullable; expected_assignee_id int nullable (concurrency guard); operation_key ≤120 nullable.
data: `{ assignment_event: TaskAssignmentEvent, task: Task }`
### POST /tasks/{taskId}/actions → 201
Body: activity*: { type* ActivityType; occurred_at* date; body ≤5000; result ≤5000; direction ActivityDirection; contact_customer_id int; contact_value ≤320; channel ActivityChannel; duration_minutes int ≥0; call_outcome CallOutcome; meeting_mode MeetingMode; location ≤300; meeting_url ≤500; document_version_id int } (all except type/occurred_at nullable); task?: { status TaskStatus; due_at date; remind_at date; completion_result ≤5000 } (all nullable/optional).
data: `{ activity: Activity, task: Task }`
`Activity { activity_id, type, occurred_at, body, result, direction, channel, contact_customer_id, contact_value, duration_minutes, call_outcome, meeting_mode, location, meeting_url, document_version_id }`

## Tag: CRM - Teams
### GET /crm/teams
Query: status TeamStatus. No pagination. data: `Team[]`
`Team { team_id, title, status, parent_team_id(string|null), supervisor{user_id, display_name}|null, member_count(int) }`
### POST /crm/teams → 201  **[Idempotency-Key]**
Body: title* ≤200; supervisor_user_id* int; status TeamStatus; parent_team_id int nullable.
data: `Team + membership_id` (supervisor's membership id).
### PATCH /crm/teams/{teamId}
Body (all optional): title ≤200; supervisor_user_id int; status TeamStatus; parent_team_id int nullable. data: `Team`
### GET /crm/team-members  (paginated)
Query: page, per_page(1–100), team_id int, status MembershipStatus, q string ≤200 (name/username).
Item: `TeamMember { membership_id, team_id(string), team{team_id, title}|null, user{user_id, username, display_name}|null, status, valid_from(ISO), valid_to(ISO|null) }`
### POST /crm/teams/{teamId}/members → 201  **[Idempotency-Key]**
Body: user_id* int; valid_from int unix nullable (default now). `role` is PROHIBITED (role lives in IAM). data: `TeamMember`
### POST /crm/memberships/{membershipId}/end
Body: valid_to int unix nullable (default now); replacement_user_id int nullable. data: `TeamMember`
### POST /crm/memberships/bulk/preview
Body: `BulkMembershipRequest`. Dry run, changes nothing. data: `BulkMembershipResult`
### POST /crm/memberships/bulk  **[Idempotency-Key]**
Body: `BulkMembershipRequest { operation* BulkMembershipOperation; user_ids* int[] (1–200); source_team_id int nullable; target_team_id int nullable; replacement_user_id int nullable; confirm_open_tasks bool (must be true if open work is touched) }`. `operation_id` is PROHIBITED (server-issued).
`BulkMembershipResult { events: [{ membership_event_id, membership_id(string), event_type, operation_id|null }], memberships: TeamMember[], affected_tasks }` (affected_tasks: array/count of open tasks touched — document as `object` loosely)

---
# Module: Customer (customers, profile, addresses, org structure, history)

## Tag: CRM - Customers
### GET /customers  (paginated)
Query: page; per_page(1–100); display_name string ≤200; customer_code ≤80; phase CustomerPhase; kind CustomerKind; lifecycle CustomerLifecycle; assignee_id int; updated_at date `Y-m-d`; updated_at_from date `Y-m-d`; updated_at_to date `Y-m-d` (≥ updated_at_from).
Item: `CustomerListItem { customer_id, kind, phase, lifecycle, display_name, customer_code, assignee{user_id, display_name}|null, updated_at(ISO) }`
### POST /customers → 201  **[Idempotency-Key]**
Body: first_name* ≤120; family_name* ≤120; display_name* ≤200; customer_code ≤80 nullable; kind* CustomerKind; phase* CustomerPhase; mobile* ≤32; industry_id int nullable; assignee_id int nullable; country_code* 2 uppercase letters; province_id & city_id: required if country_code=IR, prohibited otherwise; foreign_city: required and ≤200 if country_code≠IR, prohibited if IR; postal_code string (Iranian 10-digit rule enforced in domain); address_text ≤1000 nullable.
data: `Customer { customer_id, hq_id, first_name, family_name, display_name, customer_code, kind, phase, lifecycle, mobile, industry_id, assignee_id, created_by, converted_at, created_at, updated_at, address: CustomerAddress|null }`

### GET /crm/customers/{customerId}/detail
data: `{ customer: CustomerIdentity, default_address: CustomerAddress|null, primary_industry: {industry_id, title}|null, open_tasks: [{task_id, title, status, due_at}], opportunities: [{opportunity_id, title, step:{title, outcome_type}, amount}], missing: string[] (profile gaps) }`
`CustomerIdentity { customer_id, kind, phase, lifecycle, display_name, customer_code, assignee{user_id, display_name}|null }`

### GET /crm/customers/{customerId}/profile
data: `CustomerProfile { customer_id, kind, phase, lifecycle, display_name, customer_code, assignee_id, primary_industry{industry_id,title}|null, updated_at }`
### PATCH /crm/customers/{customerId}/profile
Body (all optional): kind; phase; lifecycle; display_name ≤200 nullable; customer_code ≤80 nullable; assignee_id int nullable; primary_industry_id int nullable. data: `CustomerProfile`

### GET /crm/customers/{customerId}/extended-details
### PUT /crm/customers/{customerId}/extended-details
PUT replaces the whole set (omitted fields are cleared; nothing required). Body: salutation ≤80; birth_date int unix; trade_name ≤200; legal_form ≤120; legal_name ≤200; registration_no ≤80; registration_date int unix; registration_place ≤200; need_summary ≤2000; budget int ≥0; budget_known bool; authority_note ≤2000; need_confirmed bool; timeframe ≤2000; qualification_result ≤40 (all nullable).
data: `ExtendedDetails { customer_id, salutation, birth_date(unix), trade_name, legal_form, legal_name, registration_no, registration_date(unix), registration_place, need_summary, budget, budget_known, authority_note, need_confirmed, timeframe, qualification_result, evaluated_by{user_id,display_name}|null, evaluated_at(ISO), updated_at(ISO) }`

### GET /crm/customers/{customerId}/financial-details   (finance permission)
### PUT /crm/customers/{customerId}/financial-details   (finance permission)
Body: financial_reference_date* int unix; source_note* ≤2000; credit_limit int ≥0 nullable; credit_rating CreditRating nullable; settlement_terms ≤2000; accounting_code ≤80; accounting_title ≤200; revenue, receipts, direct_cost, balance: signed int nullable.
data: `FinancialDetails { customer_id, credit_limit, credit_rating, settlement_terms, financial_reference_date(unix), source_note, accounting_code, accounting_title, revenue, receipts, direct_cost, balance, updated_at }`

## Tag: CRM - Customer Addresses  (/crm/customers/{customerId}/addresses)
### GET /  → data: `CustomerAddress[]`
### GET /{addressId} → data: `CustomerAddress`
### POST / → 201
Body: country_code* 2 uppercase letters; purpose* ≤60; address_text* ≤1000; province_id int (only if country=IR; required if city_id sent); city_id int (only if IR); foreign_region ≤200 & foreign_city ≤200 (only if country≠IR); postal_code ≤10; plaque ≤40 (string); unit ≤40 (string); latitude −90..90 and longitude −180..180 (both or neither); is_default bool. (All except first three nullable/optional.)
### PATCH /{addressId}
All fields optional (same fields as POST, cross-field rules judged in domain → 422). data: `CustomerAddress`
`CustomerAddress { customer_address_id, country_code, country?{country_id,name}, purpose, province_id, city_id, province?{province_id,name}, city?{city_id,name}, foreign_region, foreign_city, address_text, postal_code, plaque, unit, latitude, longitude, is_default, created_at, updated_at }`

## Tag: CRM - Customer Org Structure
### GET /crm/customers/{customerId}/org-structure
data: `{ company:{customer_id, kind, display_name, customer_code}, departments: Department[] (tree) }`
`Department { department_id, parent_department_id(string|null), title, cost_center_code, positions: Position[] }`
`Position { position_id, department_id, title, decision_level, delegation_limit(int|null) }`
### POST /crm/customers/{customerId}/departments → 201
Body: title* ≤200; parent_department_id int nullable (null = root); cost_center_code ≤80 nullable. data: `Department`
### PATCH /crm/customers/{customerId}/departments/{departmentId}
Body all optional (same fields). data: `Department`
### POST /crm/customers/{customerId}/departments/{departmentId}/positions → 201
Body: title* ≤200; decision_level ≤80 nullable; delegation_limit int ≥0 nullable. data: `Position`
### PATCH /crm/customers/{customerId}/positions/{positionId}
Body all optional (same fields). data: `Position`

## Tag: CRM - Customer History
### GET /crm/customers/{customerId}/history
data: `{ customer_id, categories: [{ category: CustomerHistoryCategory, total(int), unavailable_reason: string|null, preview: HistoryEntry[] }], last_interaction_at(ISO|null), days_since_last_interaction(int|null), open_work_count(int) }`
### GET /crm/customers/{customerId}/history/timeline  (paginated)
### GET /crm/customers/{customerId}/history/{category}  (paginated; category ∈ work|finance|correspondence|tickets|operations|changes, else 404)
Query (both): page; per_page(1–100); search ≤200.
`HistoryEntry { category, entry_type, entry_id, title, kind, status, actor, amount, occurred_at(ISO) }` (kind/status/actor/amount nullable; actor is a string or object — document loosely)

---
# Total: 54 endpoints (Customer 21, Catalog 1, Opportunities 5, Sales Documents 7, Finance 7, Tasks 5, Teams 8).
