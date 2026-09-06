<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Google tag (gtag.js) -->
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

    <title>About Book to Screen | Our Mission and Editorial Process</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta
        name="description"
        content="Learn about Book to Screen, our editorial process, and how we track books, articles, podcasts, comics, and true stories being adapted for film and television.">

    <meta
        name="robots"
        content="index,follow">

    <link rel="canonical" href="https://booktoscreen.org/about/">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/../assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/../assets/css/header-footer.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <main>
        <section class="hero about-hero">
            <p class="eyebrow">About Book to Screen</p>

            <h1>The stories behind tomorrow’s movies and television.</h1>

            <p class="hero-intro">
                Book to Screen is an editorially curated adaptation tracker following
                books, articles, podcasts, comics, short stories, and true stories as
                they make their way toward film and television.
            </p>
        </section>

        <section class="section">
            <div class="content-card">
                <p class="eyebrow">Our Mission</p>

                <h2>Follow the source material before it reaches the screen.</h2>

                <p>
                    Every adaptation begins with a source. Sometimes it is a bestselling
                    novel or celebrated memoir. Other times, it is a magazine article,
                    podcast, comic, short story, or remarkable real-life event.
                </p>

                <p>
                    Book to Screen collects and organizes adaptation announcements so
                    readers and viewers can discover the original stories behind upcoming
                    movies and television projects.
                </p>

                <p>
                    Our goal is to create a useful, accessible archive for anyone interested
                    in the connection between publishing, storytelling, and the screen
                    entertainment industry.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="content-card">
                <p class="eyebrow">Editorial Process</p>

                <h2>How adaptations are added.</h2>

                <p>
                    Book to Screen reviews entertainment industry reporting and evaluates
                    each potential lead before it is added to the public archive.
                </p>

                <div class="process-grid">
                    <article class="process-step">
                        <span class="process-number">1</span>

                        <h3>Discover</h3>

                        <p>
                            We review industry news and identify announcements that may
                            involve existing source material.
                        </p>
                    </article>

                    <article class="process-step">
                        <span class="process-number">2</span>

                        <h3>Verify</h3>

                        <p>
                            We confirm the original work, its creator, and the announced
                            film or television project.
                        </p>
                    </article>

                    <article class="process-step">
                        <span class="process-number">3</span>

                        <h3>Classify</h3>

                        <p>
                            Each project is categorized by adaptation type and its current
                            stage of development.
                        </p>
                    </article>

                    <article class="process-step">
                        <span class="process-number">4</span>

                        <h3>Publish</h3>

                        <p>
                            Verified adaptations are added to the archive with a link to
                            the original reporting.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="team-card">
                <p class="eyebrow">Editorial Team</p>

                <h2>The people behind the archive.</h2>

                <div class="team-member">
                    <h3>Ajay Dhar</h3>

                    <p class="team-role">Founder &amp; Editor</p>

                    <p class="team-description">
                        Ajay founded Book to Screen and oversees its editorial direction,
                        adaptation research, classification standards, contributor
                        program, and ongoing product development.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Sarah Beth Couvillion</h3>

                    <p class="team-role">Editorial Contributor</p>

                    <p class="team-description">
                        Sarah contributes to Book to Screen's editorial research and review process,
                        helping identify and evaluate adaptation leads for inclusion in the archive.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Product Lead</h3>

                    <p class="team-role">Coming Soon</p>

                    <p class="team-description">
                        The Product Lead will oversee the long-term success of Book
                        to Screen by planning new features, managing the product
                        roadmap and backlog, coordinating projects, maintaining
                        documentation, monitoring site health and analytics,
                        supporting marketing efforts, and continuously improving
                        the experience for readers, contributors, and publishers
                        while helping Neurochip build the company behind the platform.
                    </p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="contribute-card">
                <p class="eyebrow">Contribute</p>

                <h2>Help us discover what is being adapted next.</h2>

                <p>
                    Community submissions and contributor opportunities are planned for
                    the future. In the meantime, explore the latest verified adaptations
                    in the Book to Screen archive.
                </p>

                <a class="button button-primary" href="/">
                    Browse Latest Adaptations →
                </a>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>