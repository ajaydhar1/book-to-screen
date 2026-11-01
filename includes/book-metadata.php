<?php

declare(strict_types=1);

final class BookMetadataProviderException extends RuntimeException
{
}

function book_isbn_normalize(string $input): ?string
{
    $isbn = strtoupper(preg_replace('/[\s-]+/', '', $input) ?? '');

    if (preg_match('/^\d{9}[\dX]$/', $isbn) === 1) {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (10 - $i) * ($isbn[$i] === 'X' ? 10 : (int) $isbn[$i]);
        }
        if ($sum % 11 !== 0) {
            return null;
        }
        $isbn = '978' . substr($isbn, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return $isbn . ((10 - $sum % 10) % 10);
    }

    if (preg_match('/^97[89]\d{10}$/', $isbn) !== 1) {
        return null;
    }

    $sum = 0;
    for ($i = 0; $i < 13; $i++) {
        $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    return $sum % 10 === 0 ? $isbn : null;
}

function book_metadata_text(mixed $value, int $limit = 2000): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') {
        return null;
    }
    return function_exists('mb_substr')
        ? mb_substr($text, 0, $limit, 'UTF-8')
        : substr($text, 0, $limit);
}

function book_metadata_strings(mixed $values, bool $named = false): array
{
    if (!is_array($values)) {
        return [];
    }
    $result = [];
    foreach (array_slice($values, 0, 20) as $value) {
        $text = book_metadata_text($named && is_array($value) ? ($value['name'] ?? null) : $value, 200);
        if ($text !== null) {
            $result[] = $text;
        }
    }
    return array_values(array_unique($result));
}

function book_metadata_has_isbn(mixed $identifiers, string $isbn): bool
{
    if (!is_array($identifiers)) {
        return false;
    }
    foreach ($identifiers as $identifier) {
        if (is_string($identifier) && book_isbn_normalize($identifier) === $isbn) {
            return true;
        }
    }
    return false;
}

function book_metadata_classification(mixed $values, bool $named = false): array
{
    $aliases = [
        'Science Fiction' => ['science fiction', 'science-fiction', 'sci-fi', 'sci fi'],
        'Fantasy' => ['fantasy', 'fantasy fiction'],
        'Romance' => ['romance', 'romance fiction', 'romantic fiction'],
        'Mystery' => ['mystery', 'mystery fiction', 'mystery and detective stories', 'detective fiction', 'mystery & detective'],
        'Thriller' => ['thriller', 'thrillers', 'thriller fiction', 'suspense fiction', 'thrillers & suspense'],
        'Horror' => ['horror', 'horror fiction', 'horror tales'],
        'Historical Fiction' => ['historical fiction', 'historical novels'],
        'Literary Fiction' => ['literary fiction'],
        'Young Adult' => ['young adult', 'young adult fiction', 'young adult literature'],
        'Memoir / Biography' => ['memoir', 'memoirs', 'biography', 'biographies', 'autobiography', 'autobiographies', 'biography & autobiography'],
        'Nonfiction' => ['nonfiction', 'non-fiction', 'non fiction'],
    ];
    $noise = [
        'fiction', 'general', 'juvenile fiction', 'juvenile literature', 'juvenile works',
        "children's fiction", "children's stories", 'children', 'novels', 'novel',
        'english fiction', 'american fiction', 'fiction in english',
        'large type books', 'accessible book', 'protected daisy', 'in library',
        'open library staff picks', 'reading level-grade', 'reading level-age',
    ];
    $raw = [];
    $genres = [];
    $subjects = [];
    foreach (is_array($values) ? array_slice($values, 0, 100) : [] as $value) {
        $term = book_metadata_text($named && is_array($value) ? ($value['name'] ?? null) : $value, 200);
        if ($term === null) {
            continue;
        }
        $raw[] = $term;
        // Match whole taxonomy components, never title/description keywords or substrings.
        $parts = preg_split('/\s*\/\s*|\s+--\s+/', $term) ?: [];
        $fictionCategory = preg_match('/^(?:fiction|young adult fiction)\s*\//i', $term) === 1;
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);
            $key = function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part);
            $genre = null;
            foreach ($aliases as $label => $variations) {
                if (in_array($key, $variations, true)) {
                    $genre = $label;
                    break;
                }
            }
            if ($genre === null && $fictionCategory) {
                $genre = match ($key) {
                    'historical' => 'Historical Fiction',
                    'literary' => 'Literary Fiction',
                    default => null,
                };
            }
            if ($genre !== null) {
                $genres[$genre] = $genre;
                continue;
            }
            if ($part === '' || strlen($part) > 80 || in_array($key, $noise, true)
                || preg_match('/^(?:fiction|juvenile|children\'s)\\b/i', $part) === 1
                || preg_match('/^(?:bisac|lcc|ddc|dewey|lc classification|collection|series|reading level|nyt:|award:|subject:|[a-z]{1,3}\\d|\\d{3}(?:\\.|$))/i', $part) === 1
                || preg_match('/^\\d{4}(?:-\\d{4})?$/', $part) === 1) {
                continue;
            }
            $subjects[$key] ??= $part;
        }
    }
    return [
        'genres' => array_values($genres),
        'subjects' => array_slice(array_values($subjects), 0, 8),
        'provider_terms' => $raw,
    ];
}

