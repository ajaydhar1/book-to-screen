# SQLite Cloud staging preparation (Milestone 4A)

For the subsequent confirmed target, narrow SQLite-Vector exception and exact
first-write approval, see [the current migration validation procedure](submissions-staging-migrations.md).
The earlier first read-only staging session is complete; no further connection
or migration is authorized by these documents.

This is a preparation plan, not permission to provision, connect, migrate,
write, deploy, or activate intake. All identifiers below are fictional examples.
No SMTP or Turnstile configuration is part of this phase.

## Architecture and isolation

Prefer a dedicated nonproduction SQLite Cloud project with a different endpoint
and a database named `b2s_submissions_staging_<label>`. The subsequently reported
`book-to-screen-staging` / `author-submissions-staging.sqlite` target is also
explicitly accepted by the offline preflight; actual endpoint/ownership is not
yet verified. See [the read-only diagnostic preparation](submissions-staging-readonly.md)
for current credential documentation, identity limitations and admin-risk gates.
Use a separate billing/
project boundary if needed to prevent credentials from reaching production.
A separate database on a production project is not accepted by this preflight.
If the service cannot provide the required project/database-scoped permissions,
stop and obtain approval for an alternative isolation design.

Create an empty database only after provisioning approval. Do not clone, import,
attach, restore, or query production records. Seed only invented properties,
`example.com` addresses and synthetic staff identities. Do not reuse the retained
20-property local demo's credentials or encryption key.

The ordinary application factory in `includes/db.php` loads production-capable
configuration. Staging tools must NOT load it, `config.php`, `config.local.php`,
or call `get_db()`. Existing submission HTTP routes intentionally accept only
outside-web-root local SQLite fixtures. Leave that boundary unchanged; first
validate Cloud through a separately approved CLI harness, not the public portal.
The normal `admin/database.php` inspector also calls this production-capable
factory and lists only existing application tables; it is not a staging inspector
or a submission backup tool. Do not use it for this exercise.

After approval, create separate short-lived credentials for:

- Read-only inspection: only the exact staging database, no DDL/DML or database
  creation/selection outside that scope.
- Migration: staging DDL/DML, temporarily issued and revoked after testing.
- Synthetic service tests: staging submission DML/read access, no provisioning
  or production access. A synthetic `users` fixture will need separately approved
  creation; migrations 1/2 do not create users.

Keep URLs and a new random 32-byte encryption key in a private secret store or
outside-web-root environment injection, never command arguments, Git, reports,
screenshots or logs. Reserve `B2S_SUBMISSIONS_STAGING_READONLY_URL`,
`B2S_SUBMISSIONS_STAGING_MIGRATION_URL`, `B2S_SUBMISSIONS_STAGING_TEST_URL` and
`B2S_SUBMISSIONS_STAGING_KEY`; only the read-only URL is now consumed by the
separate, explicit diagnostic CLI. None is wired to the application. Never fall
back to existing production or local-demo values.

## Current implementation: assumptions to verify

| Surface | Existing behavior | Required Cloud verification |
|---|---|---|
| Connection | SDK 0.2.0 opens a TLS socket and selects the URL database; construction immediately connects | Endpoint/project identity, CA trust, scoped grants and selected database; reject missing database paths |
| SDK options | URL query options can enable create, memory, insecure TLS or weaker consistency | Future harness must construct/validate an exact allowlisted URL, API-key auth, explicit database, TLS defaults, bounded timeouts; reject all other options, userinfo, fragments and nested paths |
| Bindings | Named parameters are compiled into correctly quoted SQL, not native server-prepared bindings | Unicode/quotes, literal colons/comments, NULL/int/bool, repeat bindings, rowsets, `changes()` and `last_insert_rowid()` on the same session |
| Foreign keys | Local adapter enables them; Cloud adapter does not explicitly do so | Inspect `PRAGMA foreign_keys`; require 1 before writes, or explicitly enable and recheck on each authorized write connection |
| Transactions | Adapter tracks BEGIN/COMMIT/ROLLBACK locally | Actual transactional DDL/DML, session affinity, server errors, disconnect/commit ambiguity, locking and cross-connection visibility |
| Migration 1 | Dedicated tables/indexes; versions have SHA-256 checksums | Constraints, UPSERT, cascades and exact schema |
| Migration 2 | Rebuilds parent/token/event tables, copies IDs/ciphertext, renames, recreates indexes and backfills roles/genres | ALTER rename references, FK behavior with enforcement ON, transactional rollback and preserved AUTOINCREMENT history |
| Reviewer inbox | Partial expression index explicitly selected for default inbox | Index support, EXPLAIN plan, bound filters, rowset size and performance |
| Backups | `scripts/backup-database.php` backs up only local `data/adaptations.sqlite`, retains 30 days | No existing submission Cloud backup/restore automation; obtain provider snapshot/restore procedures and retention guarantees |

