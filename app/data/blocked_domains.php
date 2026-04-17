<?php

/**
 * List of blocked domains
 *
 * Only the deployment host and loopback are blocked to prevent SSRF against the app itself.
 */

$host = parse_url(defined('SITE_URL') ? SITE_URL : '', PHP_URL_HOST);
return array_filter([
    $host,
    'localhost',
    '127.0.0.1',
]);