function book_metadata_record(string $isbn, string $provider, ?string $id, array $fields): array
{
    return [
        'isbn' => $isbn,
        'provider' => $provider,
        'provider_id' => $id,
        'source_url' => $id === null ? null : (
            $provider === 'Google Books'
                ? 'https://books.google.com/books?id=' . rawurlencode($id)
                : 'https://openlibrary.org/books/' . rawurlencode($id)
        ),
        'title' => book_metadata_text($fields['title'] ?? null, 300),
        'authors' => $fields['authors'],
        'subtitle' => book_metadata_text($fields['subtitle'] ?? null, 500),
        'publisher' => book_metadata_text($fields['publisher'] ?? null, 300),
        'publication_date' => book_metadata_text($fields['publication_date'] ?? null, 100),
        'genres' => $fields['classification']['genres'],
        'subjects' => $fields['classification']['subjects'],
        'provider_terms' => $fields['classification']['provider_terms'],
        'description' => book_metadata_text($fields['description'] ?? null, 5000),
    ];
}

function book_metadata_google(array $response, string $isbn): ?array
{
    if (isset($response['error'])) {
        throw new BookMetadataProviderException('Google Books returned an API error.');
    }
    if (!isset($response['items'])) {
        if (($response['totalItems'] ?? null) === 0) {
            return null;
        }
        throw new BookMetadataProviderException('Google Books returned an unexpected response.');
    }
    if (!is_array($response['items']) || !array_is_list($response['items'])) {
        throw new BookMetadataProviderException('Google Books returned malformed items.');
    }
    $validVolumes = 0;
    foreach ($response['items'] as $item) {
        if (!is_array($item) || !is_array($item['volumeInfo'] ?? null)) {
            continue;
        }
        $validVolumes++;
        $info = $item['volumeInfo'];
        $identifiers = [];
        foreach (is_array($info['industryIdentifiers'] ?? null) ? $info['industryIdentifiers'] : [] as $identifier) {
            if (is_array($identifier) && in_array($identifier['type'] ?? null, ['ISBN_10', 'ISBN_13'], true)) {
                $identifiers[] = $identifier['identifier'] ?? null;
            }
        }
        if (!book_metadata_has_isbn($identifiers, $isbn) || book_metadata_text($info['title'] ?? null) === null) {
            continue;
        }
        return book_metadata_record($isbn, 'Google Books', book_metadata_text($item['id'] ?? null, 100), [
            'title' => $info['title'],
            'authors' => book_metadata_strings($info['authors'] ?? null),
            'subtitle' => $info['subtitle'] ?? null,
            'publisher' => $info['publisher'] ?? null,
            'publication_date' => $info['publishedDate'] ?? null,
            'classification' => book_metadata_classification($info['categories'] ?? null),
            'description' => $info['description'] ?? null,
        ]);
    }
    if ($response['items'] !== [] && $validVolumes === 0) {
        throw new BookMetadataProviderException('Google Books returned no usable volume records.');
    }
    return null;
}