Do not disable foreign keys to force migration success. The SDK's create flag
defaults false, but arbitrary URL options can override it; a connection is not
inherently read-only. Credential permissions, not a client label or `query_only`
alone, must enforce read-only inspection. Current Cloud error messages are
generic, and `changes()` can return zero on an unexpected rowset: validate exact
response shapes and failure handling before approving integration.

## Offline preflight

Save this NONSECRET identity manifest outside the repository. Replace every
fictional identifier with operator-confirmed inventory only after approval.

```json
{
  "environment": "staging",
  "project_id": "synthetic-staging-project",
  "hostname": "staging.example.com",
  "port": 8860,
  "database": "b2s_submissions_staging_synthetic",
  "production_project_id": "synthetic-production-project",
  "production_hostname": "production.example.com",
  "production_database": "adaptations.sqlite",
  "synthetic_only": true
}
```

```powershell
php -d display_errors=0 scripts\prepare-submissions-staging.php 'C:\private-staging\identity.json'
php -d display_errors=0 tests\submissions-staging-test.php
```

The script reads only that JSON and the migration definitions. It does not load
application configuration, instantiate any database, resolve DNS, read credentials,
generate keys, or run SQL. It rejects missing identities, production overlap,
unsafe names and secret fields; reports migration checksums/counts.
Success validates claims ONLY: it does not prove project membership, credential
scope, effective database selection or all production aliases. Cross-check the
complete production inventory and Cloud control panel before any connection.
Do not use this output as an approval token or live-target safety certificate.

## Approval gates and proposed live validation

### Gate 1: provision and identify (separate approval)

Record exact project ID, endpoint/port, database name, owner, allowed credential
scopes and all production endpoints/aliases. Verify a separate project and no
production grants using the provider control panel. Provision an empty database
and scoped inspection credential. No production exports or copied records.

### Gate 2: read-only connection inspection (separate approval)

The prepared read-only diagnostic CLI requires the exact approved manifest plus an explicit
read-only URL from its dedicated environment variable. Fail closed on mismatch,
missing secrets, unsafe SDK options or uncertain identity; never retry on another
target. Do not print URLs, API keys or SDK error bodies.

Before SQL, validate that the URL selects the exact allowlisted host/port/database
and uses only the permitted authentication fields. Timeouts are fixed in code,
not supplied as SDK URL options. Connect with the
provider-enforced read-only credential where available. A separately acknowledged
staging-admin exception is documented in the read-only guide; it does not establish
restricted permissions. Reconcile the effective project/database
with provider-supported identity evidence (confirm the supported commands first;
do not guess a SQLite `PRAGMA database_list` filename proves a Cloud project).
The current narrower diagnostic inspects SQLite version/settings and schema, with
documented database/user identity commands; broader compile/integrity/grant
inspection remains separate preparation work. Expected database
is empty: any unexplained tables/data, public leads/adaptations or non-synthetic
records are a STOP condition, not something to delete.

Confirm write denial from scoped grants/control-panel evidence. Any empirical
attempted-write permission probe itself requires Gate 3 approval. No migration
calls, even for checksums: `submission_migrate()` always creates its version table
before checking existing versions.

### Gate 3: migrations and synthetic writes (separate approval)

Use a new approved write credential; repeat identity inspection on each connection,
require/enforce foreign keys, bounded timeouts and default strong consistency.
Stop other writers for schema work. Permit only the dedicated submission tables,
indexes, version ledger and separately approved synthetic users. No public tables.
Take a recoverable staging snapshot before upgrading a populated fixture.

Run these on two separately approved disposable databases/targets:

1. **Empty install:** apply migrations 1/2; assert exact version/checksum ledger,
   tables, columns, CHECK/UNIQUE/FK constraints and indexes; repeat must be a no-op.
   Inspect FK integrity and the inbox expression index plan.
2. **Upgrade:** apply only v1, seed synthetic submissions using a new staging key,
   token hashes and encrypted audit notes. Capture IDs, counts, ciphertext and
   token timestamps without logging contents. Apply v2 with the SAME key; assert
   exact preservation, role/genre backfill, intact cascades and new evaluation
   storage. Repeat v2 safely. Test future event IDs remain unique/increasing.
3. **Rollback:** on separate disposable fixtures, test missing/wrong keys and
   corrupt synthetic payloads, constraint errors, a deliberately interrupted
   migration and checksum mismatch. Verify no partial v2/temporary tables and
   intact v1 records. Failure in v2 leaves committed v1: versions are individually
   transactional, not one transaction for the whole run. The initial ledger
   creation is outside these transactions. There is no down migration; post-commit
   recovery is a tested snapshot restore, not an assumed rollback.
4. **Services:** synthetic submission validation/idempotency, encrypted contact/
   rights/notes/evaluations, hashed token storage, mocked delivery failures,
   scanner-safe confirmation semantics, expiry/reuse/resends, status/audit/revision
   updates, duplicate/quarantine filters and pagination. Use an in-memory mail
   mock; loopback service URLs keep this CLI exercise independent of SMTP/
   Turnstile infrastructure. This is not HTTP/public-intake validation.
5. **Concurrency:** independent Cloud connections/processes submit the same
   idempotency key simultaneously; assert one row and consistent receipts.
   Race verification/resends/review revisions; check single-use tokens and stale
   rejection. Exercise rate buckets and lock contention. Do not assume the local
   busy timeout or local worker tests cover Cloud; report transport/locking failures
   rather than blindly retrying an ambiguous commit.
6. **Privacy/performance/recovery:** verify only allowed synthetic tables exist,
   no plaintext contact/notes/evaluations or raw tokens in stored payloads/logs,
   default inbox isolation, 2,000+ synthetic properties with bounded page results,
   measured query timings and no truncated rowsets. Restore a snapshot into a
   separate approved staging target and decrypt with the preserved original key.
   Authorize cleanup explicitly; revoke temporary credentials and record sanitized
   results. Never clear a target merely because a test fails.

## Test portability and code needed before live testing

- `tests/submissions-test.php` and `tests/submissions-review-test.php` run unchanged
  OFFLINE only: they create in-memory SQLite/offline Cloud instances, synthetic
  tables and expect some PDO exceptions. They cannot be pointed at a real Cloud
  URL unchanged. Local concurrency workers are SQLite-specific.
- The new staging preparation tests, mocked anti-bot tests, ISBN mocked tests and
  JavaScript tests also stay unchanged/offline; they do not validate Cloud.
- `tests/submissions-http-test.php` and fixture/setup/migrate/cleanup tools are
  intentionally loopback/local-file-only and destructive to their own fixtures.
  Do not repurpose them for Cloud.
- The Gate 2 CLI connector is prepared and mock-tested; see the read-only guide
  for its narrower command set, operator identity attestation and unresolved
  provider grant limitations. The first separately approved read-only session is
  complete; no additional connection is authorized by this preparation.
  The empty-install Gate 3 harness is now implemented and offline-tested; see
  the current migration guide for its exact write approval and limitations.
  Before populated/fault testing, implement a separately gated synthetic harness
  reusing `Database`, migration definitions and service helpers, asserting Cloud
  failure behavior instead of hard-coded PDO exceptions. Require snapshot evidence,
  explicit target allowlists and operation-specific approvals; no automatic
  migrations on connection. Run offline guard/failure tests first.
- Any required Cloud adapter FK/rowset/error hardening must be approved and
  regression-tested before write validation. Do not relax constraints or alter
  existing version checksums to accommodate a server incompatibility.

## Encryption and backup considerations

The 32-byte key protects AES-256-GCM payloads and also derives HMAC email/property/
idempotency/receipt/rate identifiers. A valid-looking replacement key is NOT an
upgrade key. Preserve the original staging key for every v1-to-v2 upgrade and
restore; losing it makes private data unrecoverable. No key-version/rotation
workflow currently exists. Rotation needs its own design covering re-encryption
and HMAC identities, not just an environment-variable change.

Bibliographic JSON, titles, roles, genre projections, status and timestamps are
not application-encrypted; provider access and backup encryption still matter.
Store encrypted snapshots in access-restricted nonproduction storage, keys
separately with tested recovery. Approve provider backup/PITR availability,
retention/deletion (including expired tokens and deleted records), restore
isolation, RPO/RTO, access logs and cost. Never restore staging over production
or assume the local adaptation backup script covers Cloud submissions.

## Stop conditions / approvals still needed

The reported staging target and first read-only identity/FK checks are recorded
in the current migration procedure. Scoped grants, snapshot/restore evidence and
live transactional migration behavior remain unvalidated.
Additional connections, migration/test writes and recovery each need
separate authorization. Staging readiness does not authorize deployment, public
intake, staff-auth activation, SMTP/Turnstile setup or commits.
