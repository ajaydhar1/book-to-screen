<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/turnstile-config.php';
try {
    if (count($argv) !== 2) throw new RuntimeException('Supply only a private configuration path.');
    $config = submission_turnstile_private_config($argv[1]);
    if ($config['site_key'] === '' || $config['secret_key'] === '') {
        echo "CONFIG_INCOMPLETE: enter existing widget keys privately. No activation or network.\n";
        exit(2);
    }
    echo "CONFIG_COMPATIBLE_OFFLINE: key syntax and declared widget settings match; ownership/Managed mode and Siteverify unverified. No activation or network.\n";
} catch (Throwable) {
    fwrite(STDERR, "Turnstile configuration check stopped. Inspect private fields/permissions; details suppressed.\n");
    exit(1);
}
