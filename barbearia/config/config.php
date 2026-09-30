<?php
/**
 * Configurações gerais da aplicação.
 *
 * Os valores podem ser sobrescritos por variáveis de ambiente
 * (DB_HOST, DB_NAME, DB_USER, DB_PASS, APP_URL, APP_DEBUG) ou
 * por um arquivo config/config.local.php (não versionado).
 */

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require $local;
}

$env = static function (string $key, $default) {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
};

defined('DB_HOST') || define('DB_HOST', $env('DB_HOST', '127.0.0.1'));
defined('DB_PORT') || define('DB_PORT', (int) $env('DB_PORT', 3306));
defined('DB_NAME') || define('DB_NAME', $env('DB_NAME', 'barbearia'));
defined('DB_USER') || define('DB_USER', $env('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', $env('DB_PASS', ''));

// URL base (sem barra no final). Vazio = detecção automática.
defined('APP_URL') || define('APP_URL', $env('APP_URL', ''));

// Em produção mantenha false. Em modo debug, o link de recuperação de
// senha é exibido na tela quando não há servidor de e-mail configurado.
defined('APP_DEBUG') || define('APP_DEBUG', filter_var($env('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN));

defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'America/Sao_Paulo');
defined('UPLOAD_MAX_BYTES') || define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);

date_default_timezone_set(APP_TIMEZONE);
