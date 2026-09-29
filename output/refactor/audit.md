# Refactor audit — 2026-09-23

## 1. Current Architecture
16 Laravel modules, 1,396 owned files (including resources/tests); Presentation → UseCase Handler → Application service/repository contract → Infrastructure Query Builder. Many Application root services dispatch back into handlers.
## 2. Main Complexity Problems
Anonymous structured arrays, toBase() discarding models, compressed multi-statement code, hidden dependency names, pure-domain calculators decoding persistence JSON.
## 3. Unnecessary Abstractions
Pass-through execute methods, service façades dispatching to handlers, generic array result wrappers. Real external-service, tenant and transaction boundaries must remain explicit.
## 4. DB / Eloquent Problems
662 DB::table occurrences including tests; three recursive SQL traversals; repeated scope graph loading; models have few relationships. Spatial predicates and dashboard aggregates have legitimate SQL needs.
## 5. UseCase Problems
handle is the public convention; private execute frequently duplicates the handler entry point.
## 6. Service Problems
Concrete DI and contract DI are mixed. Services are scattered across Application root and Services.
## 7. Repository Problems
Many return object/array, use toBase(), and do not express entity types. Cross-module projections require care to preserve tenant isolation.
## 8. DTO / Array Problems
Known input and result structures are anonymous across application flows. Lists/maps and HTTP/persistence boundary arrays are legitimate and must not be blindly wrapped.
## 9. Folder Structure Problems
Root Application contains services, exceptions, catalogs, and utilities together.
## 10. Naming / Readability Problems
Fully qualified dependencies in bodies, short dependency names, nested ternaries and callbacks, statements sharing lines.
## 11. Database / Primary Key Problems
Public UUIDs are referenced by API routes, foreign keys, audit and outbox. Replacing existing PKs requires an additive migration/backfill/cutover plan; editing historical migrations alone cannot safely migrate deployed databases. Triggers enforce immutability and concurrency and cannot simply be deleted.
## 12. Proposed Target Architecture
Preserve modules; Controller/FormRequest → handle → cohesive Service/Model → Resource. Application services use explicit Contracts; DTOs expose known structures; domain calculations remain pure. Laravel/Eloquent are allowed in Application; HTTP belongs in Presentation.
## 13. Refactor Phases
Baseline tests and complete pattern inventory; normalize service contracts/imports/locations; simplify hierarchy and persistence; type critical calculations/import boundaries; verify behavior and document remaining risks.
## 14. High-risk Changes
UUID/FK migration, money precision and historical fingerprints, tenant authorization, outbox/audit atomicity, schema triggers, XLSX import constraints.

## Module flow review (before edits)

| Module | Current flow | Target and rationale |
|---|---|---|
| Foundation | middleware → custom ports/services → infrastructure | Keep tenant/security ports; explicit service contracts and shared graph value object. |
| Organization | controller → façade → handler → network repository → recursive SQL | Contract-based services; Eloquent graph load and finite traversal. |
| Authorization | controller → façade/handlers → services → repositories | Same behavior with explicit DI; share graph traversal; batch reference loading. |
| Identity | controller → identity service → handlers → repositories/security ports | Explicit service contract; preserve authentication/session boundaries. |
| User | controller → service → handlers → repositories/operational ports | Explicit service contract; preserve cross-module orchestration. |
| Operations | controller → service → handlers → array-driven services/repositories | Explicit contracts and readable imports; retain transactional workflow. |
| Consignment | controller → services/handlers → pricing and persistence | Contracts and service folders; preserve snapshots and idempotency. |
| Manifest | controller → services/handlers → workflow/repositories | Contracts and service folders; preserve custody/event ordering. |
| Pricing | controller → services/handlers → array calculators/imports | Typed pricing core, explicit enum/precision policies and boundary normalization. |
| ServiceCatalog | controller → services/handlers → version repositories | Consistent contracts/locations; retain version and dependency rules. |
| Geography | resolver → repository/spatial adapter | Keep genuine spatial boundary; explicit service imports/contracts. |
| Outbox | processor → claims/publisher → persistence | Explicit contracts; retain atomic claim and retry semantics. |
| Notification | handler → external channel port → delivery repository | Preserve genuine external port. |
| Audit | AuditWriter port → database append-only storage | Preserve immutable write boundary. |
| Dashboard | query façade → large handler → aggregate SQL | Clarify imports/contracts; aggregates remain justified SQL. |
| ArchitectureProof | application probe → domain value | Preserve minimal module registration proof. |

Counts and locations: baseline-patterns.json. Counts are textual occurrences, not claims that each match is a defect.
