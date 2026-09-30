<?php
require __DIR__ . '/includes/bootstrap.php';

$barbeiros = barbeiros_ativos();
$notas = notas_barbeiros();
$atendimentos = [];
foreach (db()->query("SELECT barbeiro_id, COUNT(*) c FROM agendamentos WHERE status = 'concluido' GROUP BY barbeiro_id")->fetchAll() as $r) {
    $atendimentos[(int) $r['barbeiro_id']] = (int) $r['c'];
}

$pageTitle = 'Barbeiros';
$pageDescription = 'Conheça a equipe de barbeiros, suas especialidades e avaliações. Agende com o seu profissional preferido.';
$active = 'barbeiros';
$heroEyebrow = 'Barbeiros';
$heroTitle = 'Nossa <em>equipe</em>';
$heroText = 'Profissionais apaixonados pelo ofício, cada um com uma assinatura própria.';
$heroImage = 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&w=2000&q=80';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container">
    <div class="barber-profiles">
      <?php foreach ($barbeiros as $i => $b):
          $n = $notas[(int) $b['id']] ?? ['media' => 5.0, 'total' => 0]; ?>
        <article class="barber-profile reveal <?= $i % 2 ? 'is-reverse' : '' ?>">
          <div class="barber-profile-media">
            <img src="<?= e(img_src($b['foto'], 'img/avatar.svg')) ?>" alt="Foto de <?= e($b['nome']) ?>" loading="lazy">
          </div>
          <div class="barber-profile-body">
            <span class="eyebrow"><b><?= sprintf('%02d', $i + 1) ?></b> Barbeiro</span>
            <h2 class="display"><?= e($b['nome']) ?></h2>
            <div class="rating-inline"><?= stars($n['media']) ?><span><?= number_format($n['media'], 1, ',', '') ?> · <?= (int) $n['total'] ?> avaliações</span></div>
            <ul class="tags"><?php foreach (especialidades_list($b['especialidades']) as $esp): ?><li><?= e($esp) ?></li><?php endforeach; ?></ul>
            <p><?= e($b['descricao']) ?></p>
            <dl class="mini-stats">
              <div><dt>Atendimentos</dt><dd><?= (int) ($atendimentos[(int) $b['id']] ?? 0) ?></dd></div>
              <div><dt>Especialidades</dt><dd><?= count(especialidades_list($b['especialidades'])) ?></dd></div>
            </dl>
            <a href="<?= e(url('agendamento.php?barbeiro=' . (int) $b['id'])) ?>" class="btn btn-gold">Agendar com este profissional <?= icon('arrow-right') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$barbeiros): ?><p class="empty">Nenhum profissional disponível no momento.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
