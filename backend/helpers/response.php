<?php
// backend/helpers/response.php

function json_ok($data = null, int $code = 200): void {
    // Discard any stray output (PHP notices, warnings, whitespace)
    // that may have been buffered before this response is sent
    if (ob_get_level()) ob_end_clean();

    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $msg, int $code = 400, $extra = null): void {
    if (ob_get_level()) ob_end_clean();

    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $r = ['success' => false, 'message' => $msg];
    if ($extra) $r['errors'] = $extra;
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
