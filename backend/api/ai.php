<?php
// backend/config/ai.php

// ── Set your Anthropic API key here ──────────────────────────────────────────
// Get your key from: https://console.anthropic.com/
// Replace the string below with your actual key (starts with sk-ant-...)
define('ANTHROPIC_API_KEY',  getenv('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');

define('ANTHROPIC_API_URL',  'https://api.anthropic.com/v1/messages');
define('ANTHROPIC_VERSION',  '2023-06-01');

// ── Correct model IDs (as of 2025) ───────────────────────────────────────────
define('AI_MODEL_FAST',  'claude-sonnet-4-5');   // design gen, completions, photo prompts
define('AI_MODEL_RICH',  'claude-opus-4-5');     // bio writing (higher quality)
