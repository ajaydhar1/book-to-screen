<?php

declare(strict_types=1);

require_once __DIR__ . '/../book-metadata.php';

function submission_cached_book_lookup(string $input, string $path, ?callable $lookup = null, ?int $now = null): array
{
    $isbn = book_isbn_normalize($input);
    if ($isbn === null) {
        return book_metadata_lookup($input);
    }
    $now ??= time();
    $directory = realpath(dirname($path));
    $root = realpath(__DIR__ . '/../..');
    if ($directory === false || str_starts_with(strtolower($directory . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))
        || basename($path) !== 'submissions-isbn-cache.json' || is_link($path)) {
        throw new RuntimeException('Use the dedicated bibliographic cache outside the web root.');
    }
    $file = fopen($path, 'c+b');
    if ($file === false) throw new RuntimeException('Could not open the bibliographic cache.');
    try {
        // One bounded shared cache also prevents simultaneous provider requests for the same edition.
        if (!flock($file, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Bibliographic cache is busy. Please retry lookup shortly.');
        }
        $body = stream_get_contents($file, 2097153);
        if ($body === false || strlen($body) > 2097152) throw new RuntimeException('Bibliographic cache is invalid.');
        $entries = $body === '' ? [] : json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($entries)) throw new RuntimeException('Bibliographic cache is invalid.');
        foreach ($entries as $key => $entry) {
            if (!is_array($entry) || !is_int($entry['expires'] ?? null) || $entry['expires'] <= $now) unset($entries[$key]);
        }
        if (isset($entries[$isbn])) return $entries[$isbn]['result'];
        $result = ($lookup ?? 'book_metadata_lookup')($isbn);
        if (in_array($result['status'] ?? null, ['success', 'not_found'], true)) {
            $entries[$isbn] = ['expires' => $now + ($result['status'] === 'success' ? 21600 : 600), 'result' => $result];
            $entries = array_slice($entries, -64, null, true);
            $json = json_encode($entries, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            if (strlen($json) > 2097152) throw new RuntimeException('Bibliographic cache capacity exceeded.');
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                throw new RuntimeException('Could not update the bibliographic cache.');
            }
        }
        return $result;
    } finally {
        fclose($file);
    }
}
