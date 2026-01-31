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

    // ====== SEUILS ======
    'instant_redirect_threshold' => 25,          // < 25 = redirect instantané
    'challenge_threshold' => 40,                 // >= 40 = challenge JS
    'block_threshold' => 70,                     // >= 70 = redirigé vers block_url

    // ====== OPTIONS ======
    'pow_difficulty' => 4,
    'session_cookie' => '__av',
    'cookie_lifetime' => 86400,

    // ====== DASHBOARD ======
    'dashboard_password' => 'admin123',          // Change ce mot de passe!
];
