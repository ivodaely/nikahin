<?php
// backend/config/ai.php

// Get your key from: https://console.anthropic.com/
define('ANTHROPIC_API_KEY',  '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');  // ← paste your real key here

define('ANTHROPIC_API_URL',  'https://api.anthropic.com/v1/messages');
define('ANTHROPIC_VERSION',  '2023-06-01');
define('AI_MODEL_FAST',      'claude-sonnet-4-5');
define('AI_MODEL_RICH',      'claude-opus-4-5');