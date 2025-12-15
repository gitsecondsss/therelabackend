<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Railway Trust Mint
|--------------------------------------------------------------------------
| - Verifies Cloudflare Turnstile
| - Collects passive signals
| - Issues short-lived opaque trust token
| - No business logic
| - No knowledge of main/VPS flow
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

/* =======================
   CONFIG
   ======================= */
$SECRET        = getenv('TRUST_SECRET');
$TOKEN_TTL     = (int)(getenv('TOKEN_TTL') ?: 300);
$TURNSTILE_KEY = getenv('TURNSTILE_SECRET');

if (!$SECRET || !$TURNSTILE_KEY) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

/* =======================
   INPUT
   ======================= */
$turnstileToken = $_POST['cf_turnstile'] ?? '';
$signalsRaw     = $_POST['signals'] ?? '';

if (!$turnstileToken) {
    echo json_encode(['ok' => false]);
    exit;
}

/* =======================
   TURNSTILE VERIFY
   ======================= */
$verify = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt_array($verify, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'secret'   => $TURNSTILE_KEY,
        'response' => $turnstileToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
    ])
]);

$response = curl_exec($verify);
curl_close($verify);

$result = json_decode($response, true);

if (empty($result['success'])) {
    echo json_encode(['ok' => false]);
    exit;
}

/* =======================
   PASSIVE SIGNAL PARSE
   ======================= */
$signals = json_decode($signalsRaw, true);
if (!is_array($signals)) {
    $signals = [];
}

/* Lightweight sanity only — NO blocking */
$ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 180);
$lang   = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 64);
$ip     = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$time   = time();
$nonce  = bin2hex(random_bytes(8));

/* =======================
   TOKEN BUILD (OPAQUE)
   ======================= */
$payload = [
    't' => $time,
    'n' => $nonce,
    'i' => hash('sha256', $ip),
    'u' => hash('sha256', $ua),
];

$raw  = base64_encode(json_encode($payload));
$mac  = hash_hmac('sha256', $raw, $SECRET);
$token = $raw . '.' . $mac;

/* =======================
   RESPONSE
   ======================= */
echo json_encode([
    'ok'    => true,
    'token' => $token,
    'exp'   => $time + $TOKEN_TTL
]);
exit;
