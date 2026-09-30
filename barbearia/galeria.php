<?php
require __DIR__ . '/includes/bootstrap.php';

$categorias = categorias_galeria();
$fotos = db()->query('SELECT * FROM galeria WHERE ativo = 1 ORDER BY id DESC')->fetchAll();
$usadas = array_unique(array_column($fotos, 'categoria'));
$inicial = array_key_exists(input('categoria'), $categorias) ? input('categoria') : 'all';

$pageTitle = 'Galeria';
$pageDescription = 'Galeria de cortes: degradê, social, barba e freestyle feitos pela nossa equipe.';
$active = 'galeria';
$heroEyebrow = 'Galeria';
$heroTitle = 'Nosso <em>portfólio</em>';
$heroText = 'Uma amostra do que sai das nossas cadeiras todos os dias.';
$heroImage = 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&w=2000&q=80';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container">
    <div class="filters filters-center" role="tablist" aria-label="Filtrar galeria" data-filter-group="full-gallery" data-initial="<?= e($inicial) ?>">
      <button type="button" class="filter is-active" data-filter="all">Todos <small><?= count($fotos) ?></small></button>
      <?php foreach ($categorias as $k => $label): if (!in_array($k, $usadas, true)) continue; ?>
        <button type="button" class="filter" data-filter="<?= e($k) ?>"><?= e($label) ?> <small><?= count(array_filter($fotos, fn($f) => $f['categoria'] === $k)) ?></small></button>
      <?php endforeach; ?>
    </div>
    <div class="gallery-grid gallery-full" data-gallery="full-gallery">
      <?php foreach ($fotos as $g): ?>
        <a class="gallery-item" href="<?= e(img_src($g['imagem'])) ?>" data-category="<?= e($g['categoria']) ?>" data-caption="<?= e($g['titulo']) ?>">
          <img src="<?= e(img_src($g['imagem'])) ?>" alt="<?= e($g['titulo'] ?: 'Corte ' . $categorias[$g['categoria']]) ?>" loading="lazy">
          <span class="gallery-caption"><small><?= e($categorias[$g['categoria']]) ?></small><?= e($g['titulo']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="empty" data-gallery-empty hidden>Nenhuma foto nesta categoria ainda.</p>
    <?php if (!$fotos): ?><p class="empty">A galeria está sendo atualizada. Volte em breve!</p><?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/lightbox.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
