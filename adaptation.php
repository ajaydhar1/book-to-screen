<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$adaptationId = filter_input(
	INPUT_GET,
	'id',
	FILTER_VALIDATE_INT,
	['options' => ['min_range' => 1]]
);

if (!$adaptationId) {
	http_response_code(404);
	exit('Adaptation not found.');
}

$adaptationStmt = $db->prepare(
	'SELECT
		adaptations.*,
		leads.article_url AS lead_article_url,
		leads.article_excerpt AS lead_article_excerpt,
		leads.featured_image_url AS lead_featured_image_url,
		leads.published_at AS lead_published_at
	 FROM adaptations
	 LEFT JOIN leads ON leads.id = adaptations.lead_id
	 WHERE adaptations.id = :id
	 LIMIT 1'
);
$adaptationStmt->execute([':id' => $adaptationId]);
$adaptation = $adaptationStmt->fetch(PDO::FETCH_ASSOC);

if (!$adaptation) {
	http_response_code(404);
	exit('Adaptation not found.');
}

$displayTitle = trim((string) ($adaptation['adaptation_title'] ?? ''))
	?: (string) ($adaptation['book_title'] ?? 'Adaptation');
$bookAuthor = trim((string) ($adaptation['book_author'] ?? ''));
$sourceExcerpt = trim((string) ($adaptation['article_excerpt'] ?? ''))
	?: trim((string) ($adaptation['lead_article_excerpt'] ?? ''));
$sourceUrl = trim((string) ($adaptation['source_url'] ?? ''))
	?: trim((string) ($adaptation['lead_article_url'] ?? ''));
$sourceName = trim((string) ($adaptation['source_name'] ?? ''));
$sourcePublishedAt = $adaptation['source_published_at']
	?: $adaptation['lead_published_at'];
$imageUrl = trim((string) ($adaptation['featured_image_url'] ?? ''))
	?: trim((string) ($adaptation['lead_featured_image_url'] ?? ''));

if ($imageUrl !== '' && str_contains($imageUrl, 'deadline.com/wp-content/uploads/') && !str_contains($imageUrl, 'w=')) {
	$separator = str_contains($imageUrl, '?') ? '&' : '?';
	$imageUrl .= $separator . 'w=1200&h=675&crop=1';
}

$pageTitle = $displayTitle . ' | Book to Screen';
$adaptationDescription = trim((string) ($adaptation['short_note'] ?? '')) ?: $sourceExcerpt;
$adaptationBasis = 'Based on ' . (string) ($adaptation['book_title'] ?? 'this source material');
if ($bookAuthor !== '') {
	$adaptationBasis .= ' by ' . $bookAuthor;
}
$metaDescription = $adaptationDescription !== ''
	? $adaptationBasis . '. ' . $adaptationDescription
	: 'Learn about the ' . strtolower((string) ($adaptation['adaptation_type'] ?: 'screen'))
		. ' adaptation of ' . $adaptationBasis . '.';
$metaTitle = $pageTitle;
$metaCanonical = 'https://booktoscreen.org/adaptation.php?id=' . $adaptationId;
$metaImage = $imageUrl;
$metaType = 'article';

$authorAdaptations = [];
if ($bookAuthor !== '') {
	$authorStmt = $db->prepare(
		'SELECT id, book_title, adaptation_title, adaptation_type,
				featured_image_url
		 FROM adaptations
		 WHERE id <> :id AND book_author = :book_author
		 ORDER BY source_published_at DESC, created_at DESC
		 LIMIT 4'
	);
	$authorStmt->execute([
		':id' => $adaptationId,
		':book_author' => $bookAuthor,
	]);
	$authorAdaptations = $authorStmt->fetchAll(PDO::FETCH_ASSOC);
}

$tmdbAdaptations = [];
if ($bookAuthor !== '') {
	$tmdbStmt = $db->prepare(
		'SELECT tmdb_id, title, overview, release_date, poster_path,
				source_author
		 FROM tmdb_adaptations
		 WHERE source_author = :source_author
		 ORDER BY release_date DESC, tmdb_id DESC
		 LIMIT 4'
	);
	$tmdbStmt->execute([':source_author' => $bookAuthor]);
	$tmdbAdaptations = $tmdbStmt->fetchAll(PDO::FETCH_ASSOC);
}

$relatedAdaptations = [];
if (trim((string) ($adaptation['adaptation_type'] ?? '')) !== '') {
	$relatedStmt = $db->prepare(
		'SELECT id, book_title, adaptation_title, adaptation_type,
				adaptation_status, featured_image_url
		 FROM adaptations
		 WHERE id <> :id AND adaptation_type = :adaptation_type
		 ORDER BY source_published_at DESC, created_at DESC
		 LIMIT 4'
	);
	$relatedStmt->execute([
		':id' => $adaptationId,
		':adaptation_type' => $adaptation['adaptation_type'],
	]);
	$relatedAdaptations = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
}

function adaptation_detail_image(?string $url): ?string
{
	$url = trim((string) $url);
	if ($url === '') {
		return null;
	}

	if (str_contains($url, 'deadline.com/wp-content/uploads/') && !str_contains($url, 'w=')) {
		$separator = str_contains($url, '?') ? '&' : '?';
		return $url . $separator . 'w=450&h=253&crop=1';
	}

	return $url;
}

