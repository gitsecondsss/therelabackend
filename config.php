<?php
// config.php

define('TOKEN_SECRET', 'ENZBzBul6cfevzAENZBzBul6cfevzA'); // keep secret
define('TOKEN_TTL', 300); // 5 minutes
define('ALLOWED_ORIGINS', [
    'https://portalaccess.zoholandingpage.com',
]);

define('RATE_LIMIT_DIR', __DIR__ . '/data/ratelimit');
