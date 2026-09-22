<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ACCLAIMED_PUBLIC_COLLECTIONS = [
    'b2s-100',
    'afi-100',
    'academy-best-picture',
    'sight-sound-100',
    'national-film-registry',
];

function acclaimed_public_collection_label(string $collectionKey): string
{
    return match ($collectionKey) {
        'b2s-100' => '100 Book to Screen selections',
        'afi-100' => 'Book-to-screen adaptations represented in AFI\'s list',
        'academy-best-picture' => 'Best Picture winners represented',
        'sight-sound-100' => 'Book-to-screen adaptations represented in Sight & Sound\'s list',
        'national-film-registry' => 'Book-to-screen adaptations represented in the Registry',
        default => 'Book-to-screen adaptations represented',
    };
}

function acclaimed_public_item_context(array $item, string $collectionKey): string
{
    return match ($collectionKey) {
        'academy-best-picture' => 'Best Picture ' . ($item['award_year'] ?? ''),
        'national-film-registry' => 'Inducted ' . ($item['induction_year'] ?? ''),
        default => isset($item['rank']) ? '#' . $item['rank'] : '',
    };
}

function acclaimed_public_poster_url(?string $posterPath): ?string
{
    return $posterPath ? 'https://image.tmdb.org/t/p/w342' . $posterPath : null;
}

function acclaimed_public_load_adaptations(array $items): array
{
    $ids = array_values(array_unique(array_filter(
        array_map(static fn(array $item): ?int => isset($item['tmdb_id']) ? (int) $item['tmdb_id'] : null, $items),
        static fn(?int $id): bool => $id !== null && $id > 0
    )));

    if ($ids === []) {
        return [];
    }

    $placeholders = [];
    $parameters = [];

    foreach ($ids as $index => $id) {
        $placeholder = ':id_' . $index;
        $placeholders[] = $placeholder;
        $parameters[$placeholder] = $id;
    }

    $db = get_db();
    $statement = $db->prepare('
        SELECT
            tmdb_id,
            title,
            overview,
            release_date,
            poster_path,
            source_author,
            trailer_youtube_key
        FROM tmdb_adaptations
        WHERE tmdb_id IN (' . implode(', ', $placeholders) . ')
    ');

    foreach ($parameters as $placeholder => $id) {
        $statement->bindValue($placeholder, $id, PDO::PARAM_INT);
    }

    $statement->execute();
    $adaptations = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $adaptation) {
        $adaptations[(int) $adaptation['tmdb_id']] = $adaptation;
    }

    return $adaptations;
}

function acclaimed_public_matched_items(array $collection): array
{
    return array_values(array_filter(
        $collection['items'],
        static fn(array $item): bool => isset($item['tmdb_id']) && $item['tmdb_id'] !== null
    ));
}