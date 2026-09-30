<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

if (is_post()) {
    admin_post_guard('admin/mensagens.php');
    $id = input_int('id');
    $acao = input('acao');
    if ($acao === 'lida') {
        $pdo->prepare('UPDATE mensagens SET lida = 1 - lida WHERE id = ?')->execute([$id]);
    } elseif ($acao === 'excluir') {
        $pdo->prepare('DELETE FROM mensagens WHERE id = ?')->execute([$id]);
        flash('success', 'Mensagem excluída.');
    }
    redirect('admin/mensagens.php');
}

$mensagens = $pdo->query('SELECT * FROM mensagens ORDER BY lida ASC, criado_em DESC LIMIT 200')->fetchAll();
admin_header('Mensagens', 'mensagens', ['subtitle' => 'Recebidas pelo formulário de contato do site']);
?>
<div class="message-list">
  <?php foreach ($mensagens as $m): ?>
    <article class="card message <?= $m['lida'] ? '' : 'is-unread' ?>">
      <header>
        <span class="avatar"><?= e(initials($m['nome'])) ?></span>
        <div>
          <strong><?= e($m['nome']) ?></strong>
          <small class="muted d-block"><?= e($m['email']) ?><?= $m['telefone'] ? ' · ' . e(format_phone($m['telefone'])) : '' ?> · <?= e(date('d/m/Y H:i', strtotime($m['criado_em']))) ?></small>
        </div>
        <?php if (!$m['lida']): ?><span class="badge badge-pendente">Nova</span><?php endif; ?>
      </header>
      <?php if ($m['assunto']): ?><h3><?= e($m['assunto']) ?></h3><?php endif; ?>
      <p><?= nl2br(e($m['mensagem'])) ?></p>
      <footer class="review-actions">
        <a class="btn btn-sm btn-ghost" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . ($m['assunto'] ?: 'Contato'))) ?>"><?= icon('mail') ?> Responder</a>
        <?php if ($m['telefone']): ?><a class="btn btn-sm btn-ghost" href="<?= e(whatsapp_link('Olá, ' . explode(' ', $m['nome'])[0] . '! Recebemos sua mensagem pelo site.', $m['telefone'])) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="lida"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-ghost"><?= icon('check') ?> <?= $m['lida'] ? 'Marcar como não lida' : 'Marcar como lida' ?></button></form>
        <form method="post" data-confirm="Excluir esta mensagem?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
      </footer>
    </article>
  <?php endforeach; ?>
  <?php if (!$mensagens): ?><p class="empty">Nenhuma mensagem recebida.</p><?php endif; ?>
</div>
<?php admin_footer();
