<?php

declare(strict_types=1);

define('DB_PATH', __DIR__ . '/../data/adaptations.sqlite');
define('DEADLINE_RSS_URL', 'https://deadline.com/feed/');
define('TIMEZONE', 'America/New_York');

date_default_timezone_set(TIMEZONE);