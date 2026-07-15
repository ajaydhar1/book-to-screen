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

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            background: #f7f2ea;
            color: #241c15;
        }

        a {
            color: inherit;
        }

        .site-header {
            padding: 28px 24px;
            border-bottom: 1px solid #ded2c2;
            background: #fffaf3;
        }

        .site-header-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .site-title {
            margin: 0;
            font-size: 1.4rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .site-title a {
            text-decoration: none;
        }

        .header-link {
            font-size: 0.9rem;
            font-weight: bold;
            text-decoration: none;
        }

        .header-link:hover {
            text-decoration: underline;
        }

        .hero {
            max-width: 960px;
            margin: 0 auto;
            padding: 72px 24px 48px;
        }

        .eyebrow {
            margin: 0 0 12px;
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #8a5a32;
            font-weight: bold;
        }

        .hero h1 {
            margin: 0;
            max-width: 850px;
            font-size: clamp(2.6rem, 7vw, 5rem);
            line-height: 0.98;
        }

        .hero-intro {
            max-width: 680px;
            margin: 24px 0 0;
            font-size: 1.2rem;
            line-height: 1.65;
            color: #5d5146;
        }

        .section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px;
        }

        .content-card {
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 24px;
            padding: 36px;
            box-shadow: 0 10px 30px rgba(36, 28, 21, 0.05);
        }

        .content-card h2 {
            margin: 0 0 16px;
            font-size: 2rem;
        }

        .content-card p {
            max-width: 760px;
            margin: 0 0 18px;
            font-size: 1.05rem;
            line-height: 1.7;
            color: #5d5146;
        }

        .content-card p:last-child {
            margin-bottom: 0;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 26px;
        }

        .process-step {
            padding: 24px;
            border: 1px solid #e5d8c8;
            border-radius: 18px;
            background: #fdf8f1;
        }

        .process-number {
            display: inline-flex;
            width: 34px;
            height: 34px;
            margin-bottom: 14px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #8a5a32;
            color: #fffaf3;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .process-step h3 {
            margin: 0 0 10px;
            font-size: 1.2rem;
        }

        .process-step p {
            margin: 0;
            font-size: 0.98rem;
        }

        .team-card {
            background: #2b2118;
            color: #fffaf3;
            border-radius: 24px;
            padding: 36px;
        }

        .team-card .eyebrow {
            color: #d7aa7a;
        }

        .team-card h2 {
            margin: 0 0 24px;
            font-size: 2rem;
        }

        .team-member {
            padding: 32px 0;
            border-top: 1px solid rgba(255, 250, 243, 0.18);
        }

        .team-member:first-of-type {
            padding-top: 0;
            border-top: none;
        }

        .team-member:last-of-type {
            padding-bottom: 0;
        }

        .team-member h3 {
            margin: 0 0 6px;
            font-size: 1.45rem;
        }

        .team-role {
            margin: 0 0 14px;
            color: #d7aa7a;
            font-weight: bold;
            letter-spacing: .03em;
        }

        .team-description {
            margin: 0;
            max-width: 720px;
            line-height: 1.7;
            color: #eadfce;
        }

        .contribute-card {
            text-align: center;
            padding: 44px 36px;
            background: #eadfce;
            border-radius: 24px;
        }

        .contribute-card h2 {
            margin: 0 0 14px;
            font-size: 2rem;
        }

        .contribute-card p {
            max-width: 650px;
            margin: 0 auto 24px;
            line-height: 1.65;
            color: #5d5146;
        }

        .button {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: bold;
            text-decoration: none;
        }

        .button-primary {
            background: #2b2118;
            color: #fffaf3;
        }

        .button-primary:hover {
            background: #443326;
        }

        .site-footer {
            padding: 36px 24px;
            text-align: center;
            color: #7b6d5e;
            font-size: 0.9rem;
        }

        .site-footer a {
            color: inherit;
            text-decoration: none;
        }

        .site-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            .site-header-inner {
                align-items: flex-start;
            }

            .hero {
                padding-top: 48px;
            }

            .process-grid {
                grid-template-columns: 1fr;
            }

            .content-card,
            .team-card,
            .contribute-card {
                padding: 28px 24px;
            }
        }
    </style>
</head>

<body>

    <header class="site-header">
        <div class="site-header-inner">
            <p class="site-title">
                <a href="/">Book to Screen</a>
            </p>

            <a class="header-link" href="/">
                Latest Adaptations →
            </a>
        </div>
    </header>

    <main>
        <section class="hero">
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
                        adaptation research, contributor workflow, classification standards,
                        and ongoing development.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Sarah C.</h3>

                    <p class="team-role">Research Contributor</p>

                    <p class="team-description">
                        Sarah reviews potential adaptation leads, verifies source material,
                        and supports the editorial research process behind the Book to Screen
                        archive.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Kieara F.</h3>

                    <p class="team-role">Contributor</p>

                    <p class="team-description">
                        Kieara helps identify interesting adaptation announcements and
                        contributes ideas that support the discovery of new books,
                        films, and television projects.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Bethy W.</h3>

                    <p class="team-role">Contributor</p>

                    <p class="team-description">
                        Bethy contributes community perspectives and helps surface
                        noteworthy adaptation leads for editorial review.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Nick H.</h3>

                    <p class="team-role">Contributor</p>

                    <p class="team-description">
                        Nick helped inspire the original concept for Book to Screen and
                        encouraged the creation of dedicated websites celebrating books,
                        adaptations, and storytelling.
                    </p>
                </div>

                <div class="team-member">
                    <h3>Sean Fletcher</h3>

                    <p class="team-role">Editorial Advisor</p>

                    <p class="team-description">
                        Sean provides editorial insight and thoughtful feedback that
                        helps shape the site's writing, organization, and long-term
                        direction.
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

    <footer class="site-footer">
        &copy; <?= date('Y') ?> Book to Screen ·
        <a href="/">Home</a> ·
        <a href="/admin/">Editorial Administration</a>
    </footer>

</body>

</html>