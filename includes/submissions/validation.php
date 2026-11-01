<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../book-metadata.php';

const SUBMISSION_CONSENT_VERSION = 'local-pilot-2026-11-01';
const SUBMISSION_PRIVACY_VERSION = 'local-pilot-2026-11-01';

function submission_validate(array $input): array
{
    $limits = [
        'isbn' => 40, 'title' => 300, 'authors' => 2000, 'subtitle' => 500, 'publisher' => 300,
        'publication_date' => 100, 'genre' => 500, 'subjects' => 1000, 'description' => 5000,
        'pitch' => 1000, 'screen-format' => 20, 'submitter-name' => 200, 'email' => 254,
        'role' => 20, 'authorization' => 20, 'authority-details' => 500,
        'rights-controller' => 20, 'rights-name' => 300, 'arrangement' => 20,
        'arrangement-details' => 1000, 'website' => 500,
    ];
    $errors = [];
    $values = [];
    foreach ($limits as $name => $limit) {
        $value = $input[$name] ?? '';
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")
            || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $limit) {
            $errors[$name] = 'Please provide valid text within the field length limit.';
            $values[$name] = '';
        } else {
            $values[$name] = trim($value);
        }
    }
    foreach (['title','authors','pitch','submitter-name','email','role','authorization'] as $required) {
        if ($values[$required] === '') {
            $errors[$required] = 'This field is required.';
        }
    }
    $enums = [
        'screen-format' => ['unknown','film','tv','limited'],
        'role' => ['author','agent','rights-holder','scout'],
        'authorization' => ['owner','representative','recommendation','unknown'],
        'rights-controller' => ['unknown','self','author','agent','publisher','other'],
        'arrangement' => ['unknown','none','discussions','shopping','option','sale','previous','other'],
    ];
    foreach ($enums as $name => $allowed) {
        if (!in_array($values[$name], $allowed, true)) {
            $errors[$name] = 'Please choose a listed option.';
        }
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $values['email'])) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $isbn = $values['isbn'] === '' ? null : book_isbn_normalize($values['isbn']);
    if ($values['isbn'] !== '' && $isbn === null) {
        $errors['isbn'] = 'Please correct the ISBN or use manual entry.';
    }
    if ($values['website'] !== '' && (!filter_var($values['website'], FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($values['website'], PHP_URL_SCHEME)), ['http','https'], true)
        || parse_url($values['website'], PHP_URL_USER) !== null)) {
        $errors['website'] = 'Use a public http:// or https:// URL without credentials.';
    }
    foreach (['consent','privacy'] as $consent) {
        if (($input[$consent] ?? false) !== true) {
            $errors[$consent] = 'Please acknowledge this statement.';
        }
    }
    $book = array_intersect_key($values, array_flip(['title','authors','subtitle','publisher','publication_date','description']));
    $book['isbn'] = $isbn;
    foreach (['genre' => 'genres', 'subjects' => 'subjects'] as $field => $key) {
        $terms = [];
        foreach (explode(',', $values[$field]) as $term) {
            $term = trim($term);
            if ($term !== '') {
                $normalized = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
                $terms[$normalized] ??= $term;
            }
        }
        $book[$key] = array_values($terms);
    }
    $provenance = $input['provenance'] ?? [];
    if (!is_array($provenance) || array_is_list($provenance) && $provenance !== []) {
        $errors['provenance'] = 'Invalid metadata provenance.';
        $provenance = [];
    }
    $book['provenance'] = [];
    foreach ($provenance as $field => $origin) {
        if (!in_array($field, ['title','authors','subtitle','publisher','publication_date','genre','subjects','description'], true) || !is_array($origin)
            || !in_array($origin['provider'] ?? null, ['Google Books','Open Library'], true)
            || !in_array($origin['state'] ?? null, ['suggested','edited','removed'], true)) {
            $errors['provenance'] = 'Invalid metadata provenance.';
        } else {
            $book['provenance'][$field] = ['provider' => $origin['provider'], 'state' => $origin['state']];
        }
    }
    $values['authority-details'] = $values['authorization'] === 'representative' ? $values['authority-details'] : '';
    $values['rights-name'] = in_array($values['rights-controller'], ['unknown','self'], true) ? '' : $values['rights-name'];
    $values['arrangement-details'] = in_array($values['arrangement'], ['unknown','none'], true) ? '' : $values['arrangement-details'];
    if ($errors !== []) {
        throw new SubmissionInputException($errors);
    }
    $private = array_diff_key($values, array_flip(['isbn','title','authors','subtitle','publisher','publication_date','genre','subjects','description']));
    $private['email'] = strtolower($private['email']);
    return ['book' => $book, 'private' => $private];
}
