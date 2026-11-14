<?php

declare(strict_types=1);

define('CINEMATIC_PREVIEW_ENTRY', true);

function cinematic_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cinematic_image_url(string $url, int $width, int $height): string
{
    if (str_contains($url, 'deadline.com/wp-content/uploads/') && !str_contains($url, 'w=')) {
        $url .= (str_contains($url, '?') ? '&' : '?') . "w={$width}&h={$height}&crop=1";
    }

    return $url;
}

$cinematicRoot = __DIR__ . '/..';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LRF3X9CMCT"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', 'G-LRF3X9CMCT');
    </script>

    <meta charset="UTF-8">
    <?php require $cinematicRoot . '/includes/meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/prototypes/cinematic-redesign/styles.css?v=<?= filemtime($cinematicRoot . '/prototypes/cinematic-redesign/styles.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime($cinematicRoot . '/assets/css/trailer-theater.css') ?>">
    <link rel="stylesheet" href="/prototypes/cinematic-redesign/cinematic-content.css?v=<?= filemtime($cinematicRoot . '/prototypes/cinematic-redesign/cinematic-content.css') ?>">
    <link rel="stylesheet" href="/assets/css/cinematic-brand.css?v=<?= filemtime($cinematicRoot . '/assets/css/cinematic-brand.css') ?>">
</head>

