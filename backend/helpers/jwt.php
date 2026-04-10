<?php
// backend/helpers/jwt.php

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'nikahin_secret_change_in_production');
define('JWT_EXPIRY', 60 * 60 * 24 * 7); // 7 days

function jwt_encode(array $payload): string {
    $header          = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp']  = time() + JWT_EXPIRY;
    $payload['iat']  = time();
    $body            = base64url_encode(json_encode($payload));
    $sig             = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || $payload['exp'] < time()) return null;
    return $payload;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function get_bearer_token(): ?string {
    // Apache on macOS strips Authorization header — check all possible locations
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['HTTP_X_AUTHORIZATION']        ?? '',
    ];

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $candidates[] = $v;
                break;
            }
        }
    }

    foreach ($candidates as $h) {
        if (str_starts_with($h, 'Bearer ')) {
            return substr($h, 7);
        }
    }
    return null;
}

function auth_user(): ?array {
    $token = get_bearer_token();
    return $token ? jwt_decode($token) : null;
}

function require_auth(): array {
    $u = auth_user();
    if (!$u) { json_error('Unauthorized', 401); exit; }
    return $u;
}