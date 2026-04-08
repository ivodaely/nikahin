<?php
// backend/helpers/response.php

function json_ok($data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function json_error(string $msg, int $code = 400, $extra = null): void {
    http_response_code($code);
    header('Content-Type: application/json');
    $r = ['success' => false, 'message' => $msg];
    if ($extra) $r['errors'] = $extra;
    echo json_encode($r);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
