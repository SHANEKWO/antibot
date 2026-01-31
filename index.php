<?php
session_start();

require_once __DIR__ . '/lib/RiskEngine.php';
require_once __DIR__ . '/lib/Fingerprint.php';
require_once __DIR__ . '/lib/RateLimit.php';
require_once __DIR__ . '/lib/Logger.php';

$config = require __DIR__ . '/config.php';

// Déjà vérifié ? Redirect instantané
if (isset($_COOKIE[$config['session_cookie']])) {
    if (Fingerprint::validateToken($_COOKIE[$config['session_cookie']])) {
        header('Location: ' . $config['target_url']);
        exit;
    }
}

// Analyse du risque
$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
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

// Challenge (pas de log ici, on log le résultat final dans verify.php)

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
