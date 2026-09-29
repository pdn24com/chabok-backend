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
