<?php
require __DIR__ . '/includes/bootstrap.php';

$servicos = db()->query('SELECT * FROM servicos WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
$hoje = date('Y-m-d');

$pageTitle = 'Serviços';
$pageDescription = 'Cortes masculinos, barba, degradê, sobrancelha, pigmentação e tratamentos. Confira preços e duração e agende online.';
$active = 'servicos';
$heroEyebrow = 'Serviços';
$heroTitle = 'Serviços & <em>preços</em>';
$heroText = 'Transparência do começo ao fim: você sabe quanto custa e quanto tempo leva antes de sentar na cadeira.';
$heroImage = 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=2000&q=80';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container">
    <div class="service-list">
      <?php foreach ($servicos as $i => $s):
          $sp = promocao_para_servico((int) $s['id'], $hoje); ?>
        <article class="service-row reveal">
          <div class="service-row-media">
            <img src="<?= e(img_src($s['foto'])) ?>" alt="<?= e($s['nome']) ?>" loading="lazy">
            <?php if ($sp): ?><span class="chip chip-gold">Promoção</span><?php endif; ?>
          </div>
          <div class="service-row-body">
            <span class="service-num"><?= sprintf('%02d', $i + 1) ?></span>
            <h2 class="display"><?= e($s['nome']) ?></h2>
            <p><?= e($s['descricao']) ?></p>
            <span class="duration"><?= icon('clock') ?> <?= e(duracao_label((int) $s['duracao'])) ?></span>
          </div>
          <div class="service-row-action">
            <div class="price price-lg">
              <?php if ($sp): ?><del><?= e(money($s['preco'])) ?></del><?php endif; ?>
              <strong><?= e(money($sp ? $sp['preco_promocional'] : $s['preco'])) ?></strong>
            </div>
            <a href="<?= e(url('agendamento.php?servico=' . (int) $s['id'])) ?>" class="btn btn-gold">Agendar <?= icon('arrow-right') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$servicos): ?><p class="empty">Nenhum serviço disponível no momento.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
