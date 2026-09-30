<?php
require __DIR__ . '/includes/auth.php';

if (is_post() && csrf_valid()) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    flash('success', 'Você saiu do painel com segurança.');
}
redirect('admin/login.php');
