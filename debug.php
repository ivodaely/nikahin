<?php
// TEMPORARY DEBUG FILE — delete after fixing!
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/backend/config/ai.php';
require_once __DIR__ . '/backend/helpers/ai.php';

header('Content-Type: text/plain');

echo "=== CONFIG ===\n";
echo "API Key set: " . (defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY !== 'sk-ant-PASTE_YOUR_KEY_HERE' ? 'YES (' . substr(ANTHROPIC_API_KEY, 0, 15) . '...)' : 'NO - KEY NOT SET!') . "\n";
echo "Model Fast: " . AI_MODEL_FAST . "\n";
echo "Model Rich: " . AI_MODEL_RICH . "\n";
echo "Upload dir exists: " . (is_dir(__DIR__ . '/uploads') ? 'YES' : 'NO') . "\n";
echo "Upload dir writable: " . (is_writable(__DIR__ . '/uploads') ? 'YES' : 'NO') . "\n\n";

echo "=== TESTING ANTHROPIC API ===\n";
$result = claude_request(AI_MODEL_FAST, 'You are a test assistant.', 'Reply with just the word: OK', 10);
echo "Response: " . ($result ?? 'NULL - API call failed!') . "\n\n";

echo "=== PHP INFO ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "cURL enabled: " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";
echo "ob_start available: " . (function_exists('ob_start') ? 'YES' : 'NO') . "\n";