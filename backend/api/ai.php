<?php
// backend/api/ai.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/ai.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $path[2] ?? '';

if ($method !== 'POST') json_error('Method not allowed', 405);

$u  = require_auth();
$b  = body();

switch ($action) {

    // Generate full design spec for an invitation
    case 'generate-design':
        $invId = (int)($b['invitation_id'] ?? 0);
        if (!$invId) json_error('invitation_id required');

        $db = db();
        $q  = $db->prepare('SELECT i.*,d.* FROM invitations i
                             LEFT JOIN invitation_details d ON d.invitation_id=i.id
                             WHERE i.id=? AND i.user_id=?');
        $q->execute([$invId, $u['user_id']]);
        $inv = $q->fetch();
        if (!$inv) json_error('Invitation not found', 404);

        $design = ai_generate_design($inv);
        if (!$design) json_error('AI generation failed. Try again.', 500);

        // Save design JSON
        $db->prepare('UPDATE invitation_details SET ai_design_json=? WHERE invitation_id=?')
           ->execute([json_encode($design), $invId]);

        json_ok($design);
        break;

    // Generate bio for groom or bride
    case 'generate-bio':
        $name  = trim($b['name'] ?? '');
        $facts = trim($b['facts'] ?? '');
        $role  = $b['role'] ?? 'groom';
        if (!$name) json_error('name required');
        json_ok(['bio' => ai_generate_bio($name, $facts, $role)]);
        break;

    // Generate pre-wedding photo scene description
    case 'generate-photo-prompt':
        $groom = trim($b['groom_name'] ?? '');
        $bride = trim($b['bride_name'] ?? '');
        $theme = trim($b['theme'] ?? 'elegant');
        json_ok(['prompt' => ai_generate_photo_prompt($groom, $bride, $theme)]);
        break;

    // Autocomplete a greeting message
    case 'autocomplete-greeting':
        $partial    = trim($b['partial'] ?? '');
        $groomBride = trim($b['couple'] ?? '');
        if (!$partial) json_error('partial required');
        json_ok(['message' => ai_autocomplete_greeting($partial, $groomBride)]);
        break;

    // Generate RSVP thank-you
    case 'generate-thankyou':
        $guest      = trim($b['guest_name'] ?? '');
        $groomBride = trim($b['couple'] ?? '');
        $status     = $b['status'] ?? 'attending';
        json_ok(['message' => ai_generate_thankyou($guest, $groomBride, $status)]);
        break;

    default:
        json_error('Unknown AI action', 404);
}
