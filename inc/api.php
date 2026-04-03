<?php
/**
 * ExamplePress REST API
 *
 * Loader for the REST API endpoint modules. Each domain has its own
 * file under inc/api/ for maintainability.
 */

require_once __DIR__ . '/api/apps.php';
require_once __DIR__ . '/api/connections.php';
require_once __DIR__ . '/api/demo.php';
require_once __DIR__ . '/api/updater.php';
require_once __DIR__ . '/api/filesystem.php';
