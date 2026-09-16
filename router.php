<?php

// Router for PHP's built-in dev server (php -S ... -t public router.php).
//
// The docroot is public/, so static assets and index.php are served
// straight from there. The api/ directory lives outside the docroot (it's
// a sibling of public/, not public/api/), so requests to /api/* are routed
// here explicitly.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($path, '/api/')) {
    $script = __DIR__ . $path;

    if (is_file($script)) {
        require $script;

        return true;
    }

    http_response_code(404);

    return true;
}

// Not an /api/ request — let the built-in server serve it from the docroot
// (public/) as usual.
return false;
