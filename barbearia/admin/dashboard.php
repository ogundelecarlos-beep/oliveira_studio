<?php
require __DIR__ . '/includes/auth.php';
$u = require_login();

$pdo = db();
$hoje = date('Y-m-d');
$iniMes = date('Y-m-01');
$fimMes = date('Y-m-t');

$q = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $st = $pdo->prepare($sql); $st->execute($p); return $st; })();

$agHoje = $q("SELECT status, COUNT(*) c FROM agendamentos WHERE data = ? GROUP BY status", [$hoje])->fetchAll(PDO::FETCH_KEY_PAIR);
$totalHoje = array_sum(array_diff_key($agHoje, ['cancelado' => 0]));
$clientes = (int) $q('SELECT COUNT(*) FROM clientes')->fetchColumn();
$clientesNovos = (int) $q('SELECT COUNT(*) FROM clientes WHERE data_cadastro >= ?', [$iniMes])->fetchColumn();
$fatMes = (float) $q("SELECT COALESCE(SUM(valor),0) FROM agendamentos WHERE status = 'concluido' AND data BETWEEN ? AND ?", [$iniMes, $fimMes])->fetchColumn();
$fatHoje = (float) $q("SELECT COALESCE(SUM(valor),0) FROM agendamentos WHERE status = 'concluido' AND data = ?", [$hoje])->fetchColumn();
$previsto = (float) $q("SELECT COALESCE(SUM(valor),0) FROM agendamentos WHERE status IN ('pendente','confirmado') AND data BETWEEN ? AND ?", [$hoje, $fimMes])->fetchColumn();
$realizados = (int) $q("SELECT COUNT(*) FROM agendamentos WHERE status = 'concluido' AND data BETWEEN ? AND ?", [$iniMes, $fimMes])->fetchColumn();
$ticket = $realizados ? $fatMes / $realizados : 0;
$proximosCount = (int) $q("SELECT COUNT(*) FROM agendamentos WHERE status IN ('pendente','confirmado') AND (data > ? OR (data = ? AND hora >= ?))", [$hoje, $hoje, date('H:i')])->fetchColumn();

