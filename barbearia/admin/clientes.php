<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

/* ---------- Ações ---------- */
if (is_post()) {
    $acao = input('acao');
    $id = input_int('id');
    $back = $id ? 'admin/clientes.php?id=' . $id : 'admin/clientes.php';
    admin_post_guard($back);

    if ($acao === 'salvar') {
        $nome = input('nome');
        $tel = normalize_phone(input('telefone'));
        $email = strtolower(input('email'));
        $erros = [];
        if (str_len($nome) < 3 || str_len($nome) > 120) $erros[] = 'Informe o nome completo.';
        if (!valid_phone($tel)) $erros[] = 'Telefone inválido (use DDD + número).';
        if ($email !== '' && !valid_email($email)) $erros[] = 'E-mail inválido.';
        $dup = $pdo->prepare('SELECT id FROM clientes WHERE telefone = ? AND id <> ?');
        $dup->execute([$tel, $id]);
        if ($dup->fetch()) $erros[] = 'Já existe um cliente com este telefone.';
        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect($back);
        }
        if ($id) {
            $pdo->prepare('UPDATE clientes SET nome = ?, telefone = ?, email = ? WHERE id = ?')->execute([$nome, $tel, $email ?: null, $id]);
            flash('success', 'Dados do cliente atualizados.');
        } else {
            $pdo->prepare('INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)')->execute([$nome, $tel, $email ?: null]);
            $id = (int) $pdo->lastInsertId();
            flash('success', 'Cliente cadastrado com sucesso.');
        }
        redirect('admin/clientes.php?id=' . $id);
    }

    if ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM agendamentos WHERE cliente_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            flash('error', 'Não é possível excluir: o cliente possui histórico de agendamentos.');
            redirect($back);
        }
        $pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
        flash('success', 'Cliente excluído.');
        redirect('admin/clientes.php');
    }
    redirect($back);
}

