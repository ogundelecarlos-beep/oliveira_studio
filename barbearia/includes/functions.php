<?php
/**
 * Funções utilitárias: escape, URLs, CSRF, mensagens, validação,
 * formatação, uploads e acesso às configurações.
 */

/* ------------------------------------------------------------------ */
/* Saída e URLs                                                        */
/* ------------------------------------------------------------------ */

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Caminho base da aplicação (ex.: "" ou "/barbearia"). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (APP_URL !== '') {
        return $base = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
        $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    } else {
        $base = '';
    }
    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** URL absoluta (usada em mensagens de WhatsApp e e-mails). */
function absolute_url(string $path = ''): string
{
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https://' : 'http://') . $host . url($path);
}

/** Arquivo estático com versão (cache busting). */
function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : '1';
    return url($path) . '?v=' . $v;
}

/** Aceita URL externa ou caminho relativo salvo no banco. */
function img_src(?string $value, string $fallback = 'img/placeholder.svg'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return url($fallback);
    }
    if (preg_match('~^https?://~i', $value)) {
        return $value;
    }
    return url($value);
}

function redirect(string $path): void
{
    $target = preg_match('~^https?://~i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return (is_string($v) && preg_match('/^-?\d+$/', trim($v))) ? (int) $v : $default;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
}

/* ------------------------------------------------------------------ */
/* Mensagens flash                                                     */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $list = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $list;
}

function render_flashes(): string
{
    $html = '';
    foreach (flashes() as $f) {
        $type = in_array($f['type'], ['success', 'error', 'warning', 'info'], true) ? $f['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">'
            . '<span>' . e($f['message']) . '</span>'
            . '<button type="button" class="alert-close" aria-label="Fechar">&times;</button></div>';
    }
    return $html ? '<div class="flash-stack">' . $html . '</div>' : '';
}

/* ------------------------------------------------------------------ */
/* Validação                                                           */
/* ------------------------------------------------------------------ */

function only_digits(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}

/** Telefone brasileiro: DDD válido + 8 (fixo) ou 9 dígitos (celular iniciando em 9). */
function valid_phone(string $digits): bool
{
    if (strlen($digits) === 13 && strpos($digits, '55') === 0) {
        $digits = substr($digits, 2);
    }
    if (!preg_match('/^[1-9][1-9]\d{8,9}$/', $digits)) {
        return false;
    }
    if (strlen($digits) === 11 && $digits[2] !== '9') {
        return false;
    }
    return !preg_match('/^(\d)\1+$/', $digits);
}

function normalize_phone(string $v): string
{
    $d = only_digits($v);
    if (strlen($d) === 13 && strpos($d, '55') === 0) {
        $d = substr($d, 2);
    }
    return $d;
}

function valid_email(string $v): bool
{
    return strlen($v) <= 160 && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_date(string $v): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
}

function valid_time(string $v): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:00)?$/', $v);
}

function str_len(string $v): int
{
    return function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
}

/* ------------------------------------------------------------------ */
/* Formatação                                                          */
/* ------------------------------------------------------------------ */

function money($v): string
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

function date_br(?string $ymd): string
{
    if (!$ymd) {
        return '—';
    }
    $t = strtotime($ymd);
    return $t ? date('d/m/Y', $t) : '—';
}

function hm(?string $time): string
{
    return $time ? substr($time, 0, 5) : '';
}

function format_phone(?string $digits): string
{
    $d = only_digits((string) $digits);
    if (strlen($d) === 13 && strpos($d, '55') === 0) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return (string) $digits;
}

function duracao_label(int $min): string
{
    if ($min < 60) {
        return $min . ' min';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $h . 'h' . ($m ? sprintf('%02d', $m) : '');
}

function dias_semana(): array
{
    return ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
}

function data_extenso(string $ymd): string
{
    $meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = strtotime($ymd);
    return dias_semana()[(int) date('w', $t)] . ', ' . (int) date('d', $t) . ' de ' . $meses[(int) date('n', $t)];
}

function status_labels(): array
{
    return [
        'pendente'   => 'Pendente',
        'confirmado' => 'Confirmado',
        'concluido'  => 'Concluído',
        'cancelado'  => 'Cancelado',
    ];
}

function status_badge(string $status): string
{
    $label = status_labels()[$status] ?? ucfirst($status);
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

function categorias_galeria(): array
{
    return [
        'degrade'   => 'Degradê',
        'social'    => 'Corte Social',
        'barba'     => 'Barba',
        'freestyle' => 'Freestyle',
        'outros'    => 'Outros',
    ];
}

function tipos_bloqueio(): array
{
    return [
        'feriado'     => 'Feriado',
        'folga'       => 'Folga',
        'manutencao'  => 'Manutenção',
        'compromisso' => 'Compromisso',
        'outro'       => 'Outro',
    ];
}

function stars(float $nota, string $class = 'stars'): string
{
    $html = '<span class="' . e($class) . '" aria-label="Nota ' . number_format($nota, 1, ',', '') . ' de 5">';
    for ($i = 1; $i <= 5; $i++) {
        $fill = $nota >= $i ? 'full' : ($nota >= $i - 0.5 ? 'half' : 'empty');
        $html .= '<svg class="star ' . $fill . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.83 5.73 6.33.92-4.58 4.46 1.08 6.3L12 17.24l-5.66 2.97 1.08-6.3L2.84 9.45l6.33-.92z"/></svg>';
    }
    return $html . '</span>';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? end($parts) : '';
    $f = function_exists('mb_substr') ? mb_substr($first, 0, 1) : substr($first, 0, 1);
    $l = function_exists('mb_substr') ? mb_substr($last, 0, 1) : substr($last, 0, 1);
    return strtoupper($f . $l);
}

function especialidades_list(?string $v): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $v))));
}

