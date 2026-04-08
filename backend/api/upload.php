<?php
// backend/api/upload.php

require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('UPLOAD_URL', (getenv('APP_URL') ?: 'http://localhost') . '/uploads/');
define('MAX_SIZE',   5 * 1024 * 1024); // 5MB
$ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') json_error('Method not allowed', 405);

$u = require_auth();
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

$file = $_FILES['file'] ?? null;
if (!$file) json_error('No file uploaded');
if ($file['error'] !== UPLOAD_ERR_OK) json_error('Upload error: ' . $file['error']);
if ($file['size'] > MAX_SIZE) json_error('File too large (max 5MB)');

$mime = mime_content_type($file['tmp_name']);
if (!isset($ALLOWED[$mime])) json_error('Invalid file type. Only JPEG, PNG, WebP, GIF allowed');

$ext      = $ALLOWED[$mime];
$filename = sprintf('%s_%s.%s', $u['user_id'], bin2hex(random_bytes(8)), $ext);
$dest     = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Failed to save file', 500);

json_ok(['url' => UPLOAD_URL . $filename, 'filename' => $filename]);
