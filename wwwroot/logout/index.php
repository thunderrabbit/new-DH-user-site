<?php

declare(strict_types=1);

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Auth\IsLoggedIn $is_logged_in
 */

// Logging out revokes every session the user has, so it takes a POST, and
// prepend.php has already rejected a POST without the CSRF token. A GET (a
// link, an <img>, a redirect from another site) logs nobody out.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $is_logged_in->logout();
}
header(header: "Location: /");
exit;
