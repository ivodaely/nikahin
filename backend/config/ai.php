<?php
// backend/config/ai.php

// Get your key from: https://console.anthropic.com/
define('ANTHROPIC_API_KEY',  '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');  // ← paste your real key here

define('ANTHROPIC_API_URL',  'https://api.anthropic.com/v1/messages');
define('ANTHROPIC_VERSION',  '2023-06-01');
define('AI_MODEL_FAST',      'claude-sonnet-4-6');
define('AI_MODEL_RICH',      'claude-opus-4-6');