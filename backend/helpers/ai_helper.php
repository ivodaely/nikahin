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
        // SSL fix for XAMPP on macOS (avoids certificate errors)
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // Log cURL transport errors
    if ($curlErr) {
        error_log("[nikahin AI] cURL error: $curlErr");
        return null;
    }

    $data = json_decode($response, true);

    // Log any non-200 response from Anthropic (wrong key, wrong model, quota, etc.)
    if ($httpCode !== 200) {
        $errType = $data['error']['type']    ?? 'unknown';
        $errMsg  = $data['error']['message'] ?? $response;
        error_log("[nikahin AI] Anthropic API error (HTTP $httpCode) [$errType]: $errMsg");
        return null;
    }

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
    $system = "You are a romantic wedding writer. Write a short, warm, 2-3 sentence bio for a wedding invitation. Write in Indonesian. Return only the bio text, no labels or extra text.";
    $msg    = "Name: $name\nRole: $role\nFacts about them: $facts";
    $bio    = claude_request(AI_MODEL_RICH, $system, $msg, 200);
    return $bio ?? "$name adalah sosok yang istimewa dalam hidup pasangannya.";
}

// ── Generate pre-wedding photo scene prompt ───────────────
function ai_generate_photo_prompt(string $groom, string $bride, string $theme): string {
    $system = "You are a professional pre-wedding photographer's assistant. Generate a vivid, specific scene description for a pre-wedding photo shoot. Return only the scene description in Indonesian, 2-3 sentences.";
    $msg    = "Couple: $groom & $bride\nWedding theme: $theme";
    $prompt = claude_request(AI_MODEL_FAST, $system, $msg, 200);
    return $prompt ?? "Sesi foto romantis di taman bunga dengan cahaya senja keemasan.";
}

// ── Autocomplete a greeting message ──────────────────────
function ai_autocomplete_greeting(string $partial, string $couple): string {
    $system = "You are helping guests write warm wedding greetings. Complete the partial message naturally and warmly. Return only the completed message in the same language as the partial input. Keep it under 3 sentences.";
    $msg    = "Couple getting married: $couple\nPartial greeting to complete: $partial";
    $result = claude_request(AI_MODEL_FAST, $system, $msg, 150);
    return $result ?? $partial;
}

// ── Generate RSVP thank-you message ──────────────────────
function ai_generate_thankyou(string $guestName, string $couple, string $status): string {
    $system = "You are writing a warm, personal thank-you reply from the wedding couple to a guest who submitted their RSVP. Write in Indonesian. Return only the message, 1-2 sentences.";
    $msg    = "Couple: $couple\nGuest name: $guestName\nRSVP status: $status";
    $result = claude_request(AI_MODEL_FAST, $system, $msg, 100);
    return $result ?? "Terima kasih atas konfirmasinya, $guestName! Kami sangat menantikan kehadiran Anda.";
}
