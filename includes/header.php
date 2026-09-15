<?php
// /includes/header.php

$currentPage = basename($_SERVER['PHP_SELF']);
$headerSearchQuery = $currentPage === 'search.php'
    && isset($_GET['q'])
    && is_string($_GET['q'])
    ? $_GET['q']
    : '';

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

        <div class="site-header__actions">
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

            <form class="site-search" method="get" action="/search.php">
                <label class="site-search__label" for="site-search-query">
                    Search Book to Screen
                </label>
                <input
                    class="site-search__input"
                    id="site-search-query"
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars($headerSearchQuery, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Search Book to Screen...">
                <button class="site-search__button" type="submit">Search</button>
            </form>
        </div>

    </div>
</header>