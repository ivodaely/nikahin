<?php
// backend/helpers/ai.php

require_once __DIR__ . '/../config/ai.php';

function claude_request(string $model, string $system, string $userMsg, int $maxTokens = 1024): ?string {
    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ]);

    $ch = curl_init(ANTHROPIC_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: ' . ANTHROPIC_VERSION,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 60,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) { error_log("Claude curl error: $err"); return null; }

    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? null;
}

// ── Generate invitation design spec ──────────────────────
function ai_generate_design(array $inv): ?array {
    $system = <<<SYS
You are a wedding invitation designer AI. Given couple information, return ONLY a valid JSON object (no markdown, no explanation) with this exact structure:
{
  "palette": {"primary":"#hex","secondary":"#hex","accent":"#hex","text":"#hex","bg":"#hex"},
  "font_heading": "Google Font name",
  "font_body": "Google Font name",
  "ornament_style": "floral|geometric|minimal|batik|tropical",
  "opening_quote": "short romantic opening quote (Indonesian or English)",
  "closing_message": "short warm closing (Indonesian)",
  "bride_quote": "short religious or romantic quote for bride section",
  "groom_quote": "short religious or romantic quote for groom section",
  "theme_mood": "one word mood description"
}
SYS;

    $msg = sprintf(
        "Groom: %s (%s, religion: %s, favorite color: %s)\n" .
        "Bride: %s (%s, religion: %s, favorite color: %s)\n" .
        "Wedding date: %s\nTheme preset: %s\n" .
        "Design request: %s",
        $inv['groom_name'], $inv['groom_bio'] ?? '', $inv['groom_religion'] ?? '', $inv['groom_color'] ?? '',
        $inv['bride_name'], $inv['bride_bio'] ?? '', $inv['bride_religion'] ?? '', $inv['bride_color'] ?? '',
        $inv['wedding_date'], $inv['theme'] ?? 'elegant',
        $inv['ai_prompt'] ?? 'Make it beautiful and romantic'
    );

    $raw = claude_request(AI_MODEL_FAST, $system, $msg, 512);
    if (!$raw) return null;

    // Strip any accidental markdown fences
    $raw = preg_replace('/^```json\s*/i', '', $raw);
    $raw = preg_replace('/```\s*$/i', '', $raw);
    return json_decode(trim($raw), true);
}

// ── Generate romantic couple bio ─────────────────────────
function ai_generate_bio(string $name, string $facts, string $role): string {
    $system = "You are a romantic wedding writer. Write a SHORT (2-3 sentences) warm, romantic bio paragraph in Indonesian for the {$role}. Return only the paragraph text, no JSON, no quotes.";
    $msg    = "Name: {$name}. Facts: {$facts}";
    return claude_request(AI_MODEL_RICH, $system, $msg, 200) ?? "Seseorang yang luar biasa dan penuh kasih.";
}

// ── Generate pre-wedding photo scene prompt ──────────────
function ai_generate_photo_prompt(string $groomName, string $brideName, string $theme): string {
    $system = "You are a wedding photographer AI. Generate a SHORT (1-2 sentences) artistic pre-wedding photo scene description suitable for AI image generation. Keep it romantic, tasteful, and visually descriptive. Return only the scene description.";
    $msg    = "Couple: {$groomName} & {$brideName}. Theme: {$theme}.";
    return claude_request(AI_MODEL_FAST, $system, $msg, 150) ?? "A romantic couple standing in golden sunset light among blooming flowers.";
}

// ── Autocomplete greeting message ────────────────────────
function ai_autocomplete_greeting(string $partial, string $groomBride): string {
    $system = "Complete this partial wedding greeting message warmly in Indonesian. Return ONLY the completed full message, nothing else.";
    $msg    = "Wedding couple: {$groomBride}\nPartial message: {$partial}";
    return claude_request(AI_MODEL_FAST, $system, $msg, 150) ?? $partial;
}

// ── Generate RSVP thank-you ──────────────────────────────
function ai_generate_thankyou(string $guestName, string $groomBride, string $status): string {
    $system = "Write a SHORT (2-3 sentences) warm, personalized Indonesian thank-you message for a wedding RSVP. Return only the message text.";
    $msg    = "Guest: {$guestName}\nCouple: {$groomBride}\nRSVP status: {$status}";
    return claude_request(AI_MODEL_FAST, $system, $msg, 150) ?? "Terima kasih atas konfirmasi Anda. Kami sangat menantikan kehadiran Anda.";
}
