<?php
/**
 * Cancelamento pelo cliente através do link com token único.
 */
require __DIR__ . '/includes/bootstrap.php';

$token = input('t');
$ag = null;
if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $st = db()->prepare('SELECT id FROM agendamentos WHERE token = ?');
    $st->execute([$token]);
    $id = $st->fetchColumn();
    $ag = $id ? agendamento_completo((int) $id) : null;
}

$podeCancelar = $ag
    && in_array($ag['status'], ['pendente', 'confirmado'], true)
    && strtotime($ag['data'] . ' ' . $ag['hora']) > time();

if (is_post() && $ag) {
    if (!csrf_valid()) {
        flash('error', 'Sua sessão expirou. Tente novamente.');
    } elseif (!$podeCancelar) {
        flash('error', 'Este agendamento não pode mais ser cancelado online. Fale conosco pelo WhatsApp.');
    } else {
        db()->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = ? AND status IN ('pendente','confirmado')")->execute([$ag['id']]);
        flash('success', 'Agendamento cancelado. O horário foi liberado na agenda.');
    }
    redirect('cancelar.php?t=' . $token);
}

$pageTitle = 'Cancelar agendamento';
$bodyClass = 'page-booking';
require __DIR__ . '/includes/header.php';
?>
<section class="booking">
  <div class="container narrow">
    <div class="booking-success">
      <?php if (!$ag): ?>
        <div class="success-icon is-error"><?= icon('x') ?></div>
        <h1 class="display">Link inválido</h1>
        <p class="muted">Não encontramos este agendamento. Verifique o link ou fale conosco.</p>
      <?php else: ?>
        <div class="success-icon <?= $ag['status'] === 'cancelado' ? 'is-error' : '' ?>"><?= icon($ag['status'] === 'cancelado' ? 'ban' : 'calendar') ?></div>
        <h1 class="display"><?= $ag['status'] === 'cancelado' ? 'Agendamento cancelado' : 'Seu agendamento' ?></h1>
        <p class="muted">Protocolo #<?= e(str_pad((string) $ag['id'], 5, '0', STR_PAD_LEFT)) ?> · <?= status_badge($ag['status']) ?></p>
        <dl class="summary-list">
          <div><dt>Serviço</dt><dd><?= e($ag['servico_nome']) ?></dd></div>
          <div><dt>Barbeiro</dt><dd><?= e($ag['barbeiro_nome']) ?></dd></div>
          <div><dt>Data</dt><dd><?= e(data_extenso($ag['data'])) ?></dd></div>
          <div><dt>Horário</dt><dd><?= e(hm($ag['hora'])) ?></dd></div>
          <div class="total"><dt>Valor</dt><dd><?= e(money($ag['valor'])) ?></dd></div>
        </dl>
        <?php if ($podeCancelar): ?>
          <form method="post" data-confirm="Tem certeza que deseja cancelar este agendamento?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-lg">Cancelar agendamento</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <div class="btn-col">
        <a href="<?= e(url('agendamento.php')) ?>" class="btn btn-gold">Fazer novo agendamento</a>
        <a href="<?= e(whatsapp_link('Olá! Preciso de ajuda com meu agendamento.')) ?>" class="btn btn-ghost" target="_blank" rel="noopener"><?= icon('whatsapp') ?> Falar no WhatsApp</a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
