<?php
/**
 * Layout do painel administrativo.
 */

function admin_header(string $title, string $active, array $opts = []): void
{
    $u = current_user();
    $cfg = settings();
    $pendAval = (int) db()->query('SELECT COUNT(*) FROM avaliacoes WHERE aprovado = 0')->fetchColumn();
    $pendMsg = (int) db()->query('SELECT COUNT(*) FROM mensagens WHERE lida = 0')->fetchColumn();
    $pendAg = (int) db()->query("SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente' AND data >= CURDATE()")->fetchColumn();

    $menu = [
        ['dashboard', 'Dashboard', 'dashboard.php', 'dashboard', 0, false],
        ['agenda', 'Agenda', 'agenda.php', 'calendar', $pendAg, false],
        ['clientes', 'Clientes', 'clientes.php', 'users', 0, false],
        ['barbeiros', 'Barbeiros', 'barbeiros.php', 'scissors', 0, false],
        ['servicos', 'Serviços', 'servicos.php', 'list', 0, false],
        ['horarios', 'Horários', 'horarios.php', 'clock', 0, false],
        ['promocoes', 'Promoções', 'promocoes.php', 'tag', 0, false],
        ['avaliacoes', 'Avaliações', 'avaliacoes.php', 'star', $pendAval, false],
        ['galeria', 'Galeria', 'galeria.php', 'image', 0, false],
        ['mensagens', 'Mensagens', 'mensagens.php', 'message', $pendMsg, false],
        ['usuarios', 'Usuários', 'usuarios.php', 'lock', 0, true],
        ['configuracoes', 'Configurações', 'configuracoes.php', 'settings', 0, true],
    ];
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Painel <?= e($cfg['nome_barbearia']) ?></title>
<link rel="icon" href="<?= e(url('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script>window.APP = { base: <?= json_encode(base_path()) ?>, csrf: <?= json_encode(csrf_token()) ?> };</script>
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
      <?php include APP_ROOT . '/includes/logo-mark.php'; ?>
      <span><strong><?= e($cfg['nome_barbearia']) ?></strong><small>Painel</small></span>
    </a>
    <nav class="sidebar-nav" aria-label="Menu do painel">
      <?php foreach ($menu as [$key, $label, $href, $ic, $count, $adminOnly]):
          if ($adminOnly && $u['tipo'] !== 'admin') continue; ?>
        <a href="<?= e(url('admin/' . $href)) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
          <?= icon($ic) ?><span><?= e($label) ?></span>
          <?php if ($count > 0): ?><em class="count"><?= $count ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><?= icon('external') ?><span>Ver site</span></a>
    </div>
  </aside>
  <div class="sidebar-overlay" data-sidebar-close></div>

  <div class="admin-main">
    <header class="topbar">
      <button type="button" class="icon-btn menu-btn" data-sidebar-toggle aria-label="Abrir menu" aria-controls="sidebar"><?= icon('menu') ?></button>
      <div class="topbar-title">
        <h1><?= e($title) ?></h1>
        <?php if (!empty($opts['subtitle'])): ?><p><?= e($opts['subtitle']) ?></p><?php endif; ?>
      </div>
      <div class="topbar-actions">
        <?= $opts['actions'] ?? '' ?>
        <details class="user-menu">
          <summary><span class="avatar sm"><?= e(initials($u['nome'])) ?></span><span class="user-name"><?= e($u['nome']) ?><small><?= $u['tipo'] === 'admin' ? 'Administrador' : 'Funcionário' ?></small></span></summary>
          <div class="user-dropdown">
            <a href="<?= e(url('admin/perfil.php')) ?>"><?= icon('user') ?> Meu perfil</a>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?> Sair</button></form>
          </div>
        </details>
      </div>
    </header>
    <main class="admin-content">
      <?= render_flashes() ?>
<?php
}

function admin_footer(array $scripts = []): void
{
    ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/script.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
<?php
}

/** Paginação simples. Retorna [offset, html]. */
function paginate(int $total, int $perPage, int $page, array $query = []): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, $page));
    $html = '';
    if ($pages > 1) {
        $html = '<nav class="pagination" aria-label="Paginação">';
        for ($i = 1; $i <= $pages; $i++) {
            $q = http_build_query(array_merge($query, ['p' => $i]));
            $html .= $i === $page
                ? '<span class="is-current" aria-current="page">' . $i . '</span>'
                : '<a href="?' . e($q) . '">' . $i . '</a>';
        }
        $html .= '</nav>';
    }
    return [($page - 1) * $perPage, $html];
}

/** Campo de upload com pré-visualização reutilizável. */
function image_field(string $name, string $label, ?string $current = null, string $fallback = 'img/placeholder.svg'): string
{
    return '<div class="field image-field"><label>' . e($label) . '</label>'
        . '<div class="image-picker"><img src="' . e(img_src($current, $fallback)) . '" alt="" data-preview-for="' . e($name) . '" data-default="' . e(url($fallback)) . '">'
        . '<div><label class="btn btn-ghost btn-sm file-btn">' . icon('upload') . ' Escolher imagem<input type="file" name="' . e($name) . '" accept="image/jpeg,image/png,image/webp" data-preview></label>'
        . '<small class="muted">JPG, PNG ou WEBP até ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB.</small></div></div></div>';
}

/* Páginas de autenticação (login, recuperação de senha) */
function auth_page_start(string $title): void
{
    $cfg = settings(); ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Painel <?= e($cfg['nome_barbearia']) ?></title>
<link rel="icon" href="<?= e(url('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth-page">
<div class="auth-visual" aria-hidden="true">
  <img src="https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=1400&q=80" alt="" onerror="this.remove()">
  <div class="auth-quote"><span>“</span>Precisão é um hábito, não um acaso.<small>— <?= e($cfg['nome_barbearia']) ?></small></div>
</div>
<main class="auth-box">
  <a class="sidebar-brand" href="<?= e(url('index.php')) ?>">
    <?php include APP_ROOT . '/includes/logo-mark.php'; ?>
    <span><strong><?= e($cfg['nome_barbearia']) ?></strong><small>Painel administrativo</small></span>
  </a>
<?php
}

function auth_page_end(): void
{ ?>
  <a class="auth-back" href="<?= e(url('index.php')) ?>"><?= icon('arrow-left') ?> Voltar ao site</a>
</main>
<script src="<?= e(asset('js/script.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}
