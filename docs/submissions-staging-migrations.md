# Controlled SQLite Cloud migration validation: procedure only

The dedicated migration runner is implemented but NOT authorized to run live.
No connections,
migrations, record inserts, settings changes or recovery operations were performed
while preparing it. Local-only migration/setup scripts remain local-only.

## Target and starting evidence

- Project: `book-to-screen-staging`, operator-confirmed ID `cutaojpqvz`.
- Endpoint: `cutaojpqvz.g2.sqlite.cloud:8860`.
- Database: `author-submissions-staging.sqlite`.
- Nonsecret production inventory has a different project ID, endpoint and database.
  Reconfirm all aliases before every approved operation; never load production config.
- The first approved read-only session confirmed selected database/user, FK ON,
  SQLite 3.54.0 and WAL. It found an empty `_sqliteai_vector` metadata table and
  `sqlite_autoindex__sqliteai_vector_1`. That session is closed.
- The refined diagnostic is mock-tested only; the actual table's complete SQL
  definition has not been matched by the refined checker in a new live session.
  The next separately approved operation must perform that check first.

Use the existing external identity manifest and private local configuration.
Do not copy the URL, API key or encryption key into this plan or Git.
The project's identity rests on the independently confirmed control-panel
endpoint mapping plus TLS hostname, not a globally unique SQL project-ID proof.
Admin credentials are write-capable; use only the acknowledged staging credential.

## Immutable migration evidence

Computed from `submission_migrations()` without executing SQL:

| Version | Statements | SHA-256 of statements joined by LF |
|---------|------------|-----------------------------------|
| 1 | 10 | `bab034d1f5038391a10043aac96cb67ddb453cfb75f0f6c5af71078c3f007269` |
| 2 | 24 | `12232008e1ebec93807896aa89e7b652473e59063cbe54b63caee6a16855e3ef` |

Before writes, recompute these from the reviewed source and compare with the
approved values. For an upgrade, read the existing version ledger and compare
every stored checksum first; unknown versions or mismatches STOP.
Never modify v1/v2/checksums to make a failed compatibility test pass.
Calling `submission_migrate()` is NOT a read-only version inspection: it creates
the ledger before checking versions.

Both versions are individually transactional; v1 can remain committed when v2
fails. Ledger creation is outside those transactions. A successful COMMIT cannot
be undone by ROLLBACK; no down migration exists. Do not retry an ambiguous
commit/disconnect automatically: inspect the same approved target read-only,
then obtain a recovery decision.

## A. First controlled migration: EMPTY installation only

The dedicated `scripts/migrate-submissions-staging.php` harness reuses the shared
staging session/inspection helpers, actual SQLite Cloud adapter and immutable
migration functions. It never invokes the local-file migration scripts or changes
the local HTTP/intake boundary. The Cloud adapter can now wrap an already-connected
SDK client, so identity checks and migration SQL share ONE connection rather than
constructing a second production-capable connection.

The harness pins the project ID/endpoint/port/database and acknowledged admin
identity above. Migration checksum values are pinned separately from the migration
definitions. Both CLI `--apply-empty-v1-v2` and the exact migration-specific
environment approval value are required BEFORE loading private credentials.
The earlier read-only approval value is not accepted.

Offline plan (no private config read, no socket, no SQL):

```powershell
php -d display_errors=0 scripts\migrate-submissions-staging.php `
  'C:\path-to-your-external\staging-diagnostic-identity.json' --offline