/* ---------- Detalhe do cliente ---------- */
$id = input_int('id');
if ($id) {
    $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) {
        flash('error', 'Cliente não encontrado.');
        redirect('admin/clientes.php');
    }
    $st = $pdo->prepare('SELECT a.*, s.nome servico, b.nome barbeiro FROM agendamentos a JOIN servicos s ON s.id = a.servico_id JOIN barbeiros b ON b.id = a.barbeiro_id
                         WHERE a.cliente_id = ? ORDER BY a.data DESC, a.hora DESC');
    $st->execute([$id]);
    $historico = $st->fetchAll();
    $concluidos = array_filter($historico, fn($a) => $a['status'] === 'concluido');
    $gasto = array_sum(array_column($concluidos, 'valor'));
    $servicosFeitos = [];
    foreach ($concluidos as $a) {
        $servicosFeitos[$a['servico']] = ($servicosFeitos[$a['servico']] ?? 0) + 1;
    }
    arsort($servicosFeitos);
    $ultimo = $concluidos ? reset($concluidos)['data'] : null;
    $proximo = null;
    foreach (array_reverse($historico) as $a) {
        if (in_array($a['status'], ['pendente', 'confirmado'], true) && $a['data'] >= date('Y-m-d')) { $proximo = $a; break; }
    }
    $favorito = null;
    $cont = [];
    foreach ($concluidos as $a) { $cont[$a['barbeiro']] = ($cont[$a['barbeiro']] ?? 0) + 1; }
    if ($cont) { arsort($cont); $favorito = array_key_first($cont); }

    admin_header($c['nome'], 'clientes', [
        'subtitle' => 'Cliente desde ' . date_br($c['data_cadastro']),
        'actions' => '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/clientes.php')) . '">' . icon('arrow-left') . '<span class="hide-sm">Voltar</span></a>'
            . '<a class="btn btn-gold btn-sm" href="' . e(whatsapp_link('Olá, ' . explode(' ', $c['nome'])[0] . '!', $c['telefone'])) . '" target="_blank" rel="noopener">' . icon('whatsapp') . '<span class="hide-sm">WhatsApp</span></a>',
    ]);
    ?>
    <section class="kpi-grid kpi-4">
      <article class="kpi"><span class="kpi-label">Visitas</span><strong class="kpi-value"><?= count($concluidos) ?></strong><span class="kpi-foot"><?= count($historico) ?> agendamentos no total</span></article>
      <article class="kpi kpi-gold"><span class="kpi-label">Valor gasto</span><strong class="kpi-value"><?= e(money($gasto)) ?></strong><span class="kpi-foot">Ticket médio <?= e(money($concluidos ? $gasto / count($concluidos) : 0)) ?></span></article>
      <article class="kpi"><span class="kpi-label">Último atendimento</span><strong class="kpi-value sm"><?= e(date_br($ultimo)) ?></strong><span class="kpi-foot"><?= $favorito ? 'Barbeiro favorito: ' . e($favorito) : '—' ?></span></article>
      <article class="kpi"><span class="kpi-label">Próximo horário</span><strong class="kpi-value sm"><?= $proximo ? e(date_br($proximo['data']) . ' ' . hm($proximo['hora'])) : '—' ?></strong><span class="kpi-foot"><?= $proximo ? e($proximo['servico']) : 'Nenhum agendado' ?></span></article>
    </section>

    <div class="dash-grid">
      <section class="card">
        <header class="card-head"><h2>Histórico de agendamentos</h2></header>
        <div class="table-wrap">
          <table class="table table-responsive">
            <thead><tr><th>Data</th><th>Serviço</th><th>Barbeiro</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($historico as $a): ?>
              <tr data-href="<?= e(url('admin/agenda.php?view=day&date=' . $a['data'] . '&abrir=' . (int) $a['id'])) ?>" class="is-clickable">
                <td data-label="Data"><strong><?= e(date_br($a['data'])) ?></strong> <span class="muted"><?= e(hm($a['hora'])) ?></span></td>
                <td data-label="Serviço"><?= e($a['servico']) ?><?php if ($a['observacao']): ?><small class="muted d-block"><?= e($a['observacao']) ?></small><?php endif; ?></td>
                <td data-label="Barbeiro"><?= e($a['barbeiro']) ?></td>
                <td data-label="Valor"><?= e(money($a['valor'])) ?></td>
                <td data-label="Status"><?= status_badge($a['status']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$historico): ?><tr><td colspan="5" class="empty">Nenhum agendamento ainda.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <div class="dash-side">
        <section class="card">
          <header class="card-head"><h2>Dados pessoais</h2></header>
          <form method="post" class="form" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <div class="field"><label for="nome">Nome</label><input id="nome" name="nome" value="<?= e($c['nome']) ?>" required minlength="3" maxlength="120"></div>
            <div class="field"><label for="tel">Telefone</label><input id="tel" name="telefone" value="<?= e(format_phone($c['telefone'])) ?>" required data-mask="phone"></div>
            <div class="field"><label for="email">E-mail</label><input id="email" type="email" name="email" value="<?= e($c['email']) ?>" maxlength="160"></div>
            <button class="btn btn-gold btn-block">Salvar</button>
          </form>
          <?php if (!$historico): ?>
            <form method="post" data-confirm="Excluir este cliente definitivamente?" class="mt"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-danger-ghost btn-block btn-sm"><?= icon('trash') ?> Excluir cliente</button></form>
          <?php endif; ?>
        </section>
        <section class="card">
          <header class="card-head"><h2>Serviços realizados</h2></header>
          <ul class="bar-list">
            <?php $mx = max($servicosFeitos ?: [1]); foreach ($servicosFeitos as $nome => $qtd): ?>
              <li><div class="bar-top"><span><?= e($nome) ?></span><b><?= $qtd ?>×</b></div><div class="bar"><span style="width: <?= round($qtd / $mx * 100) ?>%"></span></div></li>
            <?php endforeach; ?>
            <?php if (!$servicosFeitos): ?><li class="muted small">Nenhum serviço concluído.</li><?php endif; ?>
          </ul>
        </section>
      </div>
    </div>
    <?php
    admin_footer();
    exit;
}

/* ---------- Lista ---------- */
$busca = input('q');
$ordem = in_array(input('ordem'), ['nome', 'visitas', 'ultimo', 'gasto', 'recentes'], true) ? input('ordem') : 'nome';
$where = '';
$params = [];
if ($busca !== '') {
    $where = 'WHERE c.nome LIKE ? OR c.email LIKE ? OR c.telefone LIKE ?';
    $like = '%' . $busca . '%';
    $digits = only_digits($busca);
    $params = [$like, $like, '%' . ($digits !== '' ? $digits : $busca) . '%'];
}
$st = $pdo->prepare("SELECT COUNT(*) FROM clientes c $where");
$st->execute($params);
$total = (int) $st->fetchColumn();
[$offset, $pager] = paginate($total, 20, input_int('p', 1), array_filter(['q' => $busca, 'ordem' => $ordem]));