function book_metadata_open_library(array $response, string $isbn): ?array
{
    $book = $response['ISBN:' . $isbn] ?? null;
    if ($book === null) {
        if ($response === []) {
            return null;
        }
        throw new BookMetadataProviderException('Open Library returned an unexpected response.');
    }
    if (!is_array($book)) {
        throw new BookMetadataProviderException('Open Library returned malformed book data.');
    }
    $ids = is_array($book['identifiers'] ?? null) ? $book['identifiers'] : [];
    $identifiers = array_merge(
        is_array($ids['isbn_10'] ?? null) ? $ids['isbn_10'] : [],
        is_array($ids['isbn_13'] ?? null) ? $ids['isbn_13'] : []
    );
    if (!book_metadata_has_isbn($identifiers, $isbn) || book_metadata_text($book['title'] ?? null) === null) {
        return null;
    }
    $olid = book_metadata_strings($ids['openlibrary'] ?? null)[0] ?? null;
    if ($olid !== null && preg_match('/^OL\d+M$/', $olid) !== 1) {
        $olid = null;
    }
    $description = $book['description'] ?? null;
    if (is_array($description)) {
        $description = $description['value'] ?? null;
    }
    return book_metadata_record($isbn, 'Open Library', $olid, [
        'title' => $book['title'],
        'authors' => book_metadata_strings($book['authors'] ?? null, true),
        'subtitle' => $book['subtitle'] ?? null,
        'publisher' => implode(', ', book_metadata_strings($book['publishers'] ?? null, true)),
        'publication_date' => $book['publish_date'] ?? null,
        'classification' => book_metadata_classification($book['subjects'] ?? null, true),
        'description' => $description,
    ]);
}

function book_metadata_http(string $url): array
{
    if (!function_exists('curl_init')) {
        throw new BookMetadataProviderException('PHP cURL is unavailable.');
    }
    $ch = curl_init($url);
    if ($ch === false) {
        throw new BookMetadataProviderException('Could not initialize metadata lookup.');
    }
    $body = '';
    $tooLarge = false;
    $contact = getenv('BOOK_METADATA_CONTACT') ?: '';
    $contact = preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $contact) === 1 ? $contact : '';
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'BookToScreenPrototype/1.0' . ($contact === '' ? '' : ' (' . $contact . ')'),
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 524288) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $success = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorNumber = curl_errno($ch);
    curl_close($ch);
    if ($tooLarge) {
        throw new BookMetadataProviderException('Metadata response exceeded the size limit.');
    }
    if ($success === false) {
        throw new BookMetadataProviderException(
            $errorNumber === CURLE_OPERATION_TIMEDOUT ? 'Metadata lookup timed out.' : 'Metadata provider connection failed.'
        );
    }
    if ($httpCode !== 200) {
        throw new BookMetadataProviderException('Metadata provider returned HTTP ' . $httpCode . '.');
    }
    return book_metadata_decode($body);
}

function book_metadata_decode(string $body): array
{
    try {
        $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new BookMetadataProviderException('Metadata provider returned invalid JSON.', 0, $e);
    }
    if (!is_array($data) || !str_starts_with(ltrim($body), '{')) {
        throw new BookMetadataProviderException('Metadata provider returned a non-object response.');
    }
    return $data;
}

function book_metadata_lookup(string $input, ?callable $request = null, ?string $apiKey = null): array
{
    $isbn = book_isbn_normalize($input);
    if ($isbn === null) {
        return ['status' => 'invalid_isbn', 'metadata' => null, 'message' => 'Please check the ISBN. Enter a valid ISBN-10 or ISBN-13, or enter your book manually.'];
    }
    $request ??= 'book_metadata_http';
    $apiKey ??= getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    $parameters = ['q' => 'isbn:' . $isbn, 'maxResults' => 5];
    if ($apiKey !== '') {
        $parameters['key'] = $apiKey;
    }
    $providers = [
        'Google Books' => [
            'url' => 'https://www.googleapis.com/books/v1/volumes?' . http_build_query($parameters),
            'parse' => 'book_metadata_google',
        ],
        'Open Library' => [
            'url' => 'https://openlibrary.org/api/books?' . http_build_query([
                'bibkeys' => 'ISBN:' . $isbn, 'format' => 'json', 'jscmd' => 'data',
            ]),
            'parse' => 'book_metadata_open_library',
        ],
    ];
    $failures = 0;
    foreach ($providers as $name => $provider) {
        try {
            $metadata = $provider['parse']($request($provider['url']), $isbn);
            if ($metadata !== null) {
                return [
                    'status' => 'success',
                    'metadata' => $metadata,
                    'message' => 'Book details found with ' . $name . '. Review and correct them below.',
                ];
            }
        } catch (BookMetadataProviderException $e) {
            $failures++;
            // Provider-only diagnostics; never log URLs, API keys or form contents.
            error_log($name . ' lookup: ' . $e->getMessage());
        }
    }
    return [
        'status' => $failures > 0 ? 'api_error' : 'not_found',
        'metadata' => null,
        'message' => $failures > 0
            ? 'A book service is unavailable or returned unusable data. We could not confirm a match. You can try again later or enter details manually.'
            : 'No matching book was found. You can still enter your book details manually.',
    ];
}