$proximos = $q("SELECT a.id, a.data, a.hora, a.status, a.valor, c.nome cliente, c.telefone, s.nome servico, b.nome barbeiro
                FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN servicos s ON s.id = a.servico_id JOIN barbeiros b ON b.id = a.barbeiro_id
                WHERE a.status IN ('pendente','confirmado') AND (a.data > ? OR (a.data = ? AND a.hora_fim >= ?))
                ORDER BY a.data, a.hora LIMIT 10", [$hoje, $hoje, date('H:i')])->fetchAll();

$topServicos = $q("SELECT s.nome, COUNT(*) qtd, SUM(a.valor) total FROM agendamentos a JOIN servicos s ON s.id = a.servico_id
                   WHERE a.status = 'concluido' AND a.data BETWEEN ? AND ? GROUP BY s.id, s.nome ORDER BY qtd DESC LIMIT 5", [$iniMes, $fimMes])->fetchAll();
$porBarbeiro = $q("SELECT b.nome, COUNT(a.id) qtd, COALESCE(SUM(a.valor),0) total FROM barbeiros b
                   LEFT JOIN agendamentos a ON a.barbeiro_id = b.id AND a.status = 'concluido' AND a.data BETWEEN ? AND ?
                   WHERE b.ativo = 1 GROUP BY b.id, b.nome ORDER BY total DESC", [$iniMes, $fimMes])->fetchAll();
$maxServ = max(array_column($topServicos, 'qtd') ?: [1]);
$maxBarb = max(array_column($porBarbeiro, 'total') ?: [1]) ?: 1;
$pendAval = (int) $q('SELECT COUNT(*) FROM avaliacoes WHERE aprovado = 0')->fetchColumn();
$pendMsg = (int) $q('SELECT COUNT(*) FROM mensagens WHERE lida = 0')->fetchColumn();

$saudacao = (int) date('G') < 12 ? 'Bom dia' : ((int) date('G') < 18 ? 'Boa tarde' : 'Boa noite');
admin_header('Dashboard', 'dashboard', [
    'subtitle' => $saudacao . ', ' . explode(' ', $u['nome'])[0] . ' · ' . data_extenso($hoje),
    'actions'  => '<a href="' . e(url('admin/agenda.php?novo=1')) . '" class="btn btn-gold btn-sm">' . icon('plus') . '<span class="hide-sm">Novo agendamento</span></a>',
]);
?>

<?php if ($pendAval || $pendMsg): ?>
<div class="notice-row">
  <?php if ($pendAval): ?><a class="notice" href="<?= e(url('admin/avaliacoes.php?filtro=pendentes')) ?>"><?= icon('star') ?> <?= $pendAval ?> avaliação(ões) aguardando moderação <?= icon('arrow-right') ?></a><?php endif; ?>
  <?php if ($pendMsg): ?><a class="notice" href="<?= e(url('admin/mensagens.php')) ?>"><?= icon('message') ?> <?= $pendMsg ?> mensagem(ns) não lida(s) <?= icon('arrow-right') ?></a><?php endif; ?>
</div>
<?php endif; ?>

<section class="kpi-grid">
  <article class="kpi">
    <div class="kpi-icon"><?= icon('calendar') ?></div>
    <span class="kpi-label">Agendamentos hoje</span>
    <strong class="kpi-value"><?= $totalHoje ?></strong>
    <span class="kpi-foot"><?= (int) ($agHoje['confirmado'] ?? 0) ?> confirmados · <?= (int) ($agHoje['pendente'] ?? 0) ?> pendentes</span>
  </article>
  <article class="kpi">
    <div class="kpi-icon"><?= icon('users') ?></div>
    <span class="kpi-label">Clientes cadastrados</span>
    <strong class="kpi-value"><?= $clientes ?></strong>
    <span class="kpi-foot"><b class="up">+<?= $clientesNovos ?></b> neste mês</span>
  </article>
  <article class="kpi kpi-gold">
    <div class="kpi-icon"><?= icon('money') ?></div>
    <span class="kpi-label">Faturamento do mês</span>
    <strong class="kpi-value"><?= e(money($fatMes)) ?></strong>
    <span class="kpi-foot">Hoje: <?= e(money($fatHoje)) ?> · Previsto: <?= e(money($previsto)) ?></span>
  </article>
  <article class="kpi">
    <div class="kpi-icon"><?= icon('scissors') ?></div>
    <span class="kpi-label">Serviços realizados</span>
    <strong class="kpi-value"><?= $realizados ?></strong>
    <span class="kpi-foot">Ticket médio: <?= e(money($ticket)) ?></span>
  </article>
  <article class="kpi">
    <div class="kpi-icon"><?= icon('clock') ?></div>
    <span class="kpi-label">Próximos agendamentos</span>
    <strong class="kpi-value"><?= $proximosCount ?></strong>
    <span class="kpi-foot">A partir de agora</span>
  </article>
</section>

<div class="dash-grid">
  <section class="card">
    <header class="card-head">
      <h2>Próximos agendamentos</h2>
      <a href="<?= e(url('admin/agenda.php')) ?>" class="link-gold small">Ver agenda completa</a>
    </header>
    <div class="table-wrap">
      <table class="table table-responsive">
        <thead><tr><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Barbeiro</th><th>Status</th><th class="t-right">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($proximos as $a): ?>
          <tr>
            <td data-label="Horário"><strong><?= e(hm($a['hora'])) ?></strong><small class="muted d-block"><?= $a['data'] === $hoje ? 'Hoje' : e(date_br($a['data'])) ?></small></td>
            <td data-label="Cliente"><?= e($a['cliente']) ?><small class="muted d-block"><?= e(format_phone($a['telefone'])) ?></small></td>
            <td data-label="Serviço"><?= e($a['servico']) ?></td>
            <td data-label="Barbeiro"><?= e($a['barbeiro']) ?></td>
            <td data-label="Status"><?= status_badge($a['status']) ?></td>
            <td class="t-right actions-cell">
              <?php if ($a['status'] === 'pendente'): ?>
                <form method="post" action="<?= e(url('admin/agenda.php')) ?>"><?= csrf_field() ?><input type="hidden" name="acao" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="status" value="confirmado"><input type="hidden" name="voltar" value="dashboard"><button class="icon-btn success" title="Confirmar" aria-label="Confirmar"><?= icon('check') ?></button></form>
              <?php else: ?>
                <form method="post" action="<?= e(url('admin/agenda.php')) ?>"><?= csrf_field() ?><input type="hidden" name="acao" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="status" value="concluido"><input type="hidden" name="voltar" value="dashboard"><button class="icon-btn success" title="Marcar como concluído" aria-label="Concluir"><?= icon('scissors') ?></button></form>
              <?php endif; ?>
              <a class="icon-btn" href="<?= e(whatsapp_link('Olá, ' . explode(' ', $a['cliente'])[0] . '! Passando para lembrar do seu horário na ' . settings()['nome_barbearia'] . ': ' . $a['servico'] . ' em ' . date_br($a['data']) . ' às ' . hm($a['hora']) . '. Até lá!', $a['telefone'])) ?>" target="_blank" rel="noopener" title="Lembrete por WhatsApp" aria-label="WhatsApp"><?= icon('whatsapp') ?></a>
              <a class="icon-btn" href="<?= e(url('admin/agenda.php?view=day&date=' . $a['data'] . '&abrir=' . (int) $a['id'])) ?>" title="Abrir na agenda" aria-label="Abrir"><?= icon('edit') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$proximos): ?><tr><td colspan="6" class="empty">Nenhum agendamento futuro.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="dash-side">
    <section class="card">
      <header class="card-head"><h2>Serviços do mês</h2></header>
      <ul class="bar-list">
        <?php foreach ($topServicos as $s): ?>
          <li><div class="bar-top"><span><?= e($s['nome']) ?></span><b><?= (int) $s['qtd'] ?></b></div><div class="bar"><span style="width: <?= round($s['qtd'] / $maxServ * 100) ?>%"></span></div></li>
        <?php endforeach; ?>
        <?php if (!$topServicos): ?><li class="muted small">Nenhum serviço concluído neste mês.</li><?php endif; ?>
      </ul>
    </section>
    <section class="card">
      <header class="card-head"><h2>Faturamento por barbeiro</h2></header>
      <ul class="bar-list">
        <?php foreach ($porBarbeiro as $b): ?>
          <li><div class="bar-top"><span><?= e($b['nome']) ?> <small class="muted">· <?= (int) $b['qtd'] ?> atend.</small></span><b><?= e(money($b['total'])) ?></b></div><div class="bar"><span style="width: <?= round($b['total'] / $maxBarb * 100) ?>%"></span></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
<?php admin_footer();
