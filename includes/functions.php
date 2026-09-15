<?php

declare(strict_types=1);

function format_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);

    if ($date === false) {
        return $datetime;
    }

    return $date->format('M j, Y, g:i A');
}

function h(string|null $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function status_class(string $status): string
{
    return match ($status) {
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'ignored' => 'status-ignored',
        'flagged' => 'status-flagged',
        default => 'status-default',
    };
}

function filter_url(string $status, string $researcher = 'all', string $search = ''): string
{
    $params = ['status' => $status];

    if ($researcher !== 'all') {
        $params['researcher'] = $researcher;
    }

    if ($search !== '') {
        $params['search'] = $search;
    }

    return '?' . http_build_query($params);
}

function formatDate(string|null $value): string
{
    if (!$value) {
        return 'Unknown';
    }

    try {
        $date = new DateTime($value);
        return $date->format('M j, Y, g:i A');
    } catch (Exception) {
        return h($value);
    }
}