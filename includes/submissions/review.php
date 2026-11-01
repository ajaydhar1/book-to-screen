<?php

declare(strict_types=1);

const SUBMISSION_REVIEW_STATUSES = [
    'new' => 'New', 'reviewing' => 'Under review', 'needs_information' => 'Needs information',
    'research_candidate' => 'Research candidate', 'closed' => 'Closed',
    'duplicate' => 'Duplicate', 'spam' => 'Suspected spam',
];
const SUBMISSION_EVALUATION_FIELDS = [
    'premise' => 'Strength of the premise',
    'conflict' => 'Central conflict',
    'characters' => 'Character development potential',
    'visual' => 'Visual / cinematic qualities',
    'format' => 'Possible film or television format',
    'distinctiveness' => 'Distinctiveness of the source material',
    'completeness' => 'Completeness of available information',
    'rights_questions' => 'Outstanding rights / authorization questions',
];

function submission_review_filters(array $input): array
{
    $defaults = ['status' => 'inbox', 'verification' => 'verified', 'role' => '', 'genre' => '',
        'search' => '', 'from' => '', 'to' => '', 'sort' => 'priority', 'page' => '1'];
    $filters = [];
    foreach ($defaults as $key => $default) {
        $value = $input[$key] ?? $default;
        if (!is_string($value) || strlen($value) > 300 || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) {
            throw new SubmissionInputException([$key => 'Use a valid filter value.']);
        }
        $filters[$key] = trim($value);
    }
    if (!in_array($filters['status'], ['inbox','all','pending_email', ...array_keys(SUBMISSION_REVIEW_STATUSES)], true)
        || !in_array($filters['verification'], ['verified','unverified','all'], true)
        || !in_array($filters['role'], ['','author','agent','rights-holder','scout'], true)
        || !in_array($filters['sort'], ['priority','oldest','newest','title'], true)) {
        throw new SubmissionInputException(['filters' => 'Choose listed status, verification, role and sort values.']);
    }
    foreach (['from','to'] as $field) {
        if ($filters[$field] === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$field], new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $filters[$field]) {
            throw new SubmissionInputException([$field => 'Choose a valid date (YYYY-MM-DD).']);
        }
    }
    if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
        throw new SubmissionInputException(['dates' => 'Start date must not follow the end date.']);
    }
    if (!ctype_digit($filters['page']) || (int) $filters['page'] < 1 || (int) $filters['page'] > 1000000) {
        throw new SubmissionInputException(['page' => 'Choose a valid page number.']);
    }
    $filters['page'] = (int) $filters['page'];
    return $filters;
}

function submission_review_where(array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['status'] === 'inbox') {
        // Inbox verification cannot be overridden by a query-string filter.
        $where[] = "s.verified_at IS NOT NULL AND s.review_status IN ('new','reviewing','needs_information','research_candidate')";
    } elseif ($filters['status'] !== 'all') {
        $where[] = 's.review_status = :status';
        $params['status'] = $filters['status'];
    }
    if ($filters['verification'] !== 'all') {
        $where[] = 's.verified_at IS ' . ($filters['verification'] === 'verified' ? 'NOT ' : '') . 'NULL';
    }
    if ($filters['role'] !== '') {
        $where[] = 's.submitter_role = :role';
        $params['role'] = $filters['role'];
    }
    if ($filters['genre'] !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM submission_genres g WHERE g.submission_id = s.id AND g.genre_key = :genre)';
        $params['genre'] = mb_strtolower($filters['genre'], 'UTF-8');
    }
    if ($filters['search'] !== '') {
        $where[] = "(s.book_title LIKE :search ESCAPE '\\' OR s.book_authors LIKE :search ESCAPE '\\' OR s.isbn LIKE :search ESCAPE '\\')";
        $params['search'] = '%' . strtr($filters['search'], ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    }
    foreach (['from','to'] as $field) {
        if ($filters[$field] !== '') {
            $where[] = 's.created_at ' . ($field === 'from' ? '>= :from' : '< :to');
            $date = new DateTimeImmutable($filters[$field], new DateTimeZone('UTC'));
            $params[$field] = ($field === 'to' ? $date->modify('+1 day') : $date)->getTimestamp();
        }
    }
    return [$where === [] ? '1 = 1' : implode(' AND ', $where), $params];
}

function submission_evaluation_validate(array $input): array
{
    $result = [];
    if (array_diff(array_keys($input), array_keys(SUBMISSION_EVALUATION_FIELDS)) !== []) {
        throw new SubmissionInputException(['evaluation' => 'Use only the listed evaluation criteria.']);
    }
    foreach (SUBMISSION_EVALUATION_FIELDS as $field => $label) {
        $value = $input[$field] ?? '';
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")
            || mb_strlen($value, 'UTF-8') > 1000) {
            throw new SubmissionInputException(['evaluation' => 'Each observation must be valid text within 1,000 characters.']);
        }
        $result[$field] = trim($value);
    }
    return $result;
}
