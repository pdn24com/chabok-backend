# Task API

The task inbox — «کارها» in the prototype — is five endpoints over `crm_tasks`,
`crm_task_assignment_events` and `crm_activities`. All of them require bearer
authentication, a completed password change and the `Customer` entitlement.
Reading needs `crm.task.view` at `TENANT` scope; every write needs
`crm.task.manage` at the same scope. The authorization catalog grants both to
`hq_admin`.

| Operation | Route |
| --- | --- |
| The inbox | `GET /api/v1/tasks` |
| Raise a task | `POST /api/v1/tasks` |
| Record what happened | `POST /api/v1/tasks/{taskId}/actions` |
| Move it to another owner | `POST /api/v1/tasks/{taskId}/assignments` |
| Finish it | `POST /api/v1/tasks/{taskId}/complete` |
| Record an interaction on its own | `POST /api/v1/crm/activities` |

## The inbox

`GET /api/v1/tasks?scope=mine|accessible&bucket=…&q=…` returns the counters and
the rows of the selected slice:

```json
{
  "summary": { "open": 5, "waiting_customer": 1, "today": 1, "overdue": 2, "internal": 1 },
  "items": [
    {
      "task_id": "44",
      "title": "پیگیری پیشنهاد",
      "status": "OPEN",
      "priority": "HIGH",
      "due_at": "2026-10-02T06:30:00.000000Z",
      "remind_at": null,
      "assignee": { "user_id": "12", "display_name": "سارا احمدی" },
      "customer": { "customer_id": "11", "display_name": "پارس‌گستر آریا" },
      "opportunity": null,
      "is_overdue": false
    }
  ]
}
```

`scope` defaults to `mine`, the actor's own queue; `accessible` reads every task
of the tenant. `bucket` is one of `overdue`, `today`, `future`, `undated`,
`waiting` and `closed`; with none given the inbox lists every unfinished task.
`q` matches the title. Rows are ordered by deadline with the undated ones last,
whatever the bucket.

`is_overdue` is computed, never stored: a deadline already past on a task nobody
finished or called off. The `overdue` and `today` buckets meet at midnight, so a
deadline earlier today already counts as late. The counters count exactly the
rows their matching bucket lists, and `internal` counts the work attached to no
customer and no opportunity.

The list is returned whole rather than by the page, as the prototype contract
describes it. A tenant whose inbox grows past a screenful will want paging here;
the contract does not define it yet.

## Raising, moving and finishing

`POST /api/v1/tasks` takes `title`, `priority`, `description`, `due_at`,
`remind_at`, `customer_id`, `opportunity_id`, `assignee_id`, `team_context_id`
and `operation_key`, and returns `201` with the task and the `CREATE` row that
opens its assignment history. Every task has that history from the start, so the
queue always reads back as a sequence of moves rather than an owner with no past.

`POST /api/v1/tasks/{id}/assignments` writes one more row of that history and
moves the task. `event_type` is `REFER`, `CLAIM` or `REASSIGN`; handing work to
somebody else is explained with a `reason`, while claiming it for yourself is
not. `expected_assignee_id` is the owner the caller believed the task had — a
mismatch returns `409`, because somebody moved it first. A repeated
`operation_key` replays the first result instead of moving the task twice.

`POST /api/v1/tasks/{id}/complete` takes `completion_result` and returns the
finished task. `completed_at` comes from the server clock, never the client, and
the live reminder is cleared with it.

`POST /api/v1/tasks/{id}/actions` records what happened and what that does to the
task in one write: an `activity` block and an optional `task` block. An action
may simply be logged. Finishing through it needs `task.completion_result`, the
same as the dedicated endpoint.

## Rules the endpoints enforce

- A reminder cannot come after the deadline, and a reminder needs somebody to
  remind — the database refuses both, so the API refuses them first with a named
  field rather than a failed statement.
- An opportunity task names the customer that owns the opportunity. The database
  trigger refuses a disagreeing pair; the API proves the pair before writing.
- A completed or cancelled task is neither moved nor acted on further.
- Each kind of interaction carries its own detail and nothing else: a call has an
  outcome and a duration, a meeting has a mode, a place and a link, a document
  action has a version. The columns behind the unused fields stay null by design.
