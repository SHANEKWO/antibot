<?php
require_once __DIR__ . '/lib/Logger.php';

$config = require __DIR__ . '/config.php';
$password = $config['dashboard_password'] ?? 'admin123';

session_start();
if ($_POST['password'] ?? '' === $password) {
    $_SESSION['dashboard_auth'] = true;
}
if ($_GET['logout'] ?? false) {
    unset($_SESSION['dashboard_auth']);
}

if (!($_SESSION['dashboard_auth'] ?? false)) {
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Dashboard Login</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;font-family:system-ui,sans-serif}
        form{background:#1e293b;padding:2rem;border-radius:8px;width:300px}
        input{width:100%;padding:.75rem;margin-bottom:1rem;border:1px solid #334155;border-radius:4px;background:#0f172a;color:#fff}
        button{width:100%;padding:.75rem;background:#3b82f6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:600}
        button:hover{background:#2563eb}
    </style>
    </head><body>
    <form method="POST">
        <input type="password" name="password" placeholder="Mot de passe" autofocus>
        <button type="submit">Connexion</button>
    </form>
    </body></html>
    <?php
    exit;
}

$stats = Logger::getStats(7);
$blockRate = $stats['total'] > 0 ? round($stats['blocked'] / $stats['total'] * 100, 1) : 0;

// Labels UI friendly
$reasonLabels = [
    'ua:curl' => '🔧 curl',
    'ua:wget' => '🔧 wget',
    'ua:python' => '🐍 Python',
    'ua:Python Requests' => '🐍 Python Requests',
    'ua:Scrapy' => '🕷️ Scrapy',
    'ua:Googlebot' => '🤖 Googlebot',
    'ua:Bingbot' => '🤖 Bingbot',
    'ua:bot' => '🤖 Bot générique',
    'ua:Headless Chrome' => '👻 Chrome Headless',
    'ua:Puppeteer' => '🎭 Puppeteer',
    'ua:Selenium' => '🎭 Selenium',
    'ua:Playwright' => '🎭 Playwright',
    'ua:PhantomJS' => '👻 PhantomJS',
    'no_accept_language' => '🌐 Pas de langue',
    'no_accept_encoding' => '📦 Pas d\'encoding',
    'no_gzip' => '📦 Pas de gzip',
    'datacenter' => '🏢 Datacenter',
    'vpn' => '🔒 VPN',
    'proxy' => '🔀 Proxy',
    'tor' => '🧅 Tor',
    'private_ip' => '🏠 IP locale',
    'high_frequency' => '⚡ Trop rapide',
    'moderate_frequency' => '⏱️ Fréquence élevée',
    'very_high_hourly' => '🔥 Spam horaire',
    'webdriver' => '🤖 Webdriver',
    'no_canvas' => '🎨 Pas de canvas',
    'no_plugins' => '🔌 Pas de plugins',
    'invalid_token' => '🎫 Token invalide',
    'timing_fail' => '⏱️ Timing suspect',
    'pow_fail' => '⛏️ PoW échoué',
    'pow_mismatch' => '⛏️ PoW invalide',
    'challenge_pass' => '✅ Challenge OK',
    'instant_pass' => '⚡ Accès direct',
    'empty_ua' => '❓ Pas d\'User-Agent',
    'short_ua' => '❓ UA trop court',
];

function getReasonLabel($reason) {
    global $reasonLabels;
    return $reasonLabels[$reason] ?? $reason;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AntiBot Dashboard</title>
    <style>
        :root{--bg:#0f172a;--card:#1e293b;--border:#334155;--text:#e2e8f0;--muted:#94a3b8;--green:#22c55e;--red:#ef4444;--blue:#3b82f6;--yellow:#eab308}
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:var(--bg);color:var(--text);font-family:system-ui,sans-serif;padding:1.5rem;min-height:100vh}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
        h1{font-size:1.5rem;font-weight:600}
        .logout{color:var(--muted);text-decoration:none;font-size:.875rem}
        .logout:hover{color:var(--text)}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem}
        .card{background:var(--card);border-radius:8px;padding:1.25rem}
        .card-title{font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem}
        .card-value{font-size:2rem;font-weight:700}
        .card-value.green{color:var(--green)}
        .card-value.red{color:var(--red)}
        .card-value.blue{color:var(--blue)}
        .card-value.yellow{color:var(--yellow)}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
        @media(max-width:768px){.row{grid-template-columns:1fr}}
        .section-title{font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--muted)}
        table{width:100%;border-collapse:collapse}
        th,td{text-align:left;padding:.75rem;border-bottom:1px solid var(--border)}
        th{font-size:.75rem;color:var(--muted);text-transform:uppercase;font-weight:500}
        td{font-size:.875rem}
        .badge{display:inline-block;padding:.25rem .5rem;border-radius:4px;font-size:.75rem;font-weight:500}
        .badge.green{background:rgba(34,197,94,.2);color:var(--green)}
        .badge.red{background:rgba(239,68,68,.2);color:var(--red)}
        .badge.yellow{background:rgba(234,179,8,.2);color:var(--yellow)}
        .ua{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.75rem;color:var(--muted)}
        .reason{font-size:.7rem;background:var(--bg);padding:.2rem .4rem;border-radius:3px;margin-right:.25rem;display:inline-block;margin-bottom:.25rem}
        .bar-chart{display:flex;align-items:end;height:100px;gap:2px;margin-top:1rem}
        .bar{flex:1;background:var(--border);border-radius:2px 2px 0 0;min-height:2px;position:relative}
        .bar .fill{position:absolute;bottom:0;left:0;right:0;border-radius:2px 2px 0 0}
        .bar .green-fill{background:var(--green)}
        .bar .red-fill{background:var(--red)}
        .chart-labels{display:flex;gap:2px;margin-top:.5rem}
        .chart-labels span{flex:1;text-align:center;font-size:.6rem;color:var(--muted)}
        .legend{display:flex;gap:1rem;margin-bottom:.5rem;font-size:.75rem}
        .legend span{display:flex;align-items:center;gap:.25rem}
        .legend .dot{width:8px;height:8px;border-radius:50%}
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ AntiBot Dashboard</h1>
        <a href="?logout=1" class="logout">Déconnexion</a>
    </div>

    <div class="grid">
        <div class="card">
            <div class="card-title">Total requêtes (7j)</div>
            <div class="card-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="card">
            <div class="card-title">Autorisées</div>
            <div class="card-value green"><?= number_format($stats['allowed']) ?></div>
        </div>
        <div class="card">
            <div class="card-title">Bloquées</div>
            <div class="card-value red"><?= number_format($stats['blocked']) ?></div>
        </div>
        <div class="card">
            <div class="card-title">Taux de blocage</div>
            <div class="card-value <?= $blockRate > 50 ? 'yellow' : 'blue' ?>"><?= $blockRate ?>%</div>
        </div>
    </div>

    <div class="row">
        <div class="card">
            <div class="section-title">📊 Activité par jour</div>
            <div class="legend">
                <span><span class="dot" style="background:var(--green)"></span> Autorisées</span>
                <span><span class="dot" style="background:var(--red)"></span> Bloquées</span>
            </div>
            <?php
            $maxDay = max(array_map(fn($d) => $d['allowed'] + $d['blocked'], $stats['by_day'])) ?: 1;
            ?>
            <div class="bar-chart">
                <?php foreach ($stats['by_day'] as $date => $day): ?>
                    <?php
                    $total = $day['allowed'] + $day['blocked'];
                    $height = $total / $maxDay * 100;
                    $greenH = $total > 0 ? $day['allowed'] / $total * $height : 0;
                    $redH = $total > 0 ? $day['blocked'] / $total * $height : 0;
                    ?>
                    <div class="bar" title="<?= $date ?>: <?= $day['allowed'] ?> OK, <?= $day['blocked'] ?> bloquées">
                        <div class="fill green-fill" style="height:<?= $greenH ?>%"></div>
                        <div class="fill red-fill" style="height:<?= $redH ?>%;bottom:<?= $greenH ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-labels">
                <?php foreach ($stats['by_day'] as $date => $day): ?>
                    <span><?= date('d/m', strtotime($date)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="section-title">🚫 Top raisons de blocage</div>
            <table>
                <tr><th>Raison</th><th>Count</th></tr>
                <?php foreach ($stats['top_reasons'] as $reason => $count): ?>
                    <tr>
                        <td><span class="reason"><?= getReasonLabel($reason) ?></span></td>
                        <td><?= $count ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($stats['top_reasons'])): ?>
                    <tr><td colspan="2" style="color:var(--muted)">Aucune donnée</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="card">
            <div class="section-title">🔥 Top IPs bloquées</div>
            <table>
                <tr><th>IP</th><th>Blocages</th></tr>
                <?php foreach ($stats['top_blocked_ips'] as $ip => $count): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($ip) ?></code></td>
                        <td><?= $count ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($stats['top_blocked_ips'])): ?>
                    <tr><td colspan="2" style="color:var(--muted)">Aucune donnée</td></tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="card">
            <div class="section-title">⏰ Activité par heure (aujourd'hui)</div>
            <?php $maxHour = max(array_map(fn($h) => $h['allowed'] + $h['blocked'], $stats['by_hour'])) ?: 1; ?>
            <div class="bar-chart" style="height:60px">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php
                    $total = $stats['by_hour'][$h]['allowed'] + $stats['by_hour'][$h]['blocked'];
                    $height = $total / $maxHour * 100;
                    ?>
                    <div class="bar" title="<?= $h ?>h: <?= $total ?> requêtes">
                        <div class="fill green-fill" style="height:<?= $height ?>%"></div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="chart-labels">
                <?php for ($h = 0; $h < 24; $h += 4): ?>
                    <span style="flex:4"><?= $h ?>h</span>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">📋 Dernières requêtes</div>
        <div style="overflow-x:auto">
            <table>
                <tr>
                    <th>Heure</th>
                    <th>Status</th>
                    <th>IP</th>
                    <th>Score</th>
                    <th>Raisons</th>
                    <th>User-Agent</th>
                </tr>
                <?php foreach (array_reverse($stats['recent']) as $entry): ?>
                    <tr>
                        <td><?= date('H:i:s', strtotime($entry['time'])) ?></td>
                        <td>
                            <?php if ($entry['type'] === 'allowed'): ?>
                                <span class="badge green">OK</span>
                            <?php elseif ($entry['type'] === 'blocked'): ?>
                                <span class="badge red">BLOQUÉ</span>
                            <?php else: ?>
                                <span class="badge yellow">CHALLENGE</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($entry['ip']) ?></code></td>
                        <td><?= $entry['score'] ?></td>
                        <td>
                            <?php foreach ($entry['reasons'] ?? [] as $r): ?>
                                <span class="reason"><?= getReasonLabel($r) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="ua" title="<?= htmlspecialchars($entry['ua']) ?>"><?= htmlspecialchars($entry['ua']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($stats['recent'])): ?>
                    <tr><td colspan="6" style="color:var(--muted)">Aucune requête enregistrée</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
