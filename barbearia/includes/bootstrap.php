<?php
/**
 * Inicialização comum a todas as páginas (site, admin e API).
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config/config.php';
require APP_ROOT . '/config/database.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/disponibilidade.php';
require APP_ROOT . '/includes/icons.php';

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// Sessão segura
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = is_https();
    session_name('barbearia_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Cabeçalhos de segurança
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