<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <div class="grain" aria-hidden="true"></div>

    <header class="site-nav" id="siteNav">
        <a class="brand" href="#top">
            <span class="brand-mark" aria-hidden="true">B·S</span>
            <span class="brand-name">Book <span class="brand-name__connector">to</span> Screen</span>
        </a>

        <nav class="nav-links" aria-label="Main navigation">
            <a href="/trailers.php">Trailers</a>
            <a href="/video-feed.php">Video Feed</a>
            <a href="/my-list.php">My List</a>
            <a href="/acclaimed.php">Acclaimed</a>
            <a class="nav-cta" href="#latest-announcements">Announcements</a>
        </nav>

        <form class="cinema-search" action="/search.php" method="get" role="search">
            <label for="cinema-search">Search Book to Screen</label>
            <input id="cinema-search" name="q" type="search" placeholder="Search titles, authors…">
            <button type="submit">Search</button>
        </form>
    </header>

    <main id="main">
        <section class="hero" id="top">
            <div class="hero-backdrop" aria-hidden="true">
                <div class="glow glow-amber"></div>
                <div class="glow glow-blue"></div>
                <div class="hero-pages">
                    <span class="page-sliver"></span>
                    <span class="page-sliver"></span>
                    <span class="page-sliver"></span>
                    <span class="page-sliver"></span>
                    <span class="page-sliver"></span>
                </div>
            </div>

            <div class="hero-content">
                <p class="eyebrow reveal-line" style="--d: 0">An adaptation tracker for readers &amp; viewers</p>
                <h1 class="hero-title">
                    <span class="reveal-line" style="--d: 1">Every film begins</span>
                    <span class="reveal-line" style="--d: 2">as a <em>story</em> on a page.</span>
                </h1>
                <p class="hero-sub reveal-line" style="--d: 3">
                    Book to Screen follows books, articles, podcasts, comics, and true
                    stories as they make their way toward film and television —
                    so you can read tomorrow's screen stories today.
                </p>
                <div class="hero-actions reveal-line" style="--d: 4">
                    <button class="btn btn-primary" type="button" data-random-trailer>Watch trailers</button>
                    <a class="btn btn-ghost" href="/adaptation-announcements.php">Latest announcements</a>
                </div>
                <a class="experience-link" href="/experience-v1/">From Page to Picture <span aria-hidden="true">↗</span><small>Explore the journey from book to screen.</small></a>
            </div>

            <div class="scroll-hint reveal-line" style="--d: 5" aria-hidden="true">
                <span class="scroll-line"></span>
                <span class="scroll-label">Scroll</span>
            </div>
        </section>

        <section class="section mission" id="mission">
            <div class="section-inner split">
                <div class="split-copy" data-reveal>
                    <p class="eyebrow">What we do</p>
                    <h2 class="section-title">The source material, before it reaches the screen.</h2>
                    <p class="lede">
                        Every adaptation begins somewhere — a bestselling novel, a celebrated
                        memoir, a magazine feature, a podcast, a remarkable true event.
                    </p>
                    <p class="body">
                        We collect and organize adaptation announcements into a curated,
                        searchable archive, connecting publishing and the screen
                        entertainment industry. Each entry is editorially reviewed against
                        the original reporting, with the source material at its heart.
                    </p>
                </div>

                <div class="mission-art" data-reveal aria-hidden="true">
                    <div class="mission-art__light"></div>
                    <div class="mission-art__page mission-art__page--back"></div>
                    <div class="mission-art__page mission-art__page--front">
                        <span class="mission-art__page-label">The beginning</span>
                        <span class="mission-art__lines"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        <span class="mission-art__folio">01</span>
                    </div>
                    <div class="mission-art__screen"><span></span></div>
                    <span class="mission-art__caption">From page to picture</span>
                </div>
            </div>
        </section>

        <?php require $cinematicRoot . '/prototypes/cinematic-redesign/content.php'; ?>

        <section class="section process" id="process">
            <div class="section-inner">
                <div class="section-heading" data-reveal>
                    <p class="eyebrow">Our discovery process</p>
                    <h2 class="section-title">From the first report to the archive.</h2>
                </div>

                <ol class="process-track" data-reveal data-reveal-stagger>
                    <li class="process-step">
                        <span class="step-index" aria-hidden="true">01</span>
                        <h3>Scan the trades</h3>
                        <p>We review entertainment industry reporting for announcements involving existing source material.</p>
                    </li>
                    <li class="process-step">
                        <span class="step-index" aria-hidden="true">02</span>
                        <h3>Verify the source</h3>
                        <p>Each lead is traced back to its original material — the book, article, podcast, or true story behind the headline.</p>
                    </li>
                    <li class="process-step">
                        <span class="step-index" aria-hidden="true">03</span>
                        <h3>Classify the project</h3>
                        <p>We categorize each adaptation by type and its current stage of development.</p>
                    </li>
                    <li class="process-step">
                        <span class="step-index" aria-hidden="true">04</span>
                        <h3>Publish the record</h3>
                        <p>Verified entries join a searchable public archive connecting readers to the source material first.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="section finale" id="explore">
            <div class="finale-glow" aria-hidden="true"></div>
            <div class="section-inner finale-inner" data-reveal>
                <p class="eyebrow">Start exploring</p>
                <h2 class="finale-title">One story. Two ways to be moved.</h2>
                <p class="lede">
                    Find the original stories behind tomorrow's movies and television.
                    Read the source, then see where the story takes you.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="/search.php">Search the archive</a>
                    <a class="btn btn-ghost" href="/about/">About Book to Screen</a>
                </div>
            </div>
        </section>
    </main>

    <?php require $cinematicRoot . '/includes/trailer-theater.php'; ?>

    <footer class="site-footer">
        <div class="footer-inner cinema-footer">
            <div>
                <p class="footer-brand">Book to Screen</p>
                <p class="footer-note">Discover the stories behind tomorrow's screen adaptations.</p>
            </div>
            <nav aria-label="Discover">
                <h2>Discover</h2>
                <a href="/recommendation-wizard.php">Recommendation Wizard</a>
                <a href="/releases.php">Release Browser</a>
                <a href="/acclaimed-collection.php?collection=b2s-100">B2S 100 Greatest</a>
                <a href="/acclaimed-overlap.php">Acclaimed Overlap Explorer</a>
                <a href="/film-score-radio/" target="_blank" rel="noopener noreferrer">Film Score Radio ↗</a>
            </nav>
            <nav aria-label="More">
                <h2>More</h2>
                <a href="/adaptation-announcements.php">Announcements</a>
                <a href="/my-list.php">My List</a>
                <a href="/about/">About</a>
                <a href="https://databank.booktoscreen.org/?bank=neurochip" target="_blank" rel="noopener noreferrer">Neurochip Databank ↗</a>
                <a href="/admin/">Editorial Administration</a>
            </nav>
            <nav aria-label="Social media">
                <h2>Follow</h2>
                <a href="https://www.instagram.com/booktoscreenorg/" target="_blank" rel="noopener noreferrer">Instagram</a>
                <a href="https://x.com/booktoscreenorg" target="_blank" rel="noopener noreferrer">X</a>
                <a href="https://www.threads.net/@booktoscreenorg" target="_blank" rel="noopener noreferrer">Threads</a>
                <a href="https://www.linkedin.com/company/booktoscreenorg/" target="_blank" rel="noopener noreferrer">LinkedIn</a>
            </nav>
            <p class="footer-note">© <?= date('Y') ?> Book to Screen</p>
        </div>
    </footer>

    <script src="/assets/js/my-list.js?v=<?= filemtime($cinematicRoot . '/assets/js/my-list.js') ?>"></script>
    <script src="/prototypes/cinematic-redesign/theater.js?v=<?= filemtime($cinematicRoot . '/prototypes/cinematic-redesign/theater.js') ?>"></script>
    <script src="/prototypes/cinematic-redesign/script.js?v=<?= filemtime($cinematicRoot . '/prototypes/cinematic-redesign/script.js') ?>"></script>
</body>

</html>
