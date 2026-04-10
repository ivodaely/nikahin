<?php
// TEMPORARY DEBUG FILE — delete after fixing!
set_time_limit(120);  // give it 2 minutes
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain');
// Flush output immediately so we see each step in real time
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

require_once __DIR__ . '/backend/config/ai.php';
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/helpers/ai.php';

echo "=== TEST WITH REAL INVITATION ===\n";
flush();

$db  = db();
$inv = $db->query("SELECT i.*, d.* FROM invitations i 
                   LEFT JOIN invitation_details d ON d.invitation_id = i.id 
                   ORDER BY i.id DESC LIMIT 1")->fetch();

if (!$inv) { echo "No invitations found.\n"; exit; }

echo "Invitation #{$inv['id']}: {$inv['groom_name']} & {$inv['bride_name']}\n";
echo "Calling ai_generate_design()... (may take 5-10 seconds)\n";
flush();

$design = ai_generate_design($inv);

if ($design) {
    echo "✅ SUCCESS\n";
    echo "theme_mood: " . ($design['theme_mood'] ?? '?') . "\n";
    echo "palette: " . json_encode($design['palette'] ?? []) . "\n";
} else {
    echo "❌ FAILED — returned null\n";
    echo "Check: /Applications/XAMPP/xamppfiles/logs/php_error_log\n";
}
flush();

echo "\n=== MAX EXECUTION TIME ===\n";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds\n";