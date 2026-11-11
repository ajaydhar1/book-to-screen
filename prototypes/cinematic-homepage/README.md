# Cinematic homepage prototype

This isolated PHP prototype combines the cinematic design study with the
production homepage's live content and user journeys. It does not replace
the production homepage or change application or deployment configuration.

## Preview

Run a PHP server from the **repository root**, using the same PHP version,
extensions, database configuration and data as the existing application:

```powershell
& 'C:\Program Files\Ampps\php\php.exe' -S 127.0.0.1:8080 -t .
```

Open `http://127.0.0.1:8080/prototypes/cinematic-homepage/`. Stop the server
with Ctrl+C. The repository must be the web server's document root: existing
shared application code uses root-relative URLs, including the random-trailer
endpoint. PHP's built-in server is for local development only. A static server
or opening the file directly cannot execute the PHP or application features.
The preview requires an existing administrator session; sign in at `/admin/`
first. Users without the admin role receive a 403. The PHP template fragments
reject direct requests. The page also sends noindex and private/no-store headers.

The prototype uses the configured application database, including a cloud
database if the existing configuration selects one. Preview queries are SELECT
only, but the shared connection is not necessarily restricted to read-only.
The preview refuses to open a missing local SQLite database. Production
read-only database permissions must be verified separately before deployment.
It uses the same browser
localStorage key as My List, so saving or removing an item also changes My List
for the same origin. Signing in to the existing admin system may itself update
login metadata. External posters, source images, YouTube playback and
external links require network access.

## Production comparison

| Production homepage | Original cinematic prototype | Expanded prototype |
| --- | --- | --- |
| Header: Trailers, Video Feed, My List, Acclaimed and search | Section-only navigation; no search | All production links and a real GET search form, including on mobile |
| Hero: random continuous trailers, announcements, From Page to Picture | Static story and process links | Real trailer theater, announcements link and experience link |
| Four recent TMDB releases, posters, trailer playback, date, author, Read, Listen and Save | Missing | Same release query and actions, shared list script and theater markup |
| Recommendation wizard and Surprise Me | Missing | Both production destinations, including the original surprise parameters |
| B2S 100: first five matched ranked films, posters and trailer buttons | Missing | Same dataset, match/load helpers and collection links |
| Five randomized discoveries excluding the current page's latest items; source, date, excerpt, book and adaptation links | Four fictional cards | Real randomly selected records with production metadata and destinations |
| Latest 12 announcements, source information, excerpts, book and adaptation links, pagination and empty state | Missing | Same ordering and page size; pagination stays in the prototype |
| About section and link | Mission, process and About link | Cinematic mission and process retained with the original About destination |
| Footer: discovery tools, announcements, My List, About, Neurochip, admin and social links | Minimal footer | All production destinations retained in a cinematic footer |
| Analytics and canonical/indexing metadata | No analytics; noindex | No analytics or production canonical; noindex retained for the experiment |

Production cards link to the adaptation detail page, which in turn provides the
source article journey; the prototype preserves that same destination. The
prototype uses the existing database queries and helpers, theater markup
and saved-list script; its presentation is independent.
Queries are kept locally because changing shared production code is outside
the experiment's scope. A prototype-local theater controller uses the same
endpoint and markup while handling player readiness and focus safely.

Film and story cards reveal as they enter the viewport; pointer hover adds a
subtle lift and clipped image zoom. Reduced-motion preferences disable these
effects. The featured discovery uses an opaque dark content panel so its
text remains legible regardless of the image.

The mission's CSS manuscript and screen composition adapts the page-to-picture
storytelling of `experience-v1` without importing its global styles, fonts or
scroll-driven scene. The full experience remains available via the hero link.

## Files and limitations

- `index.php`: page shell, header, hero, mission, process and footer.
- `data.php`: read-only homepage queries and shared helper loading.
- `content.php` and `story-card.php`: dynamic section and card templates.
- `styles.css`, `cinematic-content.css`, `script.js`: prototype styling, motion
  and theater focus management; theater CSS and My List JS are shared.
- `theater.js`: local YouTube theater controller, using the existing endpoint
  and shared theater markup.

The featured stories are real, not simulated. The prototype is not a
standalone application: its database, search, recommendations, destination
pages and My List script are the existing project's. Trailer availability
depends on the existing random-trailer endpoint and YouTube. The CSS grain
is decorative and may be absent in older browsers. Reduced-motion preferences
disable the prototype's animations; content remains available without JS,
though trailer playback and saved-list controls require JavaScript.
