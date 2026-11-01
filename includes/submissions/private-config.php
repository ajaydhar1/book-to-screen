<?php

declare(strict_types=1);

function submission_private_config_path(string $path, bool $excludeParentDirectory = true): string
{
    $resolved = realpath($path);
    $webRoot = realpath(__DIR__ . ($excludeParentDirectory ? '/../../..' : '/../..'));
    $supplied = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if ($resolved === false || is_link($path) || $webRoot === false
        || str_starts_with(strtolower($resolved) . DIRECTORY_SEPARATOR, strtolower($webRoot) . DIRECTORY_SEPARATOR)
        || str_starts_with(strtolower($supplied) . DIRECTORY_SEPARATOR, strtolower($webRoot) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Private configuration must exist outside the repository and its parent web directory.');
    }
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($documentRoot !== false
        && (str_starts_with(strtolower($resolved) . DIRECTORY_SEPARATOR, strtolower($documentRoot) . DIRECTORY_SEPARATOR)
            || str_starts_with(strtolower($supplied) . DIRECTORY_SEPARATOR, strtolower($documentRoot) . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Private configuration must be outside the active document root.');
    }
    if (DIRECTORY_SEPARATOR !== '\\' && (fileperms($resolved) & 0077) !== 0) {
        throw new RuntimeException('Restrict private configuration permissions to the current user.');
    }
    return $resolved;
}
