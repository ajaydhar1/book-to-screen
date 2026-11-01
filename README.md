# Book to Screen

![Status](https://img.shields.io/badge/status-active-2ea44f)

Book to Screen is an editorial website that tracks upcoming book-to-screen adaptations through a curated publishing workflow. It combines automated RSS ingestion with human editorial review to create a searchable collection of film and television adaptations.

The project was built to demonstrate a complete content management workflow—from collecting industry news to publishing curated adaptation pages.

---

## Features

### Public Website

* Browse published book-to-screen adaptations
* Individual adaptation pages
* Responsive design for desktop and mobile
* SEO-friendly URLs and metadata
* XML sitemap

### Editorial Workflow

* Automatic RSS ingestion from industry news sources
* Pending leads dashboard
* Review, approve, or reject adaptation leads
* Editorial notes
* Featured article images
* Status tracking
* Duplicate prevention

### Administration

* Password-protected admin area
* SQLite database
* Automated cron job for importing new leads
* Clean editorial workflow from discovery to publication

---

## Tech Stack

* PHP
* SQLite
* HTML5
* CSS3
* JavaScript
* RSS/XML parsing
* Cron jobs
* Git & GitHub

---

## Project Goals

Book to Screen was created to provide readers with a curated database of upcoming book-to-film and book-to-television adaptations.

Rather than automatically publishing every entertainment article, the site uses a human-reviewed editorial workflow to ensure each adaptation is verified before publication.

---

## Running Locally

1. Clone the repository.

2. Create:

```
includes/config.local.php
```

and define your local administrator credentials.

3. Initialize the SQLite database.

4. Run the RSS import script manually or configure a cron job.

5. Serve the project using any PHP-enabled web server.

---

## Roadmap

* User accounts
* Search and filtering
* Adaptation timeline
* Expanded metadata
* Editorial analytics
* Additional news sources
* Image management improvements

---

## Live Website

https://booktoscreen.com

---

## Development Status

### Users Page
**Completed**
- SQLite-backed users table
- Display users from database
- Add User functionality
- Permission checks
- Temporary password validation

**Next**
- Connect dashboard statistics cards
- Edit User
- Reset Password
- Deactivate User
- Audit trail

---

## Contributing

### Local author-submission prototype

Milestone 1 is a local-only, three-step demonstration at
`/submission-prototype/`. It is not linked from public navigation and rejects
non-loopback requests. It does not load the application database or start a
session. Form values stay in the current page's DOM only: no email, submission
request, files, browser storage, or submission records are created.

Run from the repository root:

```powershell
php -d display_errors=0 -S 127.0.0.1:8083
```

Then visit `http://127.0.0.1:8083/submission-prototype/`.
When using AMPPS instead, visit
`http://localhost/book-to-screen/submission-prototype/`.
Stop the development server when finished. Do not expose it to the network.

The server queries Google Books first, with Open Library as a fallback. Set
`GOOGLE_BOOKS_API_KEY` in the PHP server's environment for reliable Google
access. Without a key, Google may reject or rate-limit requests; fallback and
manual entry remain available. Never put a real key in source control.
Optionally set `BOOK_METADATA_CONTACT` to an application contact email for
Open Library's identifying User-Agent. These settings are independent of
`includes/config.local.php`; no production/database configuration is loaded.

Both ISBN checksums are validated, and ISBN-10 is canonicalized to ISBN-13.
Providers must return matching identifiers. Normalized output includes title,
authors, subtitle, publisher, publication date, suggested genres, subjects/themes, description
when supplied, and provider provenance. Missing values remain empty/editable.
Open Library's Books API often has no description. New lookups ask before
replacing existing book fields. Each field identifies API, manual, or edited
origin. Rights and authority are self-declared, never inferred from author or
email identity.

Genres and subjects are separate editable fields in both the book and review
steps. Common genre buttons support multiple selections; free text also allows
custom comma-separated genres. Suggestions use conservative, explicit aliases
matched against whole provider taxonomy components (including slash-separated
categories), never titles or descriptions. Generic fiction/children terms do
not imply Literary Fiction or Young Adult. Unknown classifications remain
unclassified, not automatically "Other".

Subjects are case-insensitively deduplicated, stripped of common catalog,
format and classification noise, and limited to eight short topics. Up to 100
sanitized original provider terms (200 characters each) remain in the lookup
response for context, not displayed or persisted. These rules are a small
English-language vocabulary, not a comprehensive taxonomy; author correction
is expected. No genre or subject information is required to continue.

Lookup accepts only a GET request with one `isbn` query parameter and no body.
Each click makes at most one call per provider, with 3-second connect and
6-second total timeouts per call, TLS verification and a 512 KiB response limit.
Only the ISBN leaves the page; provider errors contain no API URL or key.
The local prototype uses click-triggered requests and a client cooldown, not a
shared production rate limiter or persistent cache. Do not load-test providers.
Before any launch, review [Google Books usage](https://developers.google.com/books/docs/v1/using)
and [Open Library API guidelines](https://openlibrary.org/developers/api),
configure quotas and identification, and design appropriate server-side limits.

Run the dependency-free mocked service and safety tests:

```powershell
php -d display_errors=0 tests\book-metadata-test.php
node --check assets\js\submission-prototype.js
```

For browser evaluation, use sample contact data and check lookup/loading/error
states, editing, conditional fields, all step-navigation paths, replacement
confirmation, mobile widths, keyboard focus, and the demo-only final action.
Reloading starts over. The isolated header/footer deliberately omit the shared
search form, analytics and My List storage script.

No manuscript uploads, accounts, payments, email verification, administrative
queue, submission persistence, publication, deployment or research-record
changes are part of this milestone.

Suggestions, bug reports, and pull requests are welcome.

---

## License

The MIT License applies to the source code only.

Original editorial content—including adaptation summaries, reviews, articles, and other written content—is **© 2026 Ajay Dhar. All rights reserved.**
