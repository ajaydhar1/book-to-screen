<?php
if (!defined('CINEMATIC_PREVIEW_ENTRY')) {
    http_response_code(404);
    exit;
}
$bookUrl = barnes_and_noble_search_url($adaptation['book_title'] ?? null, $adaptation['book_author'] ?? null);
?>
<article class="cinema-story<?= !empty($featuredCard) ? ' cinema-story--featured' : '' ?>">
    <?php if (!empty($adaptation['featured_image_url'])): ?>
        <div class="cinema-story__image">
            <img src="<?= cinematic_escape(cinematic_image_url($adaptation['featured_image_url'], !empty($featuredCard) ? 900 : 450, !empty($featuredCard) ? 506 : 253)) ?>" alt="<?= cinematic_escape($adaptation['book_title']) ?>" width="900" height="506" loading="lazy" decoding="async">
        </div>
    <?php else: ?>
        <div class="cinema-story__image cinema-story__image--empty" aria-hidden="true"><span>Book to Screen</span></div>
    <?php endif; ?>
    <div class="cinema-story__body">
        <p class="cinema-meta"><?= cinematic_escape($adaptation['adaptation_type']) ?><?php if (!empty($adaptation['adaptation_status'])): ?> · <?= cinematic_escape($adaptation['adaptation_status']) ?><?php endif; ?></p>
        <h3><?= cinematic_escape($adaptation['adaptation_title'] ?: $adaptation['book_title']) ?></h3>
        <p>Based on <em><?= cinematic_escape($adaptation['book_title']) ?></em><?php if (!empty($adaptation['book_author'])): ?> by <?= cinematic_escape($adaptation['book_author']) ?><?php endif; ?></p>
        <?php if (!empty($adaptation['article_excerpt'])): ?><p class="cinema-excerpt"><?= cinematic_escape($adaptation['article_excerpt']) ?></p><?php endif; ?>
        <p class="cinema-source"><?= cinematic_escape($adaptation['source_name']) ?><?php if (!empty($adaptation['source_published_at'])): ?> · <?= cinematic_escape(date('M j, Y', strtotime($adaptation['source_published_at']))) ?><?php endif; ?></p>
        <div class="cinema-actions">
            <a href="../../adaptation.php?id=<?= (int) $adaptation['id'] ?>">View adaptation →</a>
            <?php if ($bookUrl): ?><a href="<?= cinematic_escape($bookUrl) ?>" target="_blank" rel="noopener noreferrer">Find the book ↗</a><?php endif; ?>
        </div>
    </div>
</article>
