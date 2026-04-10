<?php
// backend/index.php

// Must be FIRST: buffer all output so PHP notices/warnings never corrupt JSON
ob_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// AI calls to Anthropic can take 10-30 seconds — extend execution time
set_time_limit(120);

define('APP_URL', getenv('APP_URL') ?: 'http://localhost');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean(); http_response_code(204); exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';

// Works at root /api/... AND subdirectory /nikahin/api/...
$uri      = strtok($_SERVER['REQUEST_URI'], '?');
$segments = array_values(array_filter(explode('/', $uri)));
$apiIdx   = array_search('api', $segments);
if ($apiIdx === false) { ob_end_clean(); json_error('Not found', 404); }

$path     = array_values(array_slice($segments, $apiIdx));
$resource = $path[1] ?? '';

try {
    switch ($resource) {
        case 'auth':       require __DIR__ . '/api/auth.php';       break;
        case 'invitation': require __DIR__ . '/api/invitation.php'; break;
        case 'ai':         require __DIR__ . '/api/ai.php';         break;
        case 'guest':      require __DIR__ . '/api/guest.php';      break;
        case 'upload':     require __DIR__ . '/api/upload.php';     break;
        default:           ob_end_clean(); json_error('Resource not found', 404);
    }
} catch (Throwable $e) {
    error_log('[nikahin] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ob_end_clean();
    json_error('Server error: ' . $e->getMessage(), 500);
}