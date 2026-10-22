<?php
/**
 * Unit tests run without WordPress. Plugin files exit unless ABSPATH is
 * defined (direct-access guard), so define it for the test run.
 */

define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__, 2) . '/vendor/autoload.php';
