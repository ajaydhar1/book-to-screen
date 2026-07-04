<?php

declare(strict_types=1);

define('DB_PATH', __DIR__ . '/../data/adaptations.sqlite');
define('DEADLINE_RSS_URL', 'https://deadline.com/feed/');
define('TIMEZONE', 'America/New_York');

date_default_timezone_set(TIMEZONE);

define('ADMIN_USERNAME', 'taylorswift');
define('ADMIN_PASSWORD_HASH', '$2y$10$vATI.5xKmaVt85aN8ZXc9e2X4jd0jWu/7OBjupCHo/nvBnSe6iTB.');