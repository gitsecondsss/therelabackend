<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!in_array($origin, ALLOWED_ORIGINS, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized origin']);
    exit;
}

header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_POST['action'] ?? '';

function json_ok($extra = []) { echo json_encode(array_merge(['ok' => true], $extra)); exit; }
function json_fail($msg) { echo json_encode(['ok' => false, 'error' => $msg]); exit; }

function generate_token($email = null) {
    $ts = time();
    $payload = [
        'email' => $email ?? '',
        'ts' => $ts
    ];
    $mac = hash_hmac('sha256', json_encode($payload), TOKEN_SECRET);
    return base64_encode(json_encode(['payload'=>$payload,'mac'=>$mac]));
}

function parse_token($token) {
    $decoded = base64_decode($token, true);
    if (!$decoded) return [false, 'Invalid token'];
    $data = json_decode($decoded, true);
    if (!$data || !isset($data['payload'], $data['mac'])) return [false,'Malformed token'];

    $expected = hash_hmac('sha256', json_encode($data['payload']), TOKEN_SECRET);
    if (!hash_equals($expected, $data['mac'])) return [false,'Token verification failed'];

    if (time() - $data['payload']['ts'] > TOKEN_TTL) return [false,'Token expired'];

    return [true, $data['payload']];
}

// Rate limit by IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = RATE_LIMIT_DIR . "/$ip.txt";
if (!is_dir(RATE_LIMIT_DIR)) mkdir(RATE_LIMIT_DIR, 0755, true);
$recent = file_exists($rateFile) ? file($rateFile, FILE_IGNORE_NEW_LINES) : [];
$recent = array_filter($recent, fn($ts) => time() - (int)$ts < 60);
if (count($recent) >= 5) {
    http_response_code(429);
    json_fail('Too many requests. Try again later.');
}
$recent[] = time();
file_put_contents($rateFile, implode("\n", $recent));

// ------------------ Actions -------------------
if ($action === 'request_token') {
    // Optional: capture honeypots / passive signals
    $behavior = json_decode($_POST['behavior'] ?? '{}', true);

    // Issue token
    $token = generate_token();
    json_ok(['token'=>$token]);
}

if ($action === 'validate') {
    $token = $_POST['token'] ?? '';
    [$valid,$payload] = parse_token($token);
    if (!$valid) json_fail($payload);
    json_ok(['token'=>$token]);
}

json_fail('Invalid action');
