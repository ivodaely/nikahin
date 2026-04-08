<?php
// backend/index.php  – Main router

define('APP_URL', getenv('APP_URL') ?: 'http://localhost');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';

// Parse path: /api/{resource}/{...}
$uri  = strtok($_SERVER['REQUEST_URI'], '?');
$path = array_values(array_filter(explode('/', $uri)));
// e.g. ['api', 'auth', 'login']  OR  ['api', 'invitation', '5', 'publish']

if (!isset($path[0]) || $path[0] !== 'api') {
    json_error('Not found', 404);
}

$resource = $path[1] ?? '';

switch ($resource) {
    case 'auth':       require __DIR__ . '/api/auth.php';       break;
    case 'invitation': require __DIR__ . '/api/invitation.php'; break;
    case 'ai':         require __DIR__ . '/api/ai.php';         break;
    case 'guest':      require __DIR__ . '/api/guest.php';      break;
    case 'upload':     require __DIR__ . '/api/upload.php';     break;
    default:           json_error('Resource not found', 404);
}
