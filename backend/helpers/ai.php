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
            'x-api-key: '         . ANTHROPIC_API_KEY,
            'anthropic-version: ' . ANTHROPIC_VERSION,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,  // XAMPP macOS has no CA bundle
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[nikahin AI] cURL error: ' . $curlErr);
        return null;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $type = $data['error']['type']    ?? 'unknown';
        $msg  = $data['error']['message'] ?? $response;
        error_log("[nikahin AI] Anthropic HTTP $httpCode [$type]: $msg");
        return null;
    }

    return $data['content'][0]['text'] ?? null;
}

function ai_generate_design(array $inv): ?array {
    $system = <<<SYS
You are a wedding invitation designer AI. Given couple information, return ONLY a valid JSON object (no markdown, no explanation) with this exact structure:
{
  "palette": {"primary":"#hex","secondary":"#hex","accent":"#hex","text":"#hex","bg":"#hex"},
  "font_heading": "Google Font name",
  "font_body": "Google Font name",
  "ornament_style": "floral|geometric|minimal|batik|tropical",
  "opening_quote": "short romantic opening quote in Indonesian",
  "closing_message": "short warm closing in Indonesian",
  "bride_quote": "short romantic quote for bride section",
  "groom_quote": "short romantic quote for groom section",
  "theme_mood": "one word mood"
}
SYS;

    $msg = sprintf(
        "Groom: %s (bio: %s, religion: %s, color: %s)\nBride: %s (bio: %s, religion: %s, color: %s)\nDate: %s\nTheme: %s\nRequest: %s",
        $inv['groom_name'] ?? '', $inv['groom_bio'] ?? '', $inv['groom_religion'] ?? '', $inv['groom_color'] ?? '',
        $inv['bride_name'] ?? '', $inv['bride_bio'] ?? '', $inv['bride_religion'] ?? '', $inv['bride_color'] ?? '',
        $inv['wedding_date'] ?? '', $inv['theme'] ?? 'elegant',
        $inv['ai_prompt'] ?? 'Make it beautiful and romantic'
    );

    $raw = claude_request(AI_MODEL_FAST, $system, $msg, 600);
    if (!$raw) return null;

    $raw    = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
    $raw    = preg_replace('/```\s*$/i', '', $raw);
    $result = json_decode(trim($raw), true);

    if (!$result) {
        error_log('[nikahin AI] Failed to parse design JSON. Raw: ' . substr($raw, 0, 300));
        return null;
    }
    return $result;
}

function ai_generate_bio(string $name, string $facts, string $role): string {
    $system = "You are a romantic wedding writer. Write a SHORT (2-3 sentences) warm bio in Indonesian for the $role. Return only the paragraph, no labels.";
    $msg    = "Name: $name. Facts: $facts";
    return claude_request(AI_MODEL_RICH, $system, $msg, 200) ?? "$name adalah seseorang yang penuh kasih dan ketulusan.";
}

function ai_generate_photo_prompt(string $groom, string $bride, string $theme): string {
    $system = "You are a wedding photographer assistant. Write 1-2 sentences describing a romantic pre-wedding photo scene. Return only the scene description in Indonesian.";
    $msg    = "Couple: $groom & $bride. Theme: $theme.";
    return claude_request(AI_MODEL_FAST, $system, $msg, 150) ?? "Sesi foto romantis di bawah cahaya senja yang keemasan.";
}

function ai_autocomplete_greeting(string $partial, string $couple): string {
    $system = "Complete this partial Indonesian wedding greeting warmly. Return ONLY the completed full message.";
    $msg    = "Couple: $couple\nPartial: $partial";
    return claude_request(AI_MODEL_FAST, $system, $msg, 150) ?? $partial;
}

function ai_generate_thankyou(string $guest, string $couple, string $status): string {
    $system = "Write a SHORT warm Indonesian thank-you for a wedding RSVP. Return only the message.";
    $msg    = "Guest: $guest, Couple: $couple, Status: $status";
    return claude_request(AI_MODEL_FAST, $system, $msg, 100) ?? "Terima kasih atas konfirmasinya, $guest!";
}