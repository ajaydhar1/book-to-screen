<?php

declare(strict_types=1);

require_once __DIR__ . '/local-only.php';
require_once __DIR__ . '/../includes/book-metadata.php';

header('Content-Type: application/json; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

function prototype_lookup_response(int $code, string $status, string $message): never
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'metadata' => null, 'message' => $message], JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    prototype_lookup_response(405, 'invalid_request', 'Only ISBN lookup requests are accepted.');
}

if (count(explode('&', $_SERVER['QUERY_STRING'] ?? '')) !== 1
    || array_keys($_GET) !== ['isbn'] || !is_string($_GET['isbn']) || strlen($_GET['isbn']) > 40) {
    prototype_lookup_response(400, 'invalid_request', 'Provide only a single ISBN.');
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 || file_get_contents('php://input', false, null, 0, 1) !== '') {
    prototype_lookup_response(400, 'invalid_request', 'Lookup requests must not contain a request body.');
}

$result = isset($bookMetadataLookup) ? $bookMetadataLookup($_GET['isbn']) : book_metadata_lookup($_GET['isbn']);
http_response_code(match ($result['status']) {
    'invalid_isbn' => 422,
    'api_error' => 502,
    default => 200,
});
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
