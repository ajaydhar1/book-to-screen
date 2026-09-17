<?php

function meta_escape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$metaTitle = (string) ($metaTitle ?? 'Book to Screen');
$metaDescription = (string) ($metaDescription ?? '');
$metaCanonical = (string) ($metaCanonical ?? '');
$metaImage = trim((string) ($metaImage ?? ''));
$metaType = (string) ($metaType ?? 'website');
?>
<title><?= meta_escape($metaTitle) ?></title>
<meta name="description" content="<?= meta_escape($metaDescription) ?>">
<?php if ($metaCanonical !== ''): ?>
<link rel="canonical" href="<?= meta_escape($metaCanonical) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= meta_escape($metaTitle) ?>">
<meta property="og:description" content="<?= meta_escape($metaDescription) ?>">
<?php if ($metaImage !== ''): ?>
<meta property="og:image" content="<?= meta_escape($metaImage) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= meta_escape($metaCanonical) ?>">
<meta property="og:type" content="<?= meta_escape($metaType) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= meta_escape($metaTitle) ?>">
<meta name="twitter:description" content="<?= meta_escape($metaDescription) ?>">
<?php if ($metaImage !== ''): ?>
<meta name="twitter:image" content="<?= meta_escape($metaImage) ?>">
<?php endif; ?>
