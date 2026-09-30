<?php
$cfg = settings();
$horarios = horario_resumo();
?>
</main>

<footer class="site-footer">
  <div class="container footer-cta">
    <div>
      <span class="eyebrow">Sua cadeira está esperando</span>
      <h2 class="display">Reserve em menos<br>de <em>um minuto.</em></h2>
    </div>
    <a href="<?= e(url('agendamento.php')) ?>" class="btn btn-gold btn-lg">Agendar horário <?= icon('arrow-right') ?></a>
  </div>

  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand" href="<?= e(url('index.php')) ?>">
        <?php if (!empty($cfg['logo'])): ?>
          <img src="<?= e(img_src($cfg['logo'])) ?>" alt="<?= e($cfg['nome_barbearia']) ?>" class="brand-logo">
        <?php else: ?>
          <?php include APP_ROOT . '/includes/logo-mark.php'; ?>
          <span class="brand-text"><strong><?= e($cfg['nome_barbearia']) ?></strong><small><?= e($cfg['slogan'] ?: 'Barbearia') ?></small></span>
        <?php endif; ?>
      </a>
      <p>Tradição de barbearia com olhar contemporâneo. Cortes precisos, barba no ritual da toalha quente e um ambiente feito para você desacelerar.</p>
      <div class="social">
        <?php if (!empty($cfg['instagram'])): ?><a href="<?= e(instagram_url()) ?>" target="_blank" rel="noopener" aria-label="Instagram"><?= icon('instagram') ?></a><?php endif; ?>
        <?php if (!empty($cfg['whatsapp'])): ?><a href="<?= e(whatsapp_link('Olá! Vim pelo site.')) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('whatsapp') ?></a><?php endif; ?>
        <?php if (!empty($cfg['facebook'])): ?><a href="<?= e($cfg['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><?= icon('facebook') ?></a><?php endif; ?>
        <?php if (!empty($cfg['tiktok'])): ?><a href="https://www.tiktok.com/@<?= e(rawurlencode(ltrim($cfg['tiktok'], '@'))) ?>" target="_blank" rel="noopener" aria-label="TikTok"><?= icon('tiktok') ?></a><?php endif; ?>
      </div>
    </div>

    <div>
      <h3 class="footer-title">Navegação</h3>
      <ul class="footer-links">
        <li><a href="<?= e(url('index.php')) ?>">Início</a></li>
        <li><a href="<?= e(url('servicos.php')) ?>">Serviços</a></li>
        <li><a href="<?= e(url('barbeiros.php')) ?>">Barbeiros</a></li>
        <li><a href="<?= e(url('galeria.php')) ?>">Galeria</a></li>
        <li><a href="<?= e(url('index.php#avaliacoes')) ?>">Avaliações</a></li>
        <li><a href="<?= e(url('contato.php')) ?>">Contato</a></li>
        <li><a href="<?= e(url('agendamento.php')) ?>">Agendamento</a></li>
      </ul>
    </div>

    <div>
      <h3 class="footer-title">Visite</h3>
      <ul class="footer-info">
        <?php if ($cfg['endereco']): ?><li><?= icon('map-pin') ?><span><?= e($cfg['endereco']) ?></span></li><?php endif; ?>
        <?php if ($cfg['telefone']): ?><li><?= icon('phone') ?><a href="tel:<?= e(only_digits($cfg['telefone'])) ?>"><?= e($cfg['telefone']) ?></a></li><?php endif; ?>
        <?php if ($cfg['whatsapp']): ?><li><?= icon('whatsapp') ?><a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener"><?= e(format_phone($cfg['whatsapp'])) ?></a></li><?php endif; ?>
        <?php if ($cfg['email']): ?><li><?= icon('mail') ?><a href="mailto:<?= e($cfg['email']) ?>"><?= e($cfg['email']) ?></a></li><?php endif; ?>
      </ul>
    </div>

    <div>
      <h3 class="footer-title">Horários</h3>
      <ul class="footer-hours">
        <?php foreach ($horarios as $h): ?>
          <li><span><?= e($h['dias']) ?></span><strong class="<?= $h['horario'] === 'Fechado' ? 'is-closed' : '' ?>"><?= e($h['horario']) ?></strong></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= e($cfg['nome_barbearia']) ?>. Todos os direitos reservados.</p>
    <p><a href="<?= e(url('admin/login.php')) ?>">Área restrita</a></p>
  </div>
</footer>

<?php if (!empty($cfg['whatsapp'])): ?>
<a class="whatsapp-float" href="<?= e(whatsapp_link('Olá! Gostaria de agendar um horário.')) ?>" target="_blank" rel="noopener" aria-label="Fale conosco pelo WhatsApp">
  <?= icon('whatsapp') ?><span>Fale conosco</span>
</a>
<?php endif; ?>

<script src="<?= e(asset('js/script.js')) ?>" defer></script>
<?php foreach (($pageScripts ?? []) as $s): ?>
<script src="<?= e(asset($s)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
