<?php
/**
 * Cabeçalho do site público.
 * Variáveis esperadas: $pageTitle, $pageDescription (opcional), $active (opcional), $bodyClass (opcional)
 */
$cfg = settings();
$active = $active ?? '';
$titleFull = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | ' . $cfg['nome_barbearia'] : $cfg['nome_barbearia'] . ' — ' . ($cfg['slogan'] ?: 'Barbearia');
$desc = $pageDescription ?? 'Barbearia premium com cortes masculinos, barba na toalha quente e agendamento online. Estilo, precisão e cuidado em cada detalhe.';
$menu = [
    'inicio'     => ['Início', 'index.php'],
    'servicos'   => ['Serviços', 'servicos.php'],
    'barbeiros'  => ['Barbeiros', 'barbeiros.php'],
    'galeria'    => ['Galeria', 'galeria.php'],
    'avaliacoes' => ['Avaliações', 'index.php#avaliacoes'],
    'contato'    => ['Contato', 'contato.php'],
];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titleFull) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0c0c0c">
<meta property="og:title" content="<?= e($titleFull) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="<?= e(url('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('fonts/big-shoulders-display-latin-800-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script>window.APP = { base: <?= json_encode(base_path()) ?>, csrf: <?= json_encode(csrf_token()) ?> };</script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<header class="site-header" id="topo">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($cfg['nome_barbearia']) ?> — página inicial">
      <?php if (!empty($cfg['logo'])): ?>
        <img src="<?= e(img_src($cfg['logo'])) ?>" alt="<?= e($cfg['nome_barbearia']) ?>" class="brand-logo">
      <?php else: ?>
        <?php include APP_ROOT . '/includes/logo-mark.php'; ?>
        <span class="brand-text">
          <strong><?= e($cfg['nome_barbearia']) ?></strong>
          <small><?= e($cfg['slogan'] ?: 'Barbearia') ?></small>
        </span>
      <?php endif; ?>
    </a>

    <nav class="main-nav" id="main-nav" aria-label="Menu principal">
      <ul>
        <?php foreach ($menu as $key => [$label, $href]): ?>
          <li><a href="<?= e(url($href)) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <a href="<?= e(url('agendamento.php')) ?>" class="btn btn-gold nav-cta">Agendar horário</a>
    </nav>

    <a href="<?= e(url('agendamento.php')) ?>" class="btn btn-gold header-cta">Agendar horário</a>

    <button class="nav-toggle" type="button" aria-controls="main-nav" aria-expanded="false" aria-label="Abrir menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<main id="conteudo">
<?= render_flashes() ?>
