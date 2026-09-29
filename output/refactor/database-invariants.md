# Database invariants retained intentionally

The fresh-install schema is expressed with Laravel Blueprint. Raw SQL is limited to MySQL CHECK constraints and integrity rules that must reject direct SQL writes as well as application writes. Eloquent observers alone cannot enforce these rules: bulk updates, maintenance tools and concurrent database connections bypass model events. Existing integration tests intentionally exercise these direct-write guarantees.

Migration files create one table or install one table’s constraints. They do not read business rows, backfill history, narrow an old schema, or seed catalogs. All declared foreign keys now use unsigned integers. Public UUIDs remain unique external identities; application writes resolve them to stored integer references. Tenant composite constraints and trigger lookups use the same numeric keys.

## Retained guarantees

| Table | Required guarantee |
|---|---|
| `areas` | Legacy generated area code for direct SQL inserts that omit the code. An Eloquent creating hook would not cover those existing write paths; retained as a compatibility exception. |
| `audit_events` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `commitment_schedule_scopes` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `commitment_schedule_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `commitment_schedule_windows` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `consignment_number_allocations` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `consignment_pricing_charge_lines` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `consignment_pricing_versions` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `consignment_status_events` | Status references must belong to the same tenant or the global catalog, including direct SQL writes. |
| `consignments` | Status references must belong to the same tenant or the global catalog, including direct SQL writes. |
| `crm_activities` | Per-action column shape is enforced declaratively; a contacted person must be a PERSON record and referral evidence must belong to the same task, including direct SQL writes. |
| `crm_activity_participants` | Each participant is exactly one internal user or one PERSON customer; historical hourly cost is rejected for customer participants, including direct SQL writes. |
| `crm_catalog_categories` | A category cannot be its own parent. |
| `crm_customer_address` | Coordinates stay in range and latitude/longitude are written as a pair. |
| `crm_membership_events` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `crm_opportunities` | Probability stays within 0..100 and the current step must belong to the opportunity funnel, including direct SQL writes. |
| `crm_opportunity_events` | Append-only step history cannot be updated or deleted; the before-state snapshot group is all-or-nothing and each step must belong to its own funnel. |
| `crm_sales_funnel` | A funnel accepts a SERVICE catalog item only, including direct SQL writes. |
| `crm_sales_funnel_steps` | Display order is a positive number. |
| `crm_task_assignment_events` | Append-only assignment history cannot be updated or deleted; a referral, reassignment or membership change always carries a reason. |
| `crm_tasks` | Completion carries a timestamp and a result, a reminder requires a current owner, and a closed task holds no active reminder; an opportunity-bound task keeps the opportunity customer, including direct SQL writes. |
| `crm_team_members` | Only one current membership per tenant, team and user; the validity window is ordered and an ended membership carries an end date. |
| `crm_teams` | A team cannot be its own parent team. |
| `delivery_task_history` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `last_mile_resolution_evidence` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `manifests` | Status references must belong to the same tenant or the global catalog, including direct SQL writes. |
| `operational_exception_history` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `operational_status_revisions` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `operational_statuses` | Published status identity is stable and historical status references cannot be deleted. |
| `parcel_custody_events` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `parcels` | Status references must belong to the same tenant or the global catalog, including direct SQL writes. |
| `pricing_charge_lines` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `pricing_quote_lines` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `pricing_quotes` | Quote evidence cannot be rewritten while documented acceptance/status changes remain possible. |
| `pricing_snapshots` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `pricing_zone_members` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `pricing_zone_set_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `pricing_zones` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `route_plan_resolution_evidence` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `service_availability_bindings` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_coverage_references` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_eligibility_rules` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_offering_commitment_bindings` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_offering_option_rules` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_offering_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_option_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `service_type_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `shipping_method_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `tariff_rate_rules` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |
| `tariff_service_attachments` | Append-only audit, ledger, accepted-pricing or operational-history evidence cannot be updated or deleted. |
| `tariff_versions` | Published version content and children cannot be rewritten; only permitted lifecycle transitions remain mutable. |

## Existing regression evidence

- `ConsignmentIntegrationTest`: direct mutation of allocation and accepted pricing history fails.
- `ServiceCatalogPricingIntegrationTest`: direct changes to published versions, their children, commitment windows and accepted charge lines fail.
- `PickupRoutingProductIntegrationTest`: route resolution evidence is immutable.
- `LocalUserCommandTest`: maintenance must leave the history-delete guards effective.
- `OperationalSupportIntegrationTest`: direct invalid status/tenant writes fail.

CHECK constraints enforce declarative row invariants (range, state consistency, coordinates and owner identity). Laravel Blueprint does not expose these MySQL CHECK expressions; the SQL definitions are kept in dedicated integrity migrations.

## Review completed — 2026-09-26

All 90 triggers remain, and the final MySQL integration suite exercises their direct-write guarantees. Ordinary schema migrations contain Blueprint schema only; these guarantees remain isolated in the integrity migrations. The area-code trigger is a compatibility exception as well as the strict integrity triggers; it has not been silently counted as removed.

## Numeric-reference update — 2026-09-27

Published-content triggers resolve parent versions through `id`, including pricing-zone members and tariff attachments. Direct-write guards, cascade behavior, append-only rules, tenant checks and workflow conditions remain enforced. The isolated MySQL regression suite tests these guarantees against the numeric schema.

## CRM data model — 2026-09-28

ERD v14 introduces 21 `crm_*` tables across the Customer, CrmCatalog, CrmTeam, CrmOpportunitie and CrmTask modules. Their guarantees follow the existing split: Blueprint schema in the create migrations, foreign keys in the foreign migrations, and CHECK constraints plus triggers in the integrity migrations. `crm_team_members` uses a stored generated column because MySQL cannot express the partial unique index that "one current membership" requires. These guarantees are not yet covered by an integration test; the MySQL regression suite still needs CRM cases.
