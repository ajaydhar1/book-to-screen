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
                href="/#"
                class="site-nav__link<?= navActive('explore.php', $currentPage); ?>">
                Explore
            </a>

            <a
                href="/trailers.php"
                class="site-nav__link<?= navActive('trailers.php', $currentPage); ?>">
                Trailers
            </a>
        </nav>

    </div>
</header>