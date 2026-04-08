<?php
// backend/config/ai.php

define('ANTHROPIC_API_KEY',  getenv('ANTHROPIC_API_KEY') ?: 'YOUR_API_KEY_HERE');
define('ANTHROPIC_API_URL',  'https://api.anthropic.com/v1/messages');
define('ANTHROPIC_VERSION',  '2023-06-01');
define('AI_MODEL_FAST',      'claude-sonnet-4-20250514');   // design gen, completions
define('AI_MODEL_RICH',      'claude-opus-4-20250514');     // bio writing