$orderSql = [
    'nome' => 'c.nome ASC', 'visitas' => 'visitas DESC, c.nome', 'ultimo' => 'ultimo DESC, c.nome',
    'gasto' => 'gasto DESC, c.nome', 'recentes' => 'c.data_cadastro DESC',
][$ordem];
$st = $pdo->prepare("SELECT c.*,
        (SELECT COUNT(*) FROM agendamentos a WHERE a.cliente_id = c.id AND a.status = 'concluido') visitas,
        (SELECT MAX(a.data) FROM agendamentos a WHERE a.cliente_id = c.id AND a.status = 'concluido') ultimo,
        (SELECT COALESCE(SUM(a.valor),0) FROM agendamentos a WHERE a.cliente_id = c.id AND a.status = 'concluido') gasto
        FROM clientes c $where ORDER BY $orderSql LIMIT 20 OFFSET $offset");
$st->execute($params);
$clientes = $st->fetchAll();

admin_header('Clientes', 'clientes', [
    'subtitle' => $total . ' cliente(s)' . ($busca !== '' ? ' encontrados para "' . $busca . '"' : ' cadastrados'),
    'actions' => '<button type="button" class="btn btn-gold btn-sm" data-crud-new="modal-cliente">' . icon('plus') . '<span class="hide-sm">Novo cliente</span></button>',
]);
?>
<section class="card">
  <form class="toolbar" method="get">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por nome, telefone ou e-mail" aria-label="Buscar clientes"></div>
    <select name="ordem" onchange="this.form.submit()" aria-label="Ordenar">
      <?php foreach (['nome' => 'Nome (A–Z)', 'visitas' => 'Mais visitas', 'ultimo' => 'Último atendimento', 'gasto' => 'Maior gasto', 'recentes' => 'Cadastro recente'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= $ordem === $k ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost btn-sm">Buscar</button>
  </form>
  <div class="table-wrap">
    <table class="table table-responsive">
      <thead><tr><th>Nome</th><th>Telefone</th><th>E-mail</th><th>Último atendimento</th><th class="t-center">Visitas</th><th class="t-right">Gasto</th></tr></thead>
      <tbody>
      <?php foreach ($clientes as $c): ?>
        <tr data-href="<?= e(url('admin/clientes.php?id=' . (int) $c['id'])) ?>" class="is-clickable">
          <td data-label="Nome"><div class="cell-user"><span class="avatar sm"><?= e(initials($c['nome'])) ?></span><a href="<?= e(url('admin/clientes.php?id=' . (int) $c['id'])) ?>"><strong><?= e($c['nome']) ?></strong></a></div></td>
          <td data-label="Telefone"><?= e(format_phone($c['telefone'])) ?></td>
          <td data-label="E-mail"><?= e($c['email'] ?: '—') ?></td>
          <td data-label="Último atendimento"><?= e(date_br($c['ultimo'])) ?></td>
          <td data-label="Visitas" class="t-center"><span class="pill"><?= (int) $c['visitas'] ?></span></td>
          <td data-label="Gasto" class="t-right"><?= e(money($c['gasto'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clientes): ?><tr><td colspan="6" class="empty">Nenhum cliente encontrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= $pager ?>
</section>

<div class="modal" id="modal-cliente" role="dialog" aria-modal="true" aria-labelledby="mc-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="mc-title">Novo cliente</h3>
    <form method="post" class="form" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="">
      <div class="field"><label for="n-nome">Nome</label><input id="n-nome" name="nome" required minlength="3" maxlength="120"></div>
      <div class="form-row">
        <div class="field"><label for="n-tel">Telefone</label><input id="n-tel" name="telefone" required data-mask="phone" inputmode="tel"></div>
        <div class="field"><label for="n-email">E-mail</label><input id="n-email" type="email" name="email" maxlength="160"></div>
      </div>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Cadastrar</button></div>
    </form>
  </div>
</div>
<?php admin_footer();
