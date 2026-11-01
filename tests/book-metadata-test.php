<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/book-metadata.php';

$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
}

function expect_provider_error(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (BookMetadataProviderException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

$isbn = '9780140328721';
$googleBook = [
    'id' => 'sample-volume',
    'volumeInfo' => [
        'title' => 'Fantastic Mr. Fox',
        'authors' => ['Roald Dahl'],
        'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '0140328726']],
        'subtitle' => 'A sample subtitle',
        'publisher' => 'Sample Publisher',
        'publishedDate' => '1988',
        'categories' => ['Fiction', 'Children'],
        'description' => '<p>A story &amp; a possibility.</p>',
    ],
];
$google = ['totalItems' => 1, 'items' => [$googleBook]];
$openLibrary = [
    'ISBN:' . $isbn => [
        'title' => 'Fantastic Mr. Fox',
        'authors' => [['name' => 'Roald Dahl']],
        'identifiers' => ['isbn_13' => [$isbn], 'openlibrary' => ['OL123M']],
        'publishers' => [['name' => 'Sample Publisher']],
        'publish_date' => '1988',
        'subjects' => [['name' => 'Fiction']],
    ],
];

check(book_isbn_normalize('0-14-032872-6') === $isbn, 'ISBN-10 conversion');
check(book_isbn_normalize('978 0 14 032872 1') === $isbn, 'ISBN-13 normalization');
check(book_isbn_normalize('080442957x') === '9780804429573', 'ISBN-10 X check digit');
check(book_isbn_normalize('9791090636071') === '9791090636071', '979 ISBN accepted');
foreach (['', '9780140328722', '0140328727', '1234567890128', '01403287X6', '9780140328721<script>', '9780140328721extra'] as $invalid) {
    check(book_isbn_normalize($invalid) === null, 'Reject invalid ISBN ' . $invalid);
}

$requests = [];
$transport = static function (string $url) use (&$requests, $google): array {
    $requests[] = $url;
    return $google;
};
$success = book_metadata_lookup('0140328726', $transport, 'test-key');
check($success['status'] === 'success', 'Google ISBN-10 lookup');
check(count($requests) === 1, 'No fallback after Google success');
check(str_contains($requests[0], 'key=test-key'), 'Server key supplied');
parse_str((string) parse_url($requests[0], PHP_URL_QUERY), $params);
check($params['q'] === 'isbn:' . $isbn, 'Only canonical ISBN queried');
check(array_keys($params) === ['q', 'maxResults', 'key'], 'No private form fields sent');
check($success['metadata']['description'] === 'A story & a possibility.', 'HTML removed from description');
check($success['metadata']['publication_date'] === '1988', 'Partial dates retained');
check($success['metadata']['genres'] === [] && $success['metadata']['subjects'] === [], 'Generic fiction and children do not imply a genre');
check($success['metadata']['provider_terms'] === ['Fiction', 'Children'], 'Original sanitized provider terms retained in response');
check($success['metadata']['source_url'] === 'https://books.google.com/books?id=sample-volume', 'Provider provenance');
$isbn13Success = book_metadata_lookup($isbn, static fn(string $url): array => $google, '');
check($isbn13Success['metadata'] === $success['metadata'], 'ISBN-10 and ISBN-13 produce identical records');

$requests = [];
$invalid = book_metadata_lookup('9780140328722', $transport, '');
check($invalid['status'] === 'invalid_isbn' && $requests === [], 'Invalid checksum never reaches provider');

$requests = [];
$fallback = book_metadata_lookup($isbn, static function (string $url) use (&$requests, $openLibrary): array {
    $requests[] = $url;
    return count($requests) === 1 ? ['totalItems' => 0] : $openLibrary;
}, '');
check($fallback['status'] === 'success', 'Open Library fallback success');
check($fallback['metadata']['provider'] === 'Open Library', 'Correct fallback provider');
check($fallback['metadata']['authors'] === ['Roald Dahl'], 'Open Library named authors normalized');
check($fallback['metadata']['description'] === null, 'Unavailable description stays null');
check(array_keys($fallback['metadata']) === array_keys($success['metadata']), 'Providers share output shape');
check(count($requests) === 2, 'At most one request per provider');

$noResult = book_metadata_lookup($isbn, static fn(string $url): array => str_contains($url, 'googleapis')
    ? ['totalItems' => 0] : [], '');
check($noResult['status'] === 'not_found' && $noResult['metadata'] === null, 'No-result response');
$timeoutFallback = book_metadata_lookup($isbn, static function (string $url) use ($openLibrary): array {
    if (str_contains($url, 'googleapis')) {
        throw new BookMetadataProviderException('Metadata lookup timed out.');
    }
    return $openLibrary;
}, '');
check($timeoutFallback['status'] === 'success', 'Provider timeout allows fallback');
$error = book_metadata_lookup($isbn, static function (string $url): array {
    throw new BookMetadataProviderException('Metadata provider returned HTTP 429.');
}, '');
check($error['status'] === 'api_error', 'Provider errors visible');
$mixed = book_metadata_lookup($isbn, static function (string $url): array {
    if (str_contains($url, 'googleapis')) {
        throw new BookMetadataProviderException('Metadata lookup timed out.');
    }
    return [];
}, '');
check($mixed['status'] === 'api_error', 'Failure plus no result is not a definitive not-found');

$incomplete = ['items' => [[
    'volumeInfo' => ['title' => 'Title only', 'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => $isbn]]],
]]];
$minimal = book_metadata_google($incomplete, $isbn);
check($minimal !== null && $minimal['authors'] === [] && $minimal['publisher'] === null, 'Missing optional metadata tolerated');
check($minimal['provider_id'] === null && $minimal['source_url'] === null, 'Missing provider identity tolerated');
check($minimal['genres'] === [] && $minimal['subjects'] === [] && $minimal['provider_terms'] === [], 'Missing classifications remain optional');
$malformedFields = $google;
$malformedFields['items'][0]['volumeInfo']['authors'] = ['Valid Name', ['bad'], null];
$malformedFields['items'][0]['volumeInfo']['publisher'] = ['unexpected'];
$malformedFields['items'][0]['volumeInfo']['description'] = 42;
$sanitized = book_metadata_google($malformedFields, $isbn);
check($sanitized['authors'] === ['Valid Name'] && $sanitized['publisher'] === null && $sanitized['description'] === null, 'Malformed optional values omitted');
$wrong = $googleBook;
$wrong['volumeInfo']['industryIdentifiers'][0]['identifier'] = '9780804429573';
check(book_metadata_google(['items' => [$wrong, $googleBook]], $isbn)['title'] === 'Fantastic Mr. Fox', 'Skip wrong edition before matching result');
check(book_metadata_google(['items' => [$wrong]], $isbn) === null, 'Wrong ISBN never accepted');
$missingIds = $google;
unset($missingIds['items'][0]['volumeInfo']['industryIdentifiers']);
check(book_metadata_google($missingIds, $isbn) === null, 'Google missing identifiers not accepted');
$wrongOl = $openLibrary;
$wrongOl['ISBN:' . $isbn]['identifiers']['isbn_13'] = ['9780804429573'];
check(book_metadata_open_library($wrongOl, $isbn) === null, 'Open Library key alone is not enough');
expect_provider_error(static fn() => book_metadata_google(['items' => 'bad'], $isbn), 'Malformed Google items');
expect_provider_error(static fn() => book_metadata_google(['items' => ['not-a-volume']], $isbn), 'Malformed Google volume records');
expect_provider_error(static fn() => book_metadata_google(['items' => ['unexpected' => $googleBook]], $isbn), 'Non-list Google items');
expect_provider_error(static fn() => book_metadata_google(['error' => ['code' => 429]], $isbn), 'Google API error envelope');
expect_provider_error(static fn() => book_metadata_google(['unexpected' => true], $isbn), 'Unexpected Google envelope');
expect_provider_error(static fn() => book_metadata_open_library(['ISBN:' . $isbn => 'bad'], $isbn), 'Malformed Open Library data');
foreach (['not-json', 'null', '[]', '{"broken":', '{"items":' . str_repeat('[', 35) . '0' . str_repeat(']', 35) . '}'] as $body) {
    expect_provider_error(static fn() => book_metadata_decode($body), 'Malformed JSON rejected');
}
check(book_metadata_decode('{}') === [], 'Empty object supports no-result response');

$classification = book_metadata_classification([
    'Fiction / Science Fiction / General', 'Sci-fi', 'SCIENCE-FICTION',
    'Young adult fiction', 'Fantasy fiction', 'Historical novels', 'Memoirs',
    'Romantic fiction', 'Mystery and detective stories', 'Thrillers', 'Horror tales',
    'Literary fiction', 'Non-fiction', 'Survival', 'survival', '  Survival  ',
    'Friendship', 'Juvenile fiction', 'Open Library Staff Picks', 'Protected DAISY',
    'Large type books', 'BISAC: FIC028000', 'PS3603.O4558 H86', '813.6',
    'Fiction -- Survival -- Juvenile fiction', '2000-2010',
]);
check($classification['genres'] === [
    'Science Fiction', 'Young Adult', 'Fantasy', 'Historical Fiction', 'Memoir / Biography',
    'Romance', 'Mystery', 'Thriller', 'Horror', 'Literary Fiction', 'Nonfiction',
], 'Recognizable aliases map to canonical genres without duplicates');
check($classification['subjects'] === ['Survival', 'Friendship'], 'Case/whitespace duplicates and classification noise removed');
check(in_array('Protected DAISY', $classification['provider_terms'], true), 'Original noise retained only in internal response terms');
check(book_metadata_classification(['Romance languages', 'History', 'Literature', 'Dystopian', 'Magic'])['genres'] === [], 'Ambiguous topics are not inferred as genres');
check(book_metadata_classification(['Juvenile fiction'])['genres'] === [], 'Juvenile does not imply Young Adult');
check(book_metadata_classification(null) === ['genres' => [], 'subjects' => [], 'provider_terms' => []], 'Missing provider terms');
check(book_metadata_classification(['bad' => null, ['unexpected'], 123])['provider_terms'] === [], 'Malformed terms ignored');
$manyTerms = array_merge(
    ['Fiction', 'Survival', 'survival', 'Protected DAISY', 'Open Library Staff Picks', 'PS123.4'],
    array_map(static fn(int $i): string => 'Theme ' . $i, range(1, 30)),
    ['Science fiction', 'Young adult fiction', 'SCI-FI']
);
$manyOl = $openLibrary;
$manyOl['ISBN:' . $isbn]['subjects'] = array_map(static fn(string $term): array => ['name' => $term], $manyTerms);
$manageable = book_metadata_open_library($manyOl, $isbn);
check($manageable['genres'] === ['Science Fiction', 'Young Adult'], 'Genres found even after first twenty Open Library subjects');
check(count($manageable['subjects']) === 8 && $manageable['subjects'][0] === 'Survival', 'Many subjects limited to eight useful unique topics');
check($manageable['provider_terms'] === $manyTerms, 'Original terms available in memory, not form');
check(count(book_metadata_classification(array_fill(0, 150, 'Survival'))['provider_terms']) === 100, 'Internal term preservation bounded');
$hierarchicalGoogle = $google;
$hierarchicalGoogle['items'][0]['volumeInfo']['categories'] = ['Young Adult Fiction / Science Fiction / Survival', 'Sci-fi'];
$normalizedGoogle = book_metadata_google($hierarchicalGoogle, $isbn);
check($normalizedGoogle['genres'] === ['Young Adult', 'Science Fiction'] && $normalizedGoogle['subjects'] === ['Survival'], 'Google taxonomy uses same normalization');
check(book_metadata_classification(['Fiction / Historical / General', 'Fiction / Literary', 'Fiction / Mystery & Detective', 'Fiction / Thrillers & Suspense'])['genres']
    === ['Historical Fiction', 'Literary Fiction', 'Mystery', 'Thriller'], 'Common Google genre hierarchy labels normalized');
check(book_metadata_classification(['Historical', 'Literary'])['genres'] === [], 'Historical and literary topics require fiction taxonomy context');
$titleOnly = $incomplete;
$titleOnly['items'][0]['volumeInfo']['title'] = 'A Fantasy Romance Mystery';
$titleOnly['items'][0]['volumeInfo']['description'] = 'A thriller with horror and science fiction.';
check(book_metadata_google($titleOnly, $isbn)['genres'] === [], 'Title and description never drive genre guesses');

$page = file_get_contents(__DIR__ . '/../submission-prototype/index.php');
$js = file_get_contents(__DIR__ . '/../assets/js/submission-prototype.js');
$endpoint = file_get_contents(__DIR__ . '/../submission-prototype/lookup.php');
check(!str_contains($page, '<form'), 'No submittable HTML form');
check(preg_match('/<button(?![^>]*type="button")/i', $page) === 0, 'All buttons non-submit');
check(!str_contains($page, 'name="email"'), 'Private fields have no form names');
check(preg_match('/session_start|localStorage|sessionStorage|indexedDB|sendBeacon|document\.cookie/', $js . $page . $endpoint) === 0, 'No persistence or transmission helpers');
check(substr_count($js, 'fetch(') === 1 && str_contains($js, 'new URLSearchParams({ isbn })'), 'Only ISBN lookup is fetched');
check(!str_contains($page . $endpoint, '/includes/db.php'), 'No database connection');
check(str_contains($page, "form-action 'none'"), 'CSP prevents native submission');
echo 'PASS: ' . $checks . ' checks (mocked providers; no external requests).' . PHP_EOL;
