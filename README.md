# Production Tracker

A small API for tracking cabinet orders from order to installation. Each order has a few jobs
(e.g. "Kitchen wall cabinets") and every job goes through cutting, edge banding, assembly and
installation. Installers only see and update their own jobs.

Laravel 13, PostgreSQL 16, Sanctum tokens. No frontend.

## Running it

```bash
docker compose up --build
```

The first start installs Composer dependencies, creates `.env`, runs the migrations and seeds demo
data, so it takes a few minutes. After that the API is on `http://localhost:8000/api`.

| Service | What it is |
|---|---|
| `app` | PHP 8.5 FPM, runs the setup on start |
| `web` | nginx, port 8000 |
| `db` | PostgreSQL 16 (plus a separate `testing` database) |
| `queue` | queue worker |

Tests (they use the `testing` database, so the demo data stays as it is):

```bash
docker compose exec app php artisan test
```

Reset the demo data:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Demo users

The password is `password` for all of them.

| Email | Role |
|---|---|
| admin@example.com | admin |
| planner@example.com | planner |
| installer1@example.com | installer |
| installer2@example.com | installer |

Get a token and send it as `Authorization: Bearer <token>`:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "planner@example.com", "password": "password"}'
```

`api.http` has the requests below ready to run with the VS Code REST Client extension. Send
`login` first; the other requests reuse its token. The ids in it match freshly seeded data.

## API

| Method | Path | Who | Notes |
|---|---|---|---|
| POST | `/api/login` | anyone | returns a token |
| GET | `/api/user` | logged in | current user |
| GET | `/api/orders` | admin, planner | `?status=new\|in_progress\|completed&per_page=` (max 50) |
| POST | `/api/orders` | admin, planner | `customer_name`, `customer_phone`, `address` |
| GET | `/api/orders/{order}` | admin, planner | with its jobs |
| POST | `/api/orders/{order}/jobs` | admin, planner | `title`, optional `installer_id` |
| GET | `/api/jobs` | everyone | `?status=&order_id=&per_page=`; installers only get their own jobs |
| GET | `/api/jobs/{job}` | admin, planner, assigned installer | |
| PATCH | `/api/jobs/{job}/status` | see below | `{"status": "edge_banding"}` |
| GET | `/api/jobs/{job}/history` | admin, planner, assigned installer | status changes, oldest first |
| GET | `/api/reports/jobs-per-status` | admin, planner | number of jobs in each stage |

Status codes: `401` no or bad token, `403` the role can't do this, `404` not found (also for
another installer's job), `422` invalid input or a transition the workflow doesn't allow:

```json
{
    "message": "A job can't move from installed to cutting.",
    "errors": { "status": ["A job can't move from installed to cutting."] }
}
```

A job in a response carries the customer's name, phone and address, because installers can't open
orders but need to know where to go.

## Job workflow

```
pending → cutting → edge_banding → assembly → ready_for_installation → installing → installed
```

- A job moves one stage forward at a time. Going back or skipping a stage returns `422`.
  The whole workflow is defined in one place, `JobStatus::allowedNext()`.
- Admins and planners can move any job. An installer can only move their own jobs, and only into
  `installing` and `installed`; the workshop stages are recorded by planners.
- Every change is written to `job_status_changes` (who, when, from, to) in the same transaction as
  the status update. Creating a job writes the first row (`null → pending`).
- The order status is derived from its jobs: `new` while all jobs are pending, `completed` when all
  are installed, `in_progress` otherwise. Nobody sets it by hand.
- When a job reaches `ready_for_installation`, `NotifyInstallerJobReady` is queued. For now it
  writes a log line with the installer and the address: `docker compose logs queue`.

## Data model

| Table | Columns |
|---|---|
| `users` | Laravel defaults + `role` (admin, planner, installer) |
| `orders` | `customer_name`, `customer_phone`, `address`, `status` |
| `order_jobs` | `order_id` → orders, `title`, `status`, `installer_id` → users (nullable) |
| `job_status_changes` | `order_job_id` → order_jobs, `from_status` (null on creation), `to_status`, `changed_by` → users, `created_at` |

Foreign keys: deleting an order with jobs is blocked, deleting a job removes its history, deleting
an installer unassigns their jobs, and a user who appears in the history can't be deleted.

Indexes: `orders(status)`, `order_jobs(order_id)`, `order_jobs(installer_id, status)` for the
installer's job list, `order_jobs(status)`, `job_status_changes(order_job_id, created_at)` and
`job_status_changes(changed_by)`. PostgreSQL doesn't index foreign key columns by itself, so they
are added explicitly.

## Design decisions

- **`OrderJob`, not `Job`.** Laravel's queue already uses the `jobs` table and the `App\Jobs`
  namespace, so the model is `OrderJob` and the table `order_jobs`. The URLs still say `/jobs`.
- **Statuses are `varchar` + `CHECK`, backed by PHP enums.** That's what Laravel's `enum()` column
  produces on PostgreSQL. Native PostgreSQL enum types are awkward to change later.
- **One place for the status change.** `App\Actions\ChangeJobStatus` locks the job row
  (`SELECT … FOR UPDATE`), checks the transition, updates the job, writes the history and
  re-derives the order status in one transaction. Without the lock two requests could both move
  a job from the same status; with it, firing two identical requests at once gives one `200` and
  one `422`. `status` is not mass assignable on `OrderJob`, so this is the only way it changes.
- **The order row is locked while its status is re-derived**, so two jobs of the same order
  changing at the same time still leave the order with the right status.
- **The notification is dispatched after commit**, so a rolled back change doesn't notify anyone.
- **Authorization.** There are only three fixed roles, so a role is a `role` column backed by a
  PHP enum, and the rules live in Laravel Policies. If roles or permissions need to be managed at
  runtime (e.g. from an admin panel), I would move role and permission storage to
  `spatie/laravel-permission`. The ownership rule (an installer can only see and update their own
  jobs) would still stay in the Policy, because it depends on the record, not just the role.
- **404 instead of 403 for another installer's job**, so job ids of other installers don't leak.
- **Tests run on PostgreSQL**, not in-memory SQLite, because the locks and CHECK constraints
  behave differently there.
- **App config lives in `.env` only.** At first the database variables were also set in
  `docker-compose.yml`; real environment variables win over `phpunit.xml`, so the tests ran
  against the dev database and wiped it. Now compose doesn't set them.

## Report and indexes

```sql
SELECT status, count(*) AS total FROM order_jobs GROUP BY status
```

One query, no models loaded; stages without jobs are filled in with 0 in PHP.

I checked the plans with `EXPLAIN ANALYZE` on PostgreSQL 16:

- On the seed data (about 40 jobs) it's a sequential scan, as expected for a tiny table.
- With 500k jobs it takes about 25 ms with or without the `status` index (a parallel index-only
  scan with it, a parallel sequential scan without it). Counting every row means reading every
  row, and the table is narrow, so the index isn't much cheaper to read than the table.
- The `status` index pays off when filtering, e.g. `GET /api/jobs?status=cutting`. In real data
  most jobs end up `installed` and only a few are in each active stage. With 500k jobs, 94% of
  them installed, the page's `count(*)` for `cutting` took 0.3 ms with the index and 11 ms
  without it. The rows of the page are fast either way, because they're read backwards along
  the primary key.

If the report has to stay instant at a much larger scale, I'd keep per-status counters updated in
`ChangeJobStatus` (same transaction), or a materialized view refreshed on a schedule.

## Assumptions

1. The workflow is strictly linear: one stage forward, no going back and no skipping.
2. Installers only record installation (`installing`, `installed`). Cutting, edge banding and
   assembly happen in the workshop and are recorded by planners.
3. A job has at most one installer, chosen by the planner when the job is created.
4. Admin and planner have the same permissions in this scope; admin would also manage users,
   which is out of scope.
5. The order status follows its jobs and isn't set by users.

1 and 2 were confirmed by email. 3 to 5 are my own calls for this scope.

## Not done / next steps

- Updating or deleting orders and jobs, including assigning an installer after creation. Only
  create and list were asked for.
- Cancelling a job.
- Token expiry and logout (Sanctum tokens don't expire by default).
- A real notification channel (SMS or push) instead of the log line.
- An installer mobile app, ideally offline-first. Status changes would need an idempotency key, and
  the client would send the status it last saw so stale updates can be rejected.
- A customers table, installation scheduling, a workshop operator role.
- CI running the tests on every push.
- A production image: dependencies baked in instead of a bind mount, OPcache, a non-root user.
