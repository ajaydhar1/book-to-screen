<?php
// /includes/header.php

$currentPage = basename($_SERVER['PHP_SELF']);

function navActive(string $page, string $currentPage): string
{
    return $page === $currentPage ? ' is-active' : '';
}
?>

<header class="site-header">
    <div class="site-header__inner">

        <a href="/" class="site-brand">
            Book to Screen
        </a>

        <nav class="site-nav" aria-label="Main navigation">
            <a
                href="/adaptation-announcements.php"
                class="site-nav__link<?= navActive('adaptation-announcements.php', $currentPage); ?>">
                Announcements
            </a>

            <a
                href="/trailers.php"
                class="site-nav__link<?= navActive('trailers.php', $currentPage); ?>">
                Adaptation Trailers
            </a>

            <a
                href="/about/"
                class="site-nav__link<?= str_starts_with($_SERVER['REQUEST_URI'], '/about/') ? ' is-active' : ''; ?>">
                About
            </a>
        </nav>

    </div>
</header>