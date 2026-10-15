<?php
/**
 * Router for PHP's built-in development server. Start it with:
 *     php -S localhost:8080 -t php-admin php-admin/router.php
 * It blocks the folders that must never be reachable by URL, then lets the server handle everything else.
 * (On Apache, the .htaccess files in those folders do the same job.)
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (preg_match('~(^|/)(config|includes|bin)(/|$)~i', $path) || preg_match('~(^|/)\.~', $path) || preg_match('~/_form\.php$~', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden');
}
return false;   // serve the requested file / script normally
