<?php
// === ANTI-DDOS HARDENED ===
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$banFile = __DIR__ . '/data/banned/' . md5($ip);

// IP bannie
if (file_exists($banFile) && filemtime($banFile) > time() - 600) {
    http_response_code(429);
    exit;
}

// Limite taille POST (max 8KB - largement suffisant pour les signaux)
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 8192) {
    http_response_code(413); // Payload Too Large
    exit;
}

// Méthode POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
// === FIN ANTI-DDOS ===

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/lib/Fingerprint.php';
require_once __DIR__ . '/lib/Logger.php';

$config = require __DIR__ . '/config.php';

// Lire avec limite stricte
$rawInput = file_get_contents('php://input', false, null, 0, 8192);
$input = json_decode($rawInput, true);

if (!$input) {
    echo json_encode(['success' => false]);
    exit;
}

$token = $input['token'] ?? '';
$signals = $input['signals'] ?? [];
$pow = $signals['pow'] ?? null;

$ip = $_SESSION['ip'] ?? '';
$ua = $_SESSION['ua'] ?? '';

// Vérif token
if (empty($_SESSION['challenge']) || $token !== $_SESSION['challenge']) {
    Logger::log('blocked', ['ip' => $ip, 'ua' => $ua, 'score' => 100, 'reasons' => ['invalid_token']]);
    echo json_encode(['success' => false]);
    exit;
}

// Vérif timing
$elapsed = (microtime(true) - ($_SESSION['challenge_time'] ?? 0)) * 1000;
if ($elapsed < 100 || $elapsed > 30000) {
    Logger::log('blocked', ['ip' => $ip, 'ua' => $ua, 'score' => 100, 'reasons' => ['timing_fail']]);
    echo json_encode(['success' => false]);
    exit;
}

// Vérif PoW
if (!$pow || !isset($pow['h']) || !str_starts_with($pow['h'], '0000')) {
    Logger::log('blocked', ['ip' => $ip, 'ua' => $ua, 'score' => 100, 'reasons' => ['pow_fail']]);
    echo json_encode(['success' => false]);
    exit;
}

$expected = hash('sha256', $token . ($pow['n'] ?? 0));
if ($expected !== $pow['h']) {
    Logger::log('blocked', ['ip' => $ip, 'ua' => $ua, 'score' => 100, 'reasons' => ['pow_mismatch']]);
    echo json_encode(['success' => false]);
    exit;
}

// Analyse signaux
$score = $_SESSION['server_score'] ?? 0;
$reasons = [];

if (($signals['wd'] ?? 0) === 1) { $score += 50; $reasons[] = 'webdriver'; }
if (($signals['cv'] ?? 0) === 0) { $score += 30; $reasons[] = 'no_canvas'; }
if (($signals['plugins'] ?? 0) === 0 && stripos($signals['plat'] ?? '', 'Win') !== false) {
    $score += 20;
    $reasons[] = 'no_plugins';
}

if ($score >= $config['block_threshold']) {
    Logger::log('blocked', ['ip' => $ip, 'ua' => $ua, 'score' => $score, 'reasons' => $reasons]);
    echo json_encode(['success' => false]);
    exit;
}

// Succès
$allReasons = array_merge($_SESSION['reasons'] ?? [], ['challenge_pass']);
Logger::log('allowed', ['ip' => $ip, 'ua' => $ua, 'score' => $score, 'reasons' => $allReasons]);

$fp = hash('sha256', json_encode($signals));
$verifyToken = Fingerprint::generateToken($fp, $ip);

setcookie($config['session_cookie'], $verifyToken, [
    'expires' => time() + $config['cookie_lifetime'],
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

unset($_SESSION['challenge'], $_SESSION['challenge_time'], $_SESSION['server_score'], $_SESSION['ip'], $_SESSION['ua']);

echo json_encode(['success' => true]);
