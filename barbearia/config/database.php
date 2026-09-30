<?php
/**
 * Conexão PDO única (singleton).
 * Prepared statements reais (sem emulação) para proteção contra SQL Injection.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
        $pdo->exec("SET time_zone = '{$offset}'");
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Falha na conexão com o banco: ' . $e->getMessage());
        $msg = APP_DEBUG
            ? 'Não foi possível conectar ao banco de dados. Verifique config/config.php. Detalhe: ' . htmlspecialchars($e->getMessage())
            : 'Serviço temporariamente indisponível. Tente novamente em instantes.';
        exit('<!doctype html><meta charset="utf-8"><title>Erro</title><body style="font-family:sans-serif;background:#0d0d0d;color:#eee;padding:40px"><h1>Erro de conexão</h1><p>' . $msg . '</p></body>');
    }
    return $pdo;
}