/* ------------------------------------------------------------------ */
/* Configurações da barbearia                                          */
/* ------------------------------------------------------------------ */

function settings(bool $refresh = false): array
{
    static $cfg = null;
    if ($cfg === null || $refresh) {
        $cfg = db()->query('SELECT * FROM configuracoes WHERE id = 1')->fetch() ?: [];
        $cfg += [
            'nome_barbearia' => 'Barbearia', 'slogan' => '', 'logo' => '', 'endereco' => '', 'telefone' => '',
            'whatsapp' => '', 'email' => '', 'instagram' => '', 'facebook' => '', 'tiktok' => '', 'mapa' => '',
            'mapa_link' => '', 'horario_funcionamento' => '', 'antecedencia_minima' => 30, 'dias_antecedencia' => 45,
        ];
    }
    return $cfg;
}

function whatsapp_link(string $message = '', ?string $number = null): string
{
    $n = only_digits($number ?? (string) settings()['whatsapp']);
    if ($n !== '' && strlen($n) <= 11) {
        $n = '55' . $n;
    }
    return 'https://wa.me/' . $n . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function instagram_url(): string
{
    $ig = ltrim((string) settings()['instagram'], '@');
    return $ig !== '' ? 'https://instagram.com/' . rawurlencode($ig) : '#';
}

/** Horário de funcionamento agrupado para exibição (ex.: "Segunda a Sexta — 09:00 às 20:00"). */
function horario_resumo(): array
{
    $rows = db()->query('SELECT dia_semana, hora_inicio, hora_fim FROM horarios WHERE barbeiro_id IS NULL ORDER BY dia_semana, hora_inicio')->fetchAll();
    $byDay = array_fill(0, 7, []);
    foreach ($rows as $r) {
        $byDay[(int) $r['dia_semana']][] = hm($r['hora_inicio']) . ' às ' . hm($r['hora_fim']);
    }
    $order = [1, 2, 3, 4, 5, 6, 0];
    $groups = [];
    foreach ($order as $d) {
        $label = $byDay[$d] ? implode(' / ', $byDay[$d]) : 'Fechado';
        $lastKey = array_key_last($groups);
        if ($lastKey !== null && $groups[$lastKey]['label'] === $label) {
            $groups[$lastKey]['to'] = $d;
        } else {
            $groups[] = ['from' => $d, 'to' => $d, 'label' => $label];
        }
    }
    $nomes = dias_semana();
    $out = [];
    foreach ($groups as $g) {
        $dias = $g['from'] === $g['to'] ? $nomes[$g['from']] : $nomes[$g['from']] . ' a ' . $nomes[$g['to']];
        $out[] = ['dias' => $dias, 'horario' => $g['label']];
    }
    return $out;
}

/** Promoção vigente para um serviço em determinada data (ou null). */
function promocao_para_servico(int $servicoId, string $data): ?array
{
    $st = db()->prepare('SELECT * FROM promocoes WHERE servico_id = ? AND ativo = 1 AND ? BETWEEN data_inicio AND data_fim ORDER BY preco_promocional ASC LIMIT 1');
    $st->execute([$servicoId, $data]);
    return $st->fetch() ?: null;
}

function promocoes_vigentes(): array
{
    return db()->query('SELECT p.*, s.nome AS servico_nome FROM promocoes p LEFT JOIN servicos s ON s.id = p.servico_id
                        WHERE p.ativo = 1 AND CURDATE() BETWEEN p.data_inicio AND p.data_fim ORDER BY p.data_fim ASC')->fetchAll();
}

/** Média e total de avaliações aprovadas por barbeiro. */
function notas_barbeiros(): array
{
    $rows = db()->query('SELECT a.barbeiro_id, AVG(av.nota) AS media, COUNT(*) AS total
                         FROM avaliacoes av JOIN agendamentos a ON a.id = av.agendamento_id
                         WHERE av.aprovado = 1 GROUP BY a.barbeiro_id')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['barbeiro_id']] = ['media' => (float) $r['media'], 'total' => (int) $r['total']];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Upload de imagens                                                   */
/* ------------------------------------------------------------------ */

/**
 * Salva uma imagem enviada em uploads/{pasta}. Retorna o caminho relativo,
 * null se nenhum arquivo foi enviado, ou lança RuntimeException com mensagem clara.
 */
function upload_image(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if (is_array($f['error'])) {
        throw new RuntimeException('Envie apenas um arquivo por vez.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'A imagem excede o tamanho máximo permitido.'
            : 'Falha no envio da imagem. Tente novamente.');
    }
    if ($f['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('A imagem deve ter no máximo ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('Formato inválido. Envie uma imagem JPG, PNG ou WEBP.');
    }
    $folder = preg_replace('/[^a-z0-9_-]/', '', strtolower($folder));
    $dir = APP_ROOT . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de uploads. Verifique as permissões.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Não foi possível salvar a imagem. Verifique as permissões da pasta uploads/.');
    }
    return 'uploads/' . $folder . '/' . $name;
}

/** Remove um arquivo local enviado anteriormente (ignora URLs externas). */
function delete_upload(?string $path): void
{
    $path = (string) $path;
    if ($path === '' || strpos($path, 'uploads/') !== 0 || strpos($path, '..') !== false) {
        return;
    }
    $file = APP_ROOT . '/' . $path;
    if (is_file($file)) {
        @unlink($file);
    }
}

/** Converte "1.234,56", "35,00" ou "35.00" em "1234.56"; retorna '' se vazio. */
function parse_money(string $v): string
{
    $v = trim(str_replace(['R$', ' '], '', $v));
    if ($v === '') {
        return '';
    }
    if (strpos($v, ',') !== false) {
        $v = str_replace(['.', ','], ['', '.'], $v);
    }
    return $v;
}
