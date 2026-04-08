<?php
// backend/api/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $path[2] ?? '';

switch ("$method:$action") {

    // POST /api/auth/register
    case 'POST:register':
        $b = body();
        $name  = trim($b['name'] ?? '');
        $email = trim(strtolower($b['email'] ?? ''));
        $pass  = $b['password'] ?? '';

        if (!$name || !$email || !$pass)
            json_error('Name, email and password are required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            json_error('Invalid email address');
        if (strlen($pass) < 8)
            json_error('Password must be at least 8 characters');

        $db  = db();
        $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) json_error('Email already registered', 409);

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)');
        $stmt->execute([$name, $email, $hash]);
        $uid  = $db->lastInsertId();

        $token = jwt_encode(['user_id' => (int)$uid, 'email' => $email, 'name' => $name]);
        json_ok(['token' => $token, 'user' => ['id' => $uid, 'name' => $name, 'email' => $email]], 201);
        break;

    // POST /api/auth/login
    case 'POST:login':
        $b     = body();
        $email = trim(strtolower($b['email'] ?? ''));
        $pass  = $b['password'] ?? '';

        if (!$email || !$pass) json_error('Email and password required');

        $db   = db();
        $stmt = $db->prepare('SELECT id,name,email,password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password']))
            json_error('Invalid email or password', 401);

        $token = jwt_encode(['user_id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['name']]);
        json_ok(['token' => $token, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
        break;

    // GET /api/auth/me
    case 'GET:me':
        $u = require_auth();
        $db   = db();
        $stmt = $db->prepare('SELECT id,name,email,avatar,created_at FROM users WHERE id = ?');
        $stmt->execute([$u['user_id']]);
        $user = $stmt->fetch();
        if (!$user) json_error('User not found', 404);
        json_ok($user);
        break;

    default:
        json_error('Not found', 404);
}
