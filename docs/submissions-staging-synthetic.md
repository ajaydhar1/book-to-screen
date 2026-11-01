# Three synthetic Cloud properties: preparation only

The empty Cloud installation succeeded previously. This harness was implemented/
tested OFFLINE only. No further Cloud connection, submission writes, settings
changes, provisioning, mail, deployment or public activation occurred.

## Fixed scope and reviewer source

`scripts/test-submissions-staging.php` requires the existing external identity
manifest and private local connection config. It pins project `cutaojpqvz`
(`book-to-screen-staging`), `cutaojpqvz.g2.sqlite.cloud:8860` and
`author-submissions-staging.sqlite`. It accepts only the exact synthetic approval,
never the read-only or empty-install approval, and no production/environment
connection fallback.

The submitted forms are defined in `includes/submissions/staging-synthetic.php`:

| Fixture | Fictional property | Submitter | Final status |
|---------|-------------------|-----------|--------------|
| 1 | [SYNTHETIC] The Lanterns of Tomorrow | Invented author Mira Quill; staging-mira@example.com | research_candidate |
| 2 | [SYNTHETIC] The Orchard of Quiet Clocks | Invented agent Ellis Reed; staging-ellis@example.com | needs_information |
| 3 | [SYNTHETIC] The Lanterns of Tomorrow | Invented scout Tessa Finch; staging-tessa@example.com | duplicate |

These are three submission records representing two fictional properties; the
second representative deliberately exercises duplicate-property warnings.
All names, pitches, declarations and notes are invented; no real ISBN/provider
lookup is used. All rights assertions explicitly remain self-declared.

Submission schema has no `users` table. The user approved an isolated in-memory
SQLite fictional-reviewer fixture to avoid adding Cloud schema. A CLI-only
`Database` wrapper sends the existing administrator-read query to that fixture.
For activity attribution it substitutes an equivalent read-only derived reviewer
row into the existing activity join. All submission, evaluation, note, audit and
rate/token operations run against the actual Cloud adapter on the SAME verified
connection. No Cloud user/account is created and no HTTP authentication is changed.
This exercises existing service authorization checks, NOT production staff-login
integration. Actor ID 900001 is an explicitly reserved fictional fixture identity.

## Expected changes and checks

The harness uses `SubmissionService::submit`, `verify`, `review`, `detail` and queue
methods. No direct property/token/evaluation inserts are used in the harness.
It uses ONLY `StagingSyntheticMailer`, storing links in memory with allowlisted
fictional emails. No PHPMailer/SMTP/network mail, mailbox file or external API.

Expected final Cloud data changes:

- 3 encrypted submissions, 3 single-use hashed verification tokens (consumed).
- 3 genre projection rows, 15 audit events including encrypted notes/evaluation
  snapshots; all three evaluations are encrypted.
- 9 HMAC rate buckets with two attempts each, reflecting deliberate idempotency
  and token-reuse checks. The migration ledger and schema are unchanged.
- Inbox contains 2 actionable verified records; the duplicate stays outside it.

Each new record is resubmitted using its deterministic test request key to assert
same receipt/no additional row/no extra mail. Its mock token is verified once and
reuse must fail. The initial verified queue must contain all 3 before review.
Final checks compare normalized payloads/private decryption/HMAC projections,
statuses, revisions, exact evaluations/notes, audit types/attribution, token
lifetimes/consumption, duplicates/genres and rate buckets.

Prechecks validate selected database/user/attachments, FK ON, query_only OFF,
exact migrated schema/ledger/checksums and exact EMPTY SQLite-Vector pair.
The harness does not run migrations or set PRAGMAs. Unknown schema/data stops.
Postchecks require FK integrity and unchanged schema, ledger and vector metadata.

Repeat behavior: a fully completed matching fixture set is verified READ-ONLY,
with no resubmits, mail, reviews or rate changes. Any partial, altered, foreign
record/key/count/state is a STOP condition, not an automatic resume or deletion.
The “repeat” approval is still required before opening that inspection connection.
This intentional strictness means an interrupted run needs a separately approved
recovery assessment rather than a guessed continuation.

## Encryption and recovery prerequisites

Before ANY live run:

1. Stop all other staging writers. Confirm the exact target, disposable
   synthetic-only status and that no production data has been copied.
2. Generate one NEW random 32-byte hexadecimal staging key using an approved
   local secret-management method. Preserve it in access-restricted storage
   outside Git/web root; privately inject `B2S_SUBMISSIONS_STAGING_KEY`.
   Never reuse production/demo keys or paste the key into chat/shell history.
   The harness does not generate, log or silently replace the key.
3. Confirm a backup/isolated restore route or explicitly accept disposable
   synthetic-only loss/recreation for THIS populated test. The previous empty
   installation risk acceptance is not automatically approval for populated data.
   See [Free-plan recovery limitations](submissions-staging-migrations.md).
   No automatic Free snapshots/PITR are assumed; local adaptation backups do not
   protect Cloud data. Retain the original key and reproducible fictional inputs.
4. Obtain the separate exact approval below. Retain the key after clearing its
   process environment; losing it prevents decryption and repeat recognition.

Services commit multiple transactions plus rate updates. Failure can leave
partial encrypted submissions or consumed tokens. Mock links are memory-only:
interruption can lose a still-unverified raw token; only its hash is stored.
There is no all-three transaction, no automatic resend/retry/reset/cleanup.
An ambiguous commit/disconnect is a STOP. Recovery/resend/deletion/recreation,
backup/restore or additional test submissions need separate explicit approval.

## Offline tests and manual commands

```powershell
php -d display_errors=0 tests\submissions-staging-synthetic-test.php
php -d display_errors=0 scripts\test-submissions-staging.php `
  'C:\Users\ajayd\.copilot\session-state\8c1c5516-71ef-4683-a5d4-7a177e19baaf\files\staging-diagnostic-identity.json' --offline
```

Offline mode reads only the NONSECRET manifest and prints the fixed fictional
forms/target. It does not read the private connection file/key or open a socket.
Tests use in-memory SQL/mock Cloud connections, local fictional reviewer identity
and mock mail; no Cloud calls.
They cover all three properties, encrypted payload/evaluation/audit checks,
mock token consumption/reuse, duplicate quarantine, unchanged schema/vector
metadata, write-free repeat, missing approval/key, wrong key, partial/altered
fixture state, identity/FK/schema failures and ambiguous commits.

ONLY after new approval and private key injection:

```powershell
$env:B2S_SUBMISSIONS_STAGING_SYNTHETIC_APPROVAL = 'cutaojpqvz/author-submissions-staging.sqlite/three-synthetic-v1'
try {
    php -d display_errors=0 scripts\test-submissions-staging.php `
      'C:\Users\ajayd\.copilot\session-state\8c1c5516-71ef-4683-a5d4-7a177e19baaf\files\staging-diagnostic-identity.json' --test-three-synthetic
}
finally {
    Remove-Item Env:\B2S_SUBMISSIONS_STAGING_SYNTHETIC_APPROVAL
    Remove-Item Env:\B2S_SUBMISSIONS_STAGING_KEY -ErrorAction SilentlyContinue
}
```

Do not delete the separately preserved key. Exit 0 means all fixture checks
succeeded; exit 1 means STOP, with sanitized errors and no automated recovery.
Reports omit credentials, raw tokens, payloads/notes and schema SQL.

## Exact requested next approval

> I authorize one controlled three-synthetic-v1 submission/review test on
> book-to-screen-staging, project cutaojpqvz, endpoint
> cutaojpqvz.g2.sqlite.cloud:8860, database author-submissions-staging.sqlite.
> Use the existing private staging admin config/identity manifest and a new,
> preserved private staging encryption key. I confirm writers are stopped and
> explicitly accept disposable synthetic-only loss/recreation without assumed
> Free-plan automatic snapshots (or confirm a tested backup/restore route).
> Use only the three defined fictional submissions, in-memory mock mail and
> approved isolated fictional reviewer fixture. Exercise idempotency, token
> confirmation/reuse, status/notes/evaluations/audit/duplicate checks; preserve
> vector metadata/schema/checksums and validate FK integrity. Stop on any
> discrepancy or ambiguous failure; no automatic resume, cleanup, schema/settings
> changes, production access, real emails, deployment or public activation.

A subsequent repeat inspection must also be explicitly authorized. No live
synthetic writes are authorized by the earlier empty-install approval.
