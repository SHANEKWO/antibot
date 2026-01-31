<?php
/**
 * === ANTI-DDOS HARDENED ===
 * Toutes les protections AVANT tout traitement lourd
 */

// 1. PROTECTION X-FORWARDED-FOR SPOOFING
// Ne faire confiance à X-Forwarded-For que si configuré (derrière un proxy connu)
$trustedProxies = []; // Ajouter les IPs de ton reverse proxy si besoin: ['127.0.0.1', '10.0.0.1']
$realIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!empty($trustedProxies) && in_array($realIp, $trustedProxies)) {
    // Derrière un proxy de confiance, on peut lire X-Forwarded-For
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $realIp)[0]);
} else {
    // Pas de proxy ou proxy non configuré = IP directe uniquement
    $ip = $realIp;
}

// Valider que c'est une vraie IP
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = '0.0.0.0';
}

$ipHash = md5($ip);
$dataDir = __DIR__ . '/data';

// 2. GLOBAL RATE LIMIT (protection botnet distribué)
$globalFile = $dataDir . '/global_rate';
$now = time();
$globalWindow = floor($now / 1); // Fenêtre de 1 seconde

if (file_exists($globalFile)) {
    $gdata = @file_get_contents($globalFile);
    if ($gdata) {
        [$gWin, $gCount] = explode(':', $gdata) + [0, 0];
        if ((int)$gWin === $globalWindow && (int)$gCount > 500) {
            // Plus de 500 req/sec global = serveur sous attaque
            header('HTTP/1.1 503 Service Unavailable');
            header('Retry-After: 5');
            exit;
        }
    }
}

// 3. IP BANNIE ? Exit immédiat, ZERO traitement
$banFile = $dataDir . '/banned/' . $ipHash;
if (file_exists($banFile) && filemtime($banFile) > $now - 600) {
    header('Location: https://google.fr');
    exit;
}

// 4. RATE LIMIT PAR IP
$rateFile = $dataDir . '/ratelimit/' . $ipHash;
$window = floor($now / 60);
$count = 0;

if (file_exists($rateFile)) {
    $data = @file_get_contents($rateFile);
    if ($data) {
        [$savedWindow, $savedCount] = explode(':', $data) + [0, 0];
        if ((int)$savedWindow === $window) {
            $count = (int)$savedCount;
        }
    }
}

// >100 req/min = ban 10 min
if ($count > 100) {
    if (!file_exists($banFile)) {
        @mkdir(dirname($banFile), 0755, true);
        touch($banFile);
    }
    header('Location: https://google.fr');
    exit;
}

// >30 req/min = block temporaire
if ($count > 30) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 60');
    exit;
}

