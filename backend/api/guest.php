<?php
// backend/api/guest.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/mailer.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $path[2] ?? '';

switch ("$method:$action") {

    // GET /api/guest/{invitation_id}  – list guests
    case 'GET:':
    case 'GET:list':
        $u     = require_auth();
        $invId = (int)($path[2] ?? 0);
        $db    = db();
        $q     = $db->prepare('SELECT user_id FROM invitations WHERE id=?');
        $q->execute([$invId]);
        $row = $q->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);
        $gs = $db->prepare('SELECT * FROM guests WHERE invitation_id=? ORDER BY created_at DESC');
        $gs->execute([$invId]);
        json_ok($gs->fetchAll());
        break;

    // POST /api/guest/add  – add guests to an invitation
    case 'POST:add':
        $u  = require_auth();
        $b  = body();
        $db = db();
        $invId = (int)($b['invitation_id'] ?? 0);
        $guests = $b['guests'] ?? [];

        $q = $db->prepare('SELECT user_id FROM invitations WHERE id=?');
        $q->execute([$invId]);
        $row = $q->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);
        if (!$guests || !is_array($guests)) json_error('guests array required');

        $stmt = $db->prepare('INSERT INTO guests (invitation_id,name,phone,email) VALUES (?,?,?,?)');
        foreach ($guests as $g) {
            $stmt->execute([$invId, trim($g['name'] ?? ''), trim($g['phone'] ?? ''), trim($g['email'] ?? '')]);
        }
        json_ok(['added' => count($guests)], 201);
        break;

    // POST /api/guest/blast  – send WhatsApp links + emails
    case 'POST:blast':
        $u      = require_auth();
        $b      = body();
        $db     = db();
        $invId  = (int)($b['invitation_id'] ?? 0);

        $q = $db->prepare('SELECT i.*,d.* FROM invitations i
                           LEFT JOIN invitation_details d ON d.invitation_id=i.id
                           WHERE i.id=? AND i.user_id=?');
        $q->execute([$invId, $u['user_id']]);
        $inv = $q->fetch();
        if (!$inv) json_error('Not found', 404);
        if ($inv['status'] !== 'published') json_error('Publish the invitation first', 400);

        $link       = APP_URL . '/' . $inv['slug'];
        $groomBride = $inv['groom_name'] . ' & ' . $inv['bride_name'];

        $gs   = $db->prepare('SELECT * FROM guests WHERE invitation_id=? AND sent_at IS NULL');
        $gs->execute([$invId]);
        $guests = $gs->fetchAll();

        $wa_links  = [];
        $sent      = 0;

        foreach ($guests as $g) {
            // Send email if available
            if ($g['email']) {
                send_invitation_email($g['email'], $g['name'], $link, $groomBride);
            }
            // Build WA link
            if ($g['phone']) {
                $wa_links[] = [
                    'name' => $g['name'],
                    'phone' => $g['phone'],
                    'wa_url' => whatsapp_link($g['phone'], $g['name'], $link, $groomBride),
                ];
            }
            // Mark as sent
            $db->prepare('UPDATE guests SET sent_at=NOW() WHERE id=?')->execute([$g['id']]);
            $sent++;
        }

        json_ok(['sent' => $sent, 'whatsapp_links' => $wa_links]);
        break;

    // POST /api/guest/rsvp  – guest submits RSVP (public)
    case 'POST:rsvp':
        $b     = body();
        $db    = db();
        $invId = (int)($b['invitation_id'] ?? 0);
        $name  = trim($b['name'] ?? '');
        $status = $b['status'] ?? 'attending';

        if (!$invId || !$name) json_error('invitation_id and name required');
        if (!in_array($status, ['attending','not_attending','maybe'])) json_error('Invalid status');

        $q = $db->prepare('SELECT id FROM invitations WHERE id=? AND status="published"');
        $q->execute([$invId]);
        if (!$q->fetch()) json_error('Invitation not found', 404);

        $db->prepare('INSERT INTO rsvp (invitation_id,name,status,attendance_count,message)
                      VALUES (?,?,?,?,?)')->execute([
            $invId, $name, $status,
            (int)($b['attendance_count'] ?? 1),
            $b['message'] ?? null,
        ]);

        // Return AI thank-you
        require_once __DIR__ . '/../helpers/ai.php';
        $qInv = $db->prepare('SELECT groom_name,bride_name FROM invitations WHERE id=?');
        $qInv->execute([$invId]);
        $inv = $qInv->fetch();
        $groomBride = $inv ? $inv['groom_name'] . ' & ' . $inv['bride_name'] : '';
        $ty = ai_generate_thankyou($name, $groomBride, $status);

        json_ok(['thank_you' => $ty], 201);
        break;

    // GET /api/guest/rsvp/{invitation_id}  – list RSVPs (owner)
    case 'GET:rsvp':
        $u     = require_auth();
        $invId = (int)($path[3] ?? 0);
        $db    = db();
        $q     = $db->prepare('SELECT user_id FROM invitations WHERE id=?');
        $q->execute([$invId]);
        $row = $q->fetch();
        if (!$row || (int)$row['user_id'] !== $u['user_id']) json_error('Not found', 404);
        $rs = $db->prepare('SELECT * FROM rsvp WHERE invitation_id=? ORDER BY created_at DESC');
        $rs->execute([$invId]);
        json_ok($rs->fetchAll());
        break;

    // POST /api/guest/greeting  – post a greeting (public)
    case 'POST:greeting':
        $b     = body();
        $db    = db();
        $invId = (int)($b['invitation_id'] ?? 0);
        $name  = trim($b['name'] ?? '');
        $msg   = trim($b['message'] ?? '');

        if (!$invId || !$name || !$msg) json_error('invitation_id, name and message required');

        $q = $db->prepare('SELECT id FROM invitations WHERE id=? AND status="published"');
        $q->execute([$invId]);
        if (!$q->fetch()) json_error('Invitation not found', 404);

        $db->prepare('INSERT INTO greetings (invitation_id,name,message) VALUES (?,?,?)')
           ->execute([$invId, $name, $msg]);

        json_ok(['posted' => true], 201);
        break;

    // GET /api/guest/greetings/{invitation_id}  – list greetings (public)
    case 'GET:greetings':
        $invId = (int)($path[3] ?? 0);
        $db    = db();
        $q     = $db->prepare('SELECT * FROM greetings WHERE invitation_id=? ORDER BY created_at DESC LIMIT 100');
        $q->execute([$invId]);
        json_ok($q->fetchAll());
        break;

    default:
        json_error('Not found', 404);
}