- A task without an owner is exactly that. `to_user_id: null` leaves the work
  unowned; it is **not** a team queue. The team on a referral is history — the
  context the work moved through — and never appears on the task row. This
  settles the prototype's open question O12.

## Recording an interaction on its own

`POST /api/v1/crm/activities` (`crm.activity.manage`, `TENANT` scope, `Customer`
entitlement) records a call, message, meeting, document dispatch or note that is
not the by-product of a task action. It answers **201** with the interaction:
`activity_id`, `type`, `occurred_at`, `customer_id`, `opportunity_id`,
`task_id`, `contact_customer_id`, `body`, `result`, `direction`, `channel`,
`contact_value`, `duration_minutes`, `call_outcome`, `meeting_mode`,
`meeting_url`, `location`, `document_version_id`, `participants`
(`[{user_id, minutes}]`), `created_by`, `created_at`. IDs are strings.

The route carries `idempotent:crm-activities.create`: an interaction has no
natural key, so a retry with the same `Idempotency-Key` header (16-128
characters, required) replays the first answer instead of recording twice.

Rules, each refused with a 422 that names the offending field. They mirror the
CHECKs and triggers of `crm_activities`, which SQLite does not carry:

- `type` is one of `CALL`, `MESSAGE`, `MEETING`, `DOCUMENT_SENT`, `NOTE`.
  `REFERRAL` is refused (`type`): only an assignment event produces one.
- Detail belongs to one type: `call_outcome` to `CALL`; `direction` to `CALL`
  and `MESSAGE`; `duration_minutes` to `CALL` and `MEETING`; `meeting_mode`,
  `location`, `meeting_url` to `MEETING`; `document_version_id` to
  `DOCUMENT_SENT`; `participants` to `MEETING`.
- Each type is complete: `CALL` needs `direction`, `contact_value` and
  `call_outcome`; `MESSAGE` needs `direction`, `channel` and `contact_value`;
  `MEETING` needs `meeting_mode` and a `location` or a `meeting_url`; `NOTE`
  needs `body`.
- `contact_customer_id` must be a PERSON of the tenant, never a company.
- It must be filed against at least one of `customer_id`, `opportunity_id`,
  `task_id`. The pieces must agree: an opportunity belongs to the customer
  given (or to the customer of the task), a task is filed against the same
  customer and opportunity. What the request leaves out is taken from the task,
  then from the opportunity. A customer, opportunity or task of another tenant
  is refused exactly like an unknown one (422 on its field, as elsewhere in
  this module for ids in the body).
- `participants` are active users of the tenant, each named once, with
  `minutes >= 0`; they are written to `crm_activity_participants` in the same
  transaction as the interaction.
- `occurred_at` accepts any date string with an optional offset and is stored in
  UTC. Like the task action, it has no upper bound.

The type and detail rules live in `ActivityValidator`, shared with
`POST /api/v1/tasks/{taskId}/actions`, which keeps its request and response
shape. The shared rules are the database's, so a `MEETING` may carry a
duration inside an action too, and a `direction` on a note is refused there
with `activity.direction`.

The person and opportunity-owner lookups come through
`ActivityFilingDirectoryInterface`. Until `Customer` and `CrmOpportunitie`
provide adapters, `DatabaseActivityFilingDirectory` answers them with two
tenant-scoped reads; rebind the port to move them to the owning modules.

## Module boundaries

`CrmTask` owns no customer, opportunity or team table, and `Customer` already
reads `CrmTask` for the open work on its detail page. Rather than point the
dependency back and make a cycle, `CrmTask` declares three ports under
`Application/Ports` — `CustomerDirectoryInterface`, `OpportunityDirectoryInterface`
and `TeamDirectoryInterface` — and the modules that own those tables fill them
from `Infrastructure/Adapters`. The ports also carry the bulk name lookups the
inbox needs, so a page of rows is named in one query per module instead of one
per row. Only the owner comes from a relation, because `Iam` is imported
directly, as every module that records an owner does.

`CrmTeam` gained its first `src/` for this: a `TeamRecord`, a repository that
answers whether a team is active, and the adapter behind the port.

Run the rules test, which needs no database, with:

```bash
vendor/bin/phpunit tests/Unit/TaskRulesTest.php
```