// 5. FILE BOMB PROTECTION - Nettoyer si trop de fichiers
$ratelimitDir = $dataDir . '/ratelimit';
@mkdir($ratelimitDir, 0755, true);
$fileCount = count(glob($ratelimitDir . '/*'));
if ($fileCount > 10000) {
    // Trop de fichiers, nettoyer les vieux
    $files = glob($ratelimitDir . '/*');
    $cutoff = $now - 120; // Plus vieux que 2 min
    foreach ($files as $f) {
        if (filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }
}

// Incrémenter les compteurs (écritures minimales)
file_put_contents($rateFile, $window . ':' . ($count + 1), LOCK_EX);
@file_put_contents($globalFile, $globalWindow . ':' . ((file_exists($globalFile) && strpos(file_get_contents($globalFile), $globalWindow . ':') === 0) ? ((int)explode(':', file_get_contents($globalFile))[1] + 1) : 1), LOCK_EX);

// === FIN ANTI-DDOS ===

// Maintenant on peut charger les libs (après rate limit)
session_start();

require_once __DIR__ . '/lib/RiskEngine.php';
require_once __DIR__ . '/lib/Fingerprint.php';
require_once __DIR__ . '/lib/RateLimit.php';
require_once __DIR__ . '/lib/Logger.php';

$config = require __DIR__ . '/config.php';

// Cookie valide ? Redirect instantané
if (isset($_COOKIE[$config['session_cookie']])) {
    if (Fingerprint::validateToken($_COOKIE[$config['session_cookie']])) {
        header('Location: ' . $config['target_url']);
        exit;
    }
}

// Analyse du risque
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$risk = new RiskEngine();
$rateLimit = new RateLimit();

$score = $risk->analyzeServer([
    'ip' => $ip,
    'user_agent' => $ua,
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
    'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    'referer' => $_SERVER['HTTP_REFERER'] ?? '',
]);

$score['total'] += $rateLimit->check($ip);

// Décision
if ($score['total'] >= $config['block_threshold']) {
    Logger::log('blocked', [
        'ip' => $ip,
        'ua' => $ua,
        'score' => $score['total'],
        'reasons' => $score['reasons'] ?? [],
    ]);
    header('Location: ' . $config['block_url']);
    exit;
}

if ($score['total'] < $config['instant_redirect_threshold']) {
    Logger::log('allowed', [
        'ip' => $ip,
        'ua' => $ua,
        'score' => $score['total'],
        'reasons' => ['instant_pass'],
    ]);
    header('Location: ' . $config['target_url']);
    exit;
}

// Challenge
$token = Fingerprint::generateChallenge();
$_SESSION['challenge'] = $token;
$_SESSION['challenge_time'] = microtime(true);
$_SESSION['server_score'] = $score['total'];
$_SESSION['ip'] = $ip;
$_SESSION['ua'] = $ua;
$_SESSION['reasons'] = $score['reasons'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirection...</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;font-family:system-ui,sans-serif;color:#e2e8f0}
        .c{text-align:center;padding:2rem}
        .s{width:48px;height:48px;border:4px solid #1e293b;border-left-color:#3b82f6;border-radius:50%;animation:r 1s linear infinite;margin:0 auto 1.5rem}
        @keyframes r{to{transform:rotate(360deg)}}
        .t{font-size:1.1rem;margin-bottom:.5rem}
        .m{color:#94a3b8;font-size:.875rem}
        .ok .s{border-left-color:#22c55e}
    </style>
</head>
<body>
<div class="c" id="c">
    <div class="s" id="s"></div>
    <p class="t">Vérification de sécurité</p>
    <p class="m" id="m">Un instant...</p>
</div>
<script>
(function(){
    const T = <?= json_encode($token) ?>;
    const TARGET = <?= json_encode($config['target_url']) ?>;
    const BLOCK = <?= json_encode($config['block_url']) ?>;

    const sig = {
        scr: [screen.width, screen.height, screen.colorDepth].join('x'),
        tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
        lang: navigator.language,
        plat: navigator.platform,
        cores: navigator.hardwareConcurrency || 0,
        touch: navigator.maxTouchPoints || 0,
        wd: navigator.webdriver ? 1 : 0,
        plugins: navigator.plugins.length
    };

    try {
        const c = document.createElement('canvas');
        const ctx = c.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('test', 2, 2);
        sig.cv = c.toDataURL().length;
    } catch(e) { sig.cv = 0; }

    async function pow() {
        let n = 0;
        while (true) {
            const h = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(T + n));
            const hex = [...new Uint8Array(h)].map(b => b.toString(16).padStart(2, '0')).join('');
            if (hex.startsWith('0000')) return { n, h: hex };
            n++;
            if (n % 3000 === 0) await new Promise(r => setTimeout(r, 0));
        }
    }

    async function run() {
        try {
            sig.pow = await pow();
            const res = await fetch('verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: T, signals: sig })
            });
            const r = await res.json();
            if (r.success) {
                document.getElementById('c').className = 'c ok';
                document.getElementById('m').textContent = 'OK';
                setTimeout(() => location.href = TARGET, 200);
            } else {
                location.href = BLOCK;
            }
        } catch(e) {
            location.href = BLOCK;
        }
    }

    setTimeout(run, 50);
})();
</script>
</body>
</html>
