<?php
// backend/api/upload.php

require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

// ── Upload directory ──────────────────────────────────────────────────────────
// Resolves to:  htdocs/nikahin/uploads/
define('UPLOAD_DIR', realpath(__DIR__ . '/../../') . '/uploads/');

// ── Upload URL — derived from the actual request, not APP_URL env var ─────────
// This works whether the project is at root OR in a subdirectory (/nikahin/).
// e.g.  http://localhost/nikahin/uploads/filename.jpg
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];                          // localhost
$uri      = strtok($_SERVER['REQUEST_URI'], '?');           // /nikahin/api/upload
// Strip everything from /api/ onwards to get the base path  /nikahin/
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

// ── Method guard ──────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') json_error('Method not allowed', 405);

// ── Auth ──────────────────────────────────────────────────────────────────────
$u = require_auth();

// ── Ensure uploads folder exists and is writable ──────────────────────────────
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

// ── File validation ───────────────────────────────────────────────────────────
$file = $_FILES['file'] ?? null;
if (!$file) json_error('No file received. Make sure the form field name is "file".');

// Map PHP upload error codes to readable messages
$uploadErrors = [
    UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
    UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
    UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
    UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
];
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_error($uploadErrors[$file['error']] ?? 'Upload error code: ' . $file['error']);
}

if ($file['size'] > MAX_SIZE) json_error('File too large (max 5 MB)');

$mime = mime_content_type($file['tmp_name']);
if (!isset($ALLOWED[$mime])) {
    json_error('Invalid file type "' . $mime . '". Only JPEG, PNG, WebP, GIF allowed.');
}

// ── Save file ─────────────────────────────────────────────────────────────────
$ext      = $ALLOWED[$mime];
$filename = sprintf('%s_%s.%s', $u['user_id'], bin2hex(random_bytes(8)), $ext);
$dest     = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    error_log('[nikahin] move_uploaded_file failed: ' . $file['tmp_name'] . ' → ' . $dest);
    json_error('Failed to save file. Check PHP error log for details.', 500);
}

json_ok(['url' => UPLOAD_URL . $filename, 'filename' => $filename]);
