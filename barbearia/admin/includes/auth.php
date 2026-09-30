<?php
/**
 * Autenticação e autorização do painel administrativo.
 */
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/layout.php';

const ADMIN_SESSION_TTL = 7200;   // 2 horas de inatividade
const LOGIN_MAX_TENTATIVAS = 5;   // por IP a cada 15 minutos

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['admin_id'])) {
        return $user = null;
    }
    $st = db()->prepare('SELECT id, nome, email, tipo FROM usuarios WHERE id = ? AND ativo = 1');
    $st->execute([$_SESSION['admin_id']]);
    return $user = ($st->fetch() ?: null);
}

function require_login(): array
{
    $expirou = !empty($_SESSION['admin_last']) && (time() - $_SESSION['admin_last']) > ADMIN_SESSION_TTL;
    if ($expirou) {
        unset($_SESSION['admin_id'], $_SESSION['admin_last']);
        flash('warning', 'Sua sessão expirou por inatividade. Entre novamente.');
    }
    $u = current_user();
    if (!$u || $expirou) {
        redirect('admin/login.php');
    }
    $_SESSION['admin_last'] = time();
    header('Cache-Control: no-store, private');
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['tipo'] !== 'admin') {
        flash('error', 'Acesso restrito ao administrador.');
        redirect('admin/dashboard.php');
    }
    return $u;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['tipo'] === 'admin';
}

/** Valida CSRF em ações POST do painel; redireciona com mensagem em caso de falha. */
function admin_post_guard(string $back): void
{
    if (!csrf_valid()) {
        flash('error', 'Sessão expirada ou requisição inválida. Tente novamente.');
        redirect($back);
    }
}

function login_bloqueado(string $ip): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_tentativas WHERE ip = ? AND criado_em > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([$ip]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_TENTATIVAS;
}

function registrar_falha_login(string $ip, string $email): void
{
    db()->prepare('INSERT INTO login_tentativas (ip, email) VALUES (?, ?)')->execute([$ip, substr($email, 0, 160)]);
    db()->exec('DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $u['id'];
    $_SESSION['admin_last'] = time();
    unset($_SESSION['_csrf']);
}

/** Retorna mensagem de erro se a senha não atender à política mínima, ou null. */
function senha_fraca(string $senha): ?string
{
    if (strlen($senha) < 8) {
        return 'A senha deve ter pelo menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        return 'A senha deve conter letras e números.';
    }
    return null;
}
