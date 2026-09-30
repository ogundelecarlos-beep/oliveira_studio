<?php
/**
 * Roteador para o servidor embutido do PHP (somente desenvolvimento):
 *   php -S localhost:8000 router.php
 * Replica as regras de proteção dos arquivos .htaccess.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (preg_match('~^/(config|includes|database)(/|$)~', $path)
    || preg_match('~^/uploads/.*\.(php|phtml|phar)$~i', $path)
    || preg_match('~\.(sql|md|log|ini)$~i', $path)
    || preg_match('~/\.~', $path)) {
    http_response_code(403);
    exit('Acesso negado.');
}
if ($path === '/admin' || $path === '/admin/') {
    header('Location: /admin/index.php');
    exit;
}
return false;
