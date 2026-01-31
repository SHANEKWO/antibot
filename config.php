<?php
/**
 * ============================================
 * CONFIGURATION ANTI-BOT
 * ============================================
 */

date_default_timezone_set('Europe/Paris');

return [
    // ====== REDIRECTIONS ======
    'target_url' => 'https://example.com',       // Humains → ici
    'block_url' => 'https://google.fr',          // Bots → ici (invisible)

    // ====== SEUILS (MODE STRICT) ======
    'instant_redirect_threshold' => 10,          // < 10 = redirect instantané (humain parfait)
    'challenge_threshold' => 15,                 // >= 15 = challenge JS
    'block_threshold' => 30,                     // >= 30 = redirigé vers block_url

    // ====== OPTIONS ======
    'pow_difficulty' => 4,
    'session_cookie' => '__av',
    'cookie_lifetime' => 86400,

    // ====== DASHBOARD ======
    'dashboard_password' => 'admin123',          // Change ce mot de passe!
];
