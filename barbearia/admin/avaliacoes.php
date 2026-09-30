<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

$filtro = in_array(input('filtro'), ['todas', 'pendentes', 'aprovadas', 'ocultas'], true) ? input('filtro') : 'todas';

if (is_post()) {
    $back = 'admin/avaliacoes.php?filtro=' . $filtro;
    admin_post_guard($back);
    $id = input_int('id');
    $acoes = ['aprovar' => [1, 'Avaliação aprovada: já aparece no site.'], 'ocultar' => [2, 'Avaliação ocultada do site.'], 'pendente' => [0, 'Avaliação voltou para moderação.']];
    $acao = input('acao');
    if (isset($acoes[$acao])) {
        $pdo->prepare('UPDATE avaliacoes SET aprovado = ? WHERE id = ?')->execute([$acoes[$acao][0], $id]);
        flash('success', $acoes[$acao][1]);
    } elseif ($acao === 'excluir') {
        $pdo->prepare('DELETE FROM avaliacoes WHERE id = ?')->execute([$id]);
        flash('success', 'Avaliação excluída.');
    }
    redirect($back);
}

$where = ['pendentes' => 'WHERE av.aprovado = 0', 'aprovadas' => 'WHERE av.aprovado = 1', 'ocultas' => 'WHERE av.aprovado = 2', 'todas' => ''][$filtro];
$avaliacoes = $pdo->query("SELECT av.*, c.nome cliente_nome, c.telefone, s.nome servico, b.nome barbeiro, a.data atendimento
                           FROM avaliacoes av JOIN clientes c ON c.id = av.cliente_id
                           LEFT JOIN agendamentos a ON a.id = av.agendamento_id
                           LEFT JOIN servicos s ON s.id = a.servico_id
                           LEFT JOIN barbeiros b ON b.id = a.barbeiro_id
                           $where ORDER BY av.aprovado = 0 DESC, av.criado_em DESC")->fetchAll();
$contagem = $pdo->query('SELECT aprovado, COUNT(*) FROM avaliacoes GROUP BY aprovado')->fetchAll(PDO::FETCH_KEY_PAIR);
$media = (float) $pdo->query('SELECT AVG(nota) FROM avaliacoes WHERE aprovado = 1')->fetchColumn();

admin_header('Avaliações', 'avaliacoes', ['subtitle' => 'Somente avaliações aprovadas aparecem no site · Média publicada: ' . number_format($media, 1, ',', '')]);
?>
<div class="tabs-bar">
  <?php foreach (['todas' => ['Todas', array_sum($contagem)], 'pendentes' => ['Pendentes', $contagem[0] ?? 0], 'aprovadas' => ['Aprovadas', $contagem[1] ?? 0], 'ocultas' => ['Ocultas', $contagem[2] ?? 0]] as $k => [$l, $n]): ?>
    <a href="?filtro=<?= $k ?>" class="<?= $filtro === $k ? 'is-active' : '' ?>"><?= $l ?> <em class="count"><?= (int) $n ?></em></a>
  <?php endforeach; ?>
</div>

<div class="review-admin-grid">
  <?php foreach ($avaliacoes as $a):
      $st = [0 => ['Pendente', 'badge-pendente'], 1 => ['Aprovada', 'badge-concluido'], 2 => ['Oculta', 'badge-cancelado']][(int) $a['aprovado']]; ?>
    <article class="card review-admin <?= (int) $a['aprovado'] === 0 ? 'is-pending' : '' ?>">
      <header>
        <span class="avatar"><?= e(initials($a['cliente_nome'])) ?></span>
        <div><strong><?= e($a['cliente_nome']) ?></strong><small class="muted d-block"><?= e(date_br($a['criado_em'])) ?><?= $a['servico'] ? ' · ' . e($a['servico']) . ' com ' . e($a['barbeiro']) : '' ?></small></div>
        <span class="badge <?= $st[1] ?>"><?= $st[0] ?></span>
      </header>
      <?= stars((float) $a['nota']) ?>
      <p><?= nl2br(e($a['comentario'])) ?></p>
      <footer class="review-actions">
        <?php if ((int) $a['aprovado'] !== 1): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-success"><?= icon('check') ?> Aprovar</button></form>
        <?php endif; ?>
        <?php if ((int) $a['aprovado'] !== 2): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="ocultar"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-ghost"><?= icon('eye-off') ?> Ocultar</button></form>
        <?php endif; ?>
        <form method="post" data-confirm="Excluir esta avaliação definitivamente?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
      </footer>
    </article>
  <?php endforeach; ?>
  <?php if (!$avaliacoes): ?><p class="empty">Nenhuma avaliação neste filtro.</p><?php endif; ?>
</div>
<?php admin_footer();
