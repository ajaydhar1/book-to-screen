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

Suggestions, bug reports, and pull requests are welcome.

---

## License

The MIT License applies to the source code only.

Original editorial content—including adaptation summaries, reviews, articles, and other written content—is **© 2026 Ajay Dhar. All rights reserved.**
