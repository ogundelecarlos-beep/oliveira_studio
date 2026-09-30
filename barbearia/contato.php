<?php
require __DIR__ . '/includes/bootstrap.php';

$cfg = settings();
$erros = [];
$old = ['nome' => '', 'email' => '', 'telefone' => '', 'assunto' => '', 'mensagem' => ''];

if (is_post()) {
    foreach ($old as $k => $_) {
        $old[$k] = input($k);
    }
    // Honeypot anti-spam: campo invisível que humanos não preenchem
    if (input('website') !== '') {
        redirect('contato.php');
    }
    if (!csrf_valid()) {
        $erros['geral'] = 'Sua sessão expirou. Atualize a página e envie novamente.';
    }
    if (str_len($old['nome']) < 3 || str_len($old['nome']) > 120) {
        $erros['nome'] = 'Informe seu nome completo.';
    }
    if (!valid_email($old['email'])) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    $tel = normalize_phone($old['telefone']);
    if ($old['telefone'] !== '' && !valid_phone($tel)) {
        $erros['telefone'] = 'Telefone inválido. Use DDD + número.';
    }
    if (str_len($old['assunto']) > 150) {
        $erros['assunto'] = 'Assunto muito longo.';
    }
    if (str_len($old['mensagem']) < 10 || str_len($old['mensagem']) > 2000) {
        $erros['mensagem'] = 'A mensagem deve ter entre 10 e 2000 caracteres.';
    }
    if (!$erros) {
        db()->prepare('INSERT INTO mensagens (nome, email, telefone, assunto, mensagem) VALUES (?, ?, ?, ?, ?)')
            ->execute([$old['nome'], $old['email'], $tel ?: null, $old['assunto'] ?: null, $old['mensagem']]);
        flash('success', 'Mensagem enviada com sucesso! Responderemos em até 1 dia útil.');
        redirect('contato.php');
    }
}

$horarios = horario_resumo();
$pageTitle = 'Contato';
$pageDescription = 'Endereço, telefone, WhatsApp, horário de funcionamento e formulário de contato.';
$active = 'contato';
$heroEyebrow = 'Contato';
$heroTitle = 'Fale com a <em>gente</em>';
$heroText = 'Dúvidas, eventos, parcerias ou só um bom papo sobre estilo. Estamos por aqui.';
$heroImage = 'https://images.unsplash.com/photo-1512690459411-b9245aed614b?auto=format&fit=crop&w=2000&q=80';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/page-hero.php';
?>
<section class="section">
  <div class="container contact-grid">
    <div class="contact-cards">
      <a class="contact-card reveal" href="<?= e($cfg['mapa_link'] ?: '#localizacao') ?>" target="_blank" rel="noopener"><?= icon('map-pin') ?><small>Endereço</small><span><?= e($cfg['endereco']) ?></span></a>
      <a class="contact-card reveal" href="tel:<?= e(only_digits($cfg['telefone'])) ?>"><?= icon('phone') ?><small>Telefone</small><span><?= e($cfg['telefone']) ?></span></a>
      <a class="contact-card reveal" href="<?= e(whatsapp_link('Olá! Vim pelo site.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><small>WhatsApp</small><span><?= e(format_phone($cfg['whatsapp'])) ?></span></a>
      <a class="contact-card reveal" href="<?= e(instagram_url()) ?>" target="_blank" rel="noopener"><?= icon('instagram') ?><small>Instagram</small><span>@<?= e(ltrim($cfg['instagram'], '@')) ?></span></a>
      <div class="contact-card contact-card-wide reveal"><?= icon('clock') ?><small>Horário de funcionamento</small>
        <ul class="hours-table">
          <?php foreach ($horarios as $h): ?><li><span><?= e($h['dias']) ?></span><b class="<?= $h['horario'] === 'Fechado' ? 'is-closed' : '' ?>"><?= e($h['horario']) ?></b></li><?php endforeach; ?>
        </ul>
        <?php if ($cfg['horario_funcionamento']): ?><p class="muted small"><?= e($cfg['horario_funcionamento']) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="contact-form-wrap reveal">
      <h2 class="display">Envie uma <em>mensagem</em></h2>
      <p class="muted">Preencha o formulário e retornaremos o quanto antes. Para agendamentos, use o <a href="<?= e(url('agendamento.php')) ?>" class="link-gold">agendamento online</a>.</p>
      <?php if (!empty($erros['geral'])): ?><div class="alert alert-error inline"><?= e($erros['geral']) ?></div><?php endif; ?>
      <form method="post" class="form" data-validate novalidate>
        <?= csrf_field() ?>
        <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-row">
          <div class="field <?= isset($erros['nome']) ? 'has-error' : '' ?>">
            <label for="c-nome">Nome *</label>
            <input id="c-nome" name="nome" value="<?= e($old['nome']) ?>" required minlength="3" maxlength="120" autocomplete="name">
            <?php if (isset($erros['nome'])): ?><span class="field-error"><?= e($erros['nome']) ?></span><?php endif; ?>
          </div>
          <div class="field <?= isset($erros['email']) ? 'has-error' : '' ?>">
            <label for="c-email">E-mail *</label>
            <input id="c-email" type="email" name="email" value="<?= e($old['email']) ?>" required maxlength="160" autocomplete="email">
            <?php if (isset($erros['email'])): ?><span class="field-error"><?= e($erros['email']) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="field <?= isset($erros['telefone']) ? 'has-error' : '' ?>">
            <label for="c-tel">Telefone</label>
            <input id="c-tel" name="telefone" value="<?= e($old['telefone']) ?>" data-mask="phone" inputmode="tel" autocomplete="tel" placeholder="(11) 99999-9999">
            <?php if (isset($erros['telefone'])): ?><span class="field-error"><?= e($erros['telefone']) ?></span><?php endif; ?>
          </div>
          <div class="field <?= isset($erros['assunto']) ? 'has-error' : '' ?>">
            <label for="c-assunto">Assunto</label>
            <input id="c-assunto" name="assunto" value="<?= e($old['assunto']) ?>" maxlength="150">
          </div>
        </div>
        <div class="field <?= isset($erros['mensagem']) ? 'has-error' : '' ?>">
          <label for="c-msg">Mensagem *</label>
          <textarea id="c-msg" name="mensagem" rows="6" required minlength="10" maxlength="2000"><?= e($old['mensagem']) ?></textarea>
          <?php if (isset($erros['mensagem'])): ?><span class="field-error"><?= e($erros['mensagem']) ?></span><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-gold btn-lg">Enviar mensagem <?= icon('arrow-right') ?></button>
      </form>
    </div>
  </div>

  <div class="container">
    <div class="map-wrap map-wide reveal" id="localizacao">
      <?php if ($cfg['mapa']): ?><iframe src="<?= e($cfg['mapa']) ?>" title="Mapa de localização" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe><?php endif; ?>
      <a href="<?= e($cfg['mapa_link'] ?: 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($cfg['endereco'])) ?>" target="_blank" rel="noopener" class="btn btn-gold map-btn"><?= icon('navigation') ?> Como chegar</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
