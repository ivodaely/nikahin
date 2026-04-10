<?php
// backend/api/upload.php

require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

// Resolves to htdocs/nikahin/uploads/
define('UPLOAD_DIR', realpath(__DIR__ . '/../../') . '/uploads/');

// Derive upload URL from the actual request — works in any subdirectory
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$uri      = strtok($_SERVER['REQUEST_URI'], '?');
$apiPos   = strpos($uri, '/api/');
$basePath = $apiPos !== false ? substr($uri, 0, $apiPos) : '';
define('UPLOAD_URL', $scheme . '://' . $host . $basePath . '/uploads/');

define('MAX_SIZE', 5 * 1024 * 1024); // 5 MB
$ALLOWED = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') json_error('Method not allowed', 405);

$u = require_auth();

if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        error_log('[nikahin] Failed to create upload dir: ' . UPLOAD_DIR);
        json_error('Upload folder could not be created. Check folder permissions.', 500);
    }
}
if (!is_writable(UPLOAD_DIR)) {
    error_log('[nikahin] Upload dir not writable: ' . UPLOAD_DIR);
    json_error('Upload folder is not writable. Run: chmod 755 uploads/', 500);
}

$file = $_FILES['file'] ?? null;
if (!$file) json_error('No file received.');

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
    UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
    UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
    UPLOAD_ERR_NO_FILE    => 'No file uploaded',
    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
    UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
];
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_error($uploadErrors[$file['error']] ?? 'Upload error code: ' . $file['error']);
}

if ($file['size'] > MAX_SIZE) json_error('File too large (max 5 MB)');

$mime = mime_content_type($file['tmp_name']);
if (!isset($ALLOWED[$mime])) {
    json_error('Invalid file type "' . $mime . '". Only JPEG, PNG, WebP, GIF allowed.');
}

$ext      = $ALLOWED[$mime];
$filename = sprintf('%s_%s.%s', $u['user_id'], bin2hex(random_bytes(8)), $ext);
$dest     = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    error_log('[nikahin] move_uploaded_file failed: ' . $dest);
    json_error('Failed to save file.', 500);
}

json_ok(['url' => UPLOAD_URL . $filename, 'filename' => $filename]);