php -d display_errors=0 tests\submissions-staging-migration-test.php
```

The post-install validator compares all expected table/index definitions,
automatic-index ownership and SQLite sequence schema against the approved
checksum-protected declarations. This covers columns, types, defaults, primary/
unique/check/foreign-key constraints and index expressions without inserting
probe records. It checks the version ledger/checksums/timestamps, all five
submission data tables for zero rows, strict response shapes and
`PRAGMA foreign_key_check`. A full harness rerun is allowed only for an exact
already-installed empty v2 schema; partial v1, modified schema/checksums and any
data are STOP conditions. The pre-migration inspector alone cannot certify
post-migration schema, so the harness uses its separate exact-schema validator.

Runtime constraint violations and live locking/rollback behavior are still
separate populated/fault tests; successful schema declaration checks do not
replace them.

Offline validation results: 96 controlled migration checks, 137 mocked diagnostic
checks, 37 preparation checks and 18 private-config checks passed. The existing
191 submission/database checks also passed after the adapter/session change.
Changed PHP syntax and Git whitespace checks passed. Migration tests use the
actual adapter with an in-memory SQLite transport, not Cloud. They cover initial/
whole-harness repeats, metadata preservation, bad identity/FK/schema/ledger/data,
permission/response errors, active-version rollback, earlier-version persistence,
ambiguous commit and postcommit verification failures. No property submissions
were inserted by the migration harness tests. The real identity manifest also
passed the migration CLI's OFFLINE plan validation without reading credentials.

The harness must:

1. Validate the same manifest/URL, operator identity attestation and exact
   operation approval before opening a socket. No generic app factory, production
   config, credential output or fallback. Reconfirm database/user and attachments
   on the actual migration connection, not just a previous diagnostic connection.
2. Require the narrow known metadata pair with the exact source-defined table
   columns/types/PK and automatic-index metadata, with no records. Any other
   initial schema/data, missing/altered pair or ambiguous response STOP.
   A completely schema-empty target may also pass.
3. Require `PRAGMA foreign_keys` = 1 on that connection, strong default consistency,
   validated response shapes and timeouts. Do not silently set/disable FK or other
   settings. If FK is OFF, stop for explicit remediation approval.
4. Stop other staging writers. Capture a sanitized baseline of schema signatures
   and metadata-table record absence. Recheck migration checksums.
5. Apply migration 1, inspect the ledger and constraints, then apply migration 2
   with its table rebuilds. Use a new private staging key for future tests; no key
   is needed to backfill an empty database, but never reuse the demo/production key.
   Initial install does not create staff users or seed properties.
6. Assert ledger versions exactly [1,2], exact checksums, expected tables/columns,
   CHECK/UNIQUE/FK declarations, indexes (including the priority expression index),
   no orphan records via `PRAGMA foreign_key_check`, and zero submission/token/
   event/genre/rate rows. Read-only inspection must not decrypt or log payloads.
7. Repeat the migration runner; expect no applied versions, unchanged ledger,
   unchanged counts/schema. This runner may begin/commit transactions and issue
   its ledger `CREATE TABLE IF NOT EXISTS`, even on a no-op; include that in approval.
8. Compare the SQLite-Vector table definition, automatic-index metadata and
   record absence to baseline. Preserve both objects; never rebuild, drop, seed,
   clear or alter them. The post-install validator must allow only the exact
   submission schema plus the known metadata pair and necessary SQLite-generated
   indexes/sequence objects verified against their owning submission definitions.
   Do not reuse the pre-migration no-application-schema check as a post-install test.
9. Close the connection and produce sanitized results. Stop before submissions,
   users, probes, mail, public intake or any further operation.

The migration definitions necessarily include table-copy INSERT SELECT, drops
of old submission tables and ALTER renames in v2; with an empty initial database
these copy zero application rows. Approval for this operation must explicitly
cover those migration DDL/DML statements and the version-ledger records.
It does not authorize arbitrary DROP/DELETE or cleanup.

## B. Populated v1-to-v2 upgrade (separate target and approval)

The existing target will be v2 after phase A; it cannot be downgraded safely.
Use a separately approved disposable staging database/project with its own exact
identity allowlist. Provisioning or resetting is NOT authorized here.

Before seeding:

- Approve and test recoverable export/snapshot and isolated restore, or explicitly
  accept a disposable synthetic-only loss-and-recreate strategy as described below.
- Create a new random 32-byte staging key outside Git; retain the SAME original
  key for every v1 upgrade/restore. AES-GCM data and HMAC identifiers depend on it.
  Key format validation alone does not establish key correctness. No automatic
  key rotation exists.
- Approve exact synthetic users/properties and fault-probe objects. Use invented
  titles/authors, `example.com` addresses, mock mail only and no external APIs.
  Never copy production records or the retained demo key/credentials.

Apply v1 only; seed synthetic pending/verified/quarantined declarations, genres,
consents, token hashes/expiry/consumption and encrypted notes/audit IDs. Capture
counts, IDs, ciphertext fingerprints and timestamps privately, not plaintext
payloads/raw verification links. Take the validated recoverable baseline.
Apply v2 with the original key; assert exact IDs/ciphertext/tokens/audit preservation,
role/genre backfill, FK integrity and increasing future event IDs. Repeat safely.

Only after v2, use approved synthetic admin identities to write/edit/clear
qualitative evaluations and append notes. Assert authenticated encryption, correct
decryption, encrypted evaluation snapshots/audit attribution, stale-update rejection,
and no public-table interaction. Review/email services use an in-memory mock mailer.
The mock/loopback service base URL is test infrastructure, not public activation.

## C. Transaction, rollback and concurrency validation (separate approval)

Run each destructive/fault case on its own disposable baseline:

- BEGIN/DML visibility, rollback and commit across independent connections.
- Transactional CREATE/ALTER/DROP rollback using explicitly approved probe objects;
  verify no artifacts after rollback. Never use vector metadata as a probe.
- Missing/wrong original key and corrupt synthetic ciphertext during v2: abort,
  retain v1 ledger/rows/tokens/audit, no `_v2` remnants. Check FK integrity afterward.
- Constraint failures, CHECK/UNIQUE violations, orphan-token rejection and
  cascades on an explicitly expendable synthetic submission.
- Intentional migration interruption and checksum mismatch: verify expected
  rollback/stop behavior. Version boundaries and ambiguous commits must be
  diagnosed, not automatically “cleaned up.”
- Concurrent Cloud writers sharing an idempotency key: exactly one property and
  consistent receipts. Race verification/resends and review revisions: single-use
  tokens, resend caps and stale-review rejection. Check rate buckets under contention.
  Offline adapters/local SQLite worker tests are not proof of Cloud locking behavior.

Finish with exact schema/count/checksum/FK checks and metadata preservation.
Any cleanup, restore, reset or credential revocation is an explicit named operation,
not an automatic response to a failed test.

## Free plan backup/recovery limitations

Official sources reviewed during preparation:

- [SQLite Cloud pricing](https://www.sqlite.ai/pricing) lists Free with 512 MB storage
  and 20 connections; its comparison lists snapshots as **None** for Free.
  Daily snapshots are listed for Dev.
- [Backup documentation](https://docs.sqlitecloud.io/docs/backups) describes backups
  for Dev/Pro/Startup projects, not Free. It describes full snapshots/incremental
  recovery and warns that restoration overwrites the existing database.
  Plan names differ between pages; confirm actual account entitlements with support.

Do NOT assume a UI label “Development” on a Free project supplies paid Dev
snapshots/PITR, or that a retention entry implies recoverable Free snapshots.
No dashboard/support entitlement or restore mechanism has been verified here.
The local adaptation backup script does not back up Cloud submissions.

Before ANY populated migration test, choose and explicitly approve one route:

1. Provider-supported consistent database export/backup for this plan, encrypted
   and access-restricted storage outside Git, plus a successful restore into a
   separate approved nonproduction target and original-key decryption checks.
   Confirm support/permissions for export; do not assume the PHP SDK has a
   complete backup/download implementation. Never copy a live WAL file casually.
2. Upgrade this nonproduction project to a plan with confirmed backup capability,
   under separate billing approval; verify retention and test isolated restoration
   without overwriting the working target.
3. If recovery is unavailable, explicitly approve disposable synthetic-only
   loss/recreation, retain a reproducible synthetic seed and original key securely,
   and use a separately approved expendable target. This is NOT backup/PITR and
   does not permit populated retained-data migration tests or any production use.

Record expected loss tolerance, RPO/RTO, key recovery, encryption/access controls,
retention/deletion and named restore destination. For an empty phase-A install,
there are no submission records to recover, but record the vector baseline and
require a manual recovery decision after failure; do not promise rollback after
a committed migration or automatically delete provider objects.

## Exact approval request for phase A

After review of the mock-tested dedicated migration harness, request:

> I authorize one controlled empty submission-schema installation on project
> book-to-screen-staging (ID cutaojpqvz), endpoint cutaojpqvz.g2.sqlite.cloud:8860,
> database author-submissions-staging.sqlite, using the acknowledged staging-admin
> credential. Perform exact identity/schema/empty-vector/FK/checksum prechecks,
> apply only migration versions 1 and 2 with the checksums documented above,
> verify schema/ledger/indexes/FK integrity and zero application rows, then repeat
> for no-op verification and disconnect. Preserve _sqliteai_vector and its exact
> automatic index unchanged. No seeded submissions/users, fault probes, settings
> changes, unrelated cleanup, production access, public activation or other
> infrastructure operations are authorized. Stop on any discrepancy or failure.

The earlier read-only approval does not authorize this write operation. Populated
upgrade, synthetic records/evaluations, fault/concurrency tests and backup/restore
each require their separately scoped approvals and prerequisite targets.

### Manual live invocation ONLY AFTER that approval

Use the existing external manifest path; no credentials appear in this command.
The local private config must still have an independently confirmed matching
hostname. Stop all other staging writers before invocation.

```powershell
$env:B2S_SUBMISSIONS_STAGING_MIGRATION_APPROVAL = 'cutaojpqvz/author-submissions-staging.sqlite/empty-install-v1-v2'
try {
    php -d display_errors=0 scripts\migrate-submissions-staging.php `
      'C:\Users\ajayd\.copilot\session-state\8c1c5516-71ef-4683-a5d4-7a177e19baaf\files\staging-diagnostic-identity.json' `
      --apply-empty-v1-v2
}
finally {
    Remove-Item Env:\B2S_SUBMISSIONS_STAGING_MIGRATION_APPROVAL
}
```

The CLI exits 0 only after install/repeat/schema/ledger/count/FK checks succeed;
otherwise it exits 1 with a sanitized stop message. It never prints raw SQL,
provider errors, private config or credentials. It does not erase failed schemas
or undo already committed versions. A failure/disconnect can leave v1 or v2
committed: obtain a separately approved read-only recovery assessment, never
blindly retry. The approval environment value is an operator safeguard, not proof
that authorization actually exists.