function adaptation_detail_type_label(?string $type): string
{
	return match (strtolower(trim((string) $type))) {
		'film' => 'Film',
		'television series', 'tv series', 'tv-series' => 'TV Series',
		'limited series', 'limited-series' => 'Limited Series',
		default => trim((string) $type) ?: 'Adaptation',
	};
}
?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<?php require __DIR__ . '/includes/meta.php'; ?>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/png" href="/favicon.png">
	<link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
	<link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
	<link rel="stylesheet" href="/assets/css/adaptation-detail.css?v=<?= filemtime(__DIR__ . '/assets/css/adaptation-detail.css') ?>">
</head>

<body>
	<?php require_once __DIR__ . '/includes/header.php'; ?>

	<main class="adaptation-detail-shell">
		<p class="eyebrow">Book to Screen</p>
		<p class="detail-back-link"><a href="/">← Back to adaptations</a></p>

		<article class="adaptation-detail">
			<?php if ($imageUrl !== ''): ?>
				<div class="adaptation-detail-image">
					<img src="<?= h($imageUrl) ?>" alt="<?= h($displayTitle) ?>" loading="eager" decoding="async">
				</div>
			<?php endif; ?>

			<div class="adaptation-detail-content">
				<div class="card-meta">
					<span><?= h(adaptation_detail_type_label($adaptation['adaptation_type'] ?? null)) ?></span>
					<?php if (!empty($adaptation['adaptation_status'])): ?>
						<span><?= h($adaptation['adaptation_status']) ?></span>
					<?php endif; ?>
				</div>

				<h1 class="editorial-title"><?= h($displayTitle) ?></h1>
				<p class="detail-based-on">Based on <em><?= h($adaptation['book_title']) ?></em></p>

				<?php if ($bookAuthor !== ''): ?>
					<p class="book-author">by <?= h($bookAuthor) ?></p>
				<?php endif; ?>

				<?php if (!empty($adaptation['short_note'])): ?>
					<p class="detail-note body-copy"><?= h($adaptation['short_note']) ?></p>
				<?php endif; ?>

				<?php if ($sourceExcerpt !== ''): ?>
					<p class="detail-excerpt body-copy"><?= h($sourceExcerpt) ?></p>
				<?php endif; ?>

				<div class="detail-source">
					<strong>Original announcement</strong>
					<span>
						<?= h($sourceName ?: 'Source') ?>
						<?php if ($sourcePublishedAt): ?>
							· <?= h(format_datetime($sourcePublishedAt)) ?>
						<?php endif; ?>
					</span>
					<?php if ($sourceUrl !== ''): ?>
						<a class="button button-primary" href="<?= h($sourceUrl) ?>" target="_blank" rel="noopener noreferrer">Read original announcement →</a>
					<?php endif; ?>
				</div>
			</div>
		</article>

		<?php if ($authorAdaptations): ?>
			<section class="detail-section">
				<div class="section-heading"><div><p class="eyebrow">More by <?= h($bookAuthor) ?></p><h2>Other announcements</h2></div></div>
				<div class="detail-card-grid">
					<?php foreach ($authorAdaptations as $item): ?>
						<a class="detail-card" href="/adaptation.php?id=<?= (int) $item['id'] ?>">
							<?php $itemImage = adaptation_detail_image($item['featured_image_url'] ?? null); ?>
							<?php if ($itemImage): ?><img src="<?= h($itemImage) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
							<span class="detail-card-meta"><?= h(adaptation_detail_type_label($item['adaptation_type'] ?? null)) ?></span>
							<h3><?= h($item['adaptation_title'] ?: $item['book_title']) ?></h3>
							<p class="card-summary"><?= h($item['book_title']) ?></p>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($tmdbAdaptations): ?>
			<section class="detail-section">
				<div class="section-heading"><div><p class="eyebrow">On TMDb</p><h2>More adaptations by <?= h($bookAuthor) ?></h2></div></div>
				<div class="detail-card-grid">
					<?php foreach ($tmdbAdaptations as $item): ?>
						<a class="detail-card" href="/trailers.php?author=<?= urlencode($item['source_author']) ?>">
							<?php if (!empty($item['poster_path'])): ?><img src="<?= h('https://image.tmdb.org/t/p/w342' . $item['poster_path']) ?>" alt="<?= h($item['title']) ?>" loading="lazy" decoding="async"><?php endif; ?>
							<span class="detail-card-meta">TMDb · <?= h($item['release_date'] ?: 'Release date unknown') ?></span>
							<h3><?= h($item['title']) ?></h3>
							<?php if (!empty($item['overview'])): ?><p class="card-summary"><?= h($item['overview']) ?></p><?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($relatedAdaptations): ?>
			<section class="detail-section">
				<div class="section-heading"><div><p class="eyebrow">Keep exploring</p><h2>Recent <?= h(adaptation_detail_type_label($adaptation['adaptation_type'] ?? null)) ?> adaptations</h2></div></div>
				<div class="detail-card-grid">
					<?php foreach ($relatedAdaptations as $item): ?>
						<a class="detail-card" href="/adaptation.php?id=<?= (int) $item['id'] ?>">
							<?php $itemImage = adaptation_detail_image($item['featured_image_url'] ?? null); ?>
							<?php if ($itemImage): ?><img src="<?= h($itemImage) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
							<span class="detail-card-meta"><?= h($item['adaptation_status'] ?: 'Adaptation') ?></span>
							<h3><?= h($item['adaptation_title'] ?: $item['book_title']) ?></h3>
							<p class="card-summary"><?= h($item['book_title']) ?></p>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</main>

	<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>
