<?php
// backend/api/invitation.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($path[2]) && is_numeric($path[2]) ? (int)$path[2] : null;
$action = $id ? ($path[3] ?? '') : ($path[2] ?? '');

// ── Helpers ──────────────────────────────────────────────
function make_slug(string $groom, string $bride): string {
    $g = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $groom));
    $b = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $bride));
    return "{$g}-{$b}";
}

function ensure_unique_slug(PDO $db, string $base, ?int $excludeId = null): string {
    $slug  = $base;
    $i     = 1;
    while (true) {
        $q = $db->prepare('SELECT id FROM invitations WHERE slug = ?' . ($excludeId ? ' AND id != ?' : ''));
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $q->execute($params);
        if (!$q->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function full_invitation(PDO $db, int $id): ?array {
    $q = $db->prepare('SELECT i.*, d.* FROM invitations i
        LEFT JOIN invitation_details d ON d.invitation_id = i.id
        WHERE i.id = ?');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) return null;
    foreach (['ai_design_json','prewedding_photos','reference_images'] as $col) {
        if (isset($row[$col]) && is_string($row[$col]))
            $row[$col] = json_decode($row[$col], true);
    }
    return $row;
}

// ── Routes ───────────────────────────────────────────────
switch ("$method:" . ($id ? 'id' : $action)) {

    // GET /api/invitation  – list mine
    case 'GET:':
        $u  = require_auth();
        $db = db();
        $q  = $db->prepare('SELECT i.id,i.slug,i.groom_name,i.bride_name,i.wedding_date,
                                    i.theme,i.status,i.created_at,d.hero_photo
                             FROM invitations i
                             LEFT JOIN invitation_details d ON d.invitation_id=i.id
                             WHERE i.user_id=? ORDER BY i.created_at DESC');
        $q->execute([$u['user_id']]);
        json_ok($q->fetchAll());
        break;

    // POST /api/invitation  – create draft
    case 'POST:':
        $u  = require_auth();
        $b  = body();
        $db = db();

        $groom = trim($b['groom_name'] ?? '');
        $bride = trim($b['bride_name'] ?? '');
        if (!$groom || !$bride) json_error('Groom and bride names required');

        $slug = ensure_unique_slug($db, make_slug($groom, $bride));

        $db->prepare(
            'INSERT INTO invitations (user_id,slug,groom_name,bride_name,wedding_date,wedding_time,
             venue_name,venue_address,venue_lat,venue_lng,theme)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $u['user_id'], $slug, $groom, $bride,
            $b['wedding_date'] ?? date('Y-m-d'),
            $b['wedding_time'] ?? '10:00:00',
            $b['venue_name'] ?? null,
            $b['venue_address'] ?? null,
            $b['venue_lat'] ?? null,
            $b['venue_lng'] ?? null,
            $b['theme'] ?? 'elegant',
        ]);
        $invId = (int)$db->lastInsertId();

        // Insert details row
        $db->prepare(
            'INSERT INTO invitation_details
             (invitation_id,groom_father,groom_mother,groom_color,groom_religion,groom_bio,
              bride_father,bride_mother,bride_color,bride_religion,bride_bio,
              bank_name,bank_account,bank_holder,ai_prompt)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $invId,
            $b['groom_father'] ?? null, $b['groom_mother'] ?? null,
            $b['groom_color']  ?? null, $b['groom_religion'] ?? null, $b['groom_bio'] ?? null,
            $b['bride_father'] ?? null, $b['bride_mother'] ?? null,
            $b['bride_color']  ?? null, $b['bride_religion'] ?? null, $b['bride_bio'] ?? null,
            $b['bank_name'] ?? null, $b['bank_account'] ?? null, $b['bank_holder'] ?? null,
            $b['ai_prompt'] ?? null,
        ]);

        json_ok(full_invitation($db, $invId), 201);
        break;

    // GET /api/invitation/{id}
    case 'GET:id':
        $u   = require_auth();
        $db  = db();
        $inv = full_invitation($db, $id);
        if (!$inv || (int)$inv['user_id'] !== $u['user_id']) json_error('Not found', 404);
        json_ok($inv);
        break;

    // PUT /api/invitation/{id}
    case 'PUT:id':
        $u   = require_auth();
        $db  = db();
        $inv = $db->prepare('SELECT * FROM invitations WHERE id=?');
        $inv->execute([$id]);
        $row = $inv->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);

        $b = body();

        // Update main table
        $fields = ['groom_name','bride_name','wedding_date','wedding_time',
                   'venue_name','venue_address','venue_lat','venue_lng','theme'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (isset($b[$f])) { $sets[] = "$f=?"; $vals[] = $b[$f]; }
        }
        if ($sets) {
            $vals[] = $id;
            $db->prepare('UPDATE invitations SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
        }

        // Update details
        $dfields = ['groom_father','groom_mother','groom_color','groom_religion','groom_bio','groom_photo',
                    'bride_father','bride_mother','bride_color','bride_religion','bride_bio','bride_photo',
                    'bank_name','bank_account','bank_holder','ai_prompt','hero_photo'];
        $ds = []; $dv = [];
        foreach ($dfields as $f) {
            if (array_key_exists($f, $b)) { $ds[] = "$f=?"; $dv[] = $b[$f]; }
        }
        // JSON fields
        foreach (['ai_design_json','prewedding_photos','reference_images'] as $f) {
            if (array_key_exists($f, $b)) { $ds[] = "$f=?"; $dv[] = json_encode($b[$f]); }
        }
        if ($ds) {
            $dv[] = $id;
            $db->prepare('UPDATE invitation_details SET ' . implode(',', $ds) . ' WHERE invitation_id=?')->execute($dv);
        }

        json_ok(full_invitation($db, $id));
        break;

    // DELETE /api/invitation/{id}
    case 'DELETE:id':
        $u  = require_auth();
        $db = db();
        $q  = $db->prepare('SELECT user_id FROM invitations WHERE id=?');
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);
        $db->prepare('DELETE FROM invitations WHERE id=?')->execute([$id]);
        json_ok(['deleted' => true]);
        break;

    // POST /api/invitation/{id}/publish
    case 'POST:publish':
        $u   = require_auth();
        $db  = db();
        $q   = $db->prepare('SELECT i.*,p.status AS pay_status
                              FROM invitations i
                              LEFT JOIN payments p ON p.invitation_id=i.id AND p.status="paid"
                              WHERE i.id=?');
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);
        if (!$row['pay_status']) json_error('Payment required to publish', 402);
        $db->prepare('UPDATE invitations SET status="published",published_at=NOW() WHERE id=?')->execute([$id]);
        json_ok(['slug' => $row['slug'], 'url' => APP_URL . '/' . $row['slug']]);
        break;

    // POST /api/invitation/{id}/simulate-pay  (dev/test shortcut)
    case 'POST:simulate-pay':
        $u  = require_auth();
        $db = db();
        $q  = $db->prepare('SELECT user_id FROM invitations WHERE id=?');
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);
        $db->prepare('INSERT INTO payments (invitation_id,amount,status,paid_at) VALUES (?,99000,"paid",NOW())
                      ON DUPLICATE KEY UPDATE status="paid",paid_at=NOW()')->execute([$id]);
        json_ok(['paid' => true]);
        break;

    // GET /api/invitation/public/{slug}  – public view (no auth)
    case 'GET:public':
        $slug = $path[3] ?? '';
        $db   = db();
        $q    = $db->prepare('SELECT i.*,d.* FROM invitations i
                               LEFT JOIN invitation_details d ON d.invitation_id=i.id
                               WHERE i.slug=? AND i.status="published"');
        $q->execute([$slug]);
        $row = $q->fetch();
        if (!$row) json_error('Invitation not found', 404);
        foreach (['ai_design_json','prewedding_photos','reference_images'] as $col) {
            if (isset($row[$col]) && is_string($row[$col]))
                $row[$col] = json_decode($row[$col], true);
        }
        json_ok($row);
        break;

    default:
        json_error('Not found', 404);
}
