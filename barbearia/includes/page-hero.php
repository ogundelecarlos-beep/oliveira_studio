<?php
/** Banner das páginas internas. Variáveis: $heroEyebrow, $heroTitle (HTML permitido), $heroText, $heroImage */
?>
<section class="page-hero">
  <div class="page-hero-media"><img src="<?= e($heroImage ?? 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=2000&q=80') ?>" alt=""></div>
  <div class="container page-hero-content">
    <nav class="breadcrumb" aria-label="Você está em"><a href="<?= e(url('index.php')) ?>">Início</a><span>/</span><span aria-current="page"><?= e($heroEyebrow ?? '') ?></span></nav>
    <h1 class="display"><?= $heroTitle ?? '' ?></h1>
    <?php if (!empty($heroText)): ?><p><?= e($heroText) ?></p><?php endif; ?>
  </div>
</section>
