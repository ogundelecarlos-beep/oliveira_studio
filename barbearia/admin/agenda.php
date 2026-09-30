<?php
require __DIR__ . '/includes/auth.php';
$u = require_login();
$pdo = db();

/* ================================================================== */
/* Ações (POST)                                                        */
/* ================================================================== */
if (is_post()) {
    $acao = input('acao');
    $voltarMap = ['dashboard' => 'admin/dashboard.php', 'horarios' => 'admin/horarios.php'];
    $voltar = isset($voltarMap[input('voltar')]) ? $voltarMap[input('voltar')] : 'admin/agenda.php?' . http_build_query(array_filter([
        'view' => input('r_view'), 'date' => input('r_date'), 'barbeiro' => input('r_barbeiro'),
    ]));
    admin_post_guard($voltar);

    if ($acao === 'status') {
        $id = input_int('id');
        $status = input('status');
        if (!array_key_exists($status, status_labels())) {
            flash('error', 'Status inválido.');
            redirect($voltar);
        }
        $st = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ?');
        $st->execute([$id]);
        $ag = $st->fetch();
        if (!$ag) {
            flash('error', 'Agendamento não encontrado.');
            redirect($voltar);
        }
        // Reativar um cancelado exige que o horário ainda esteja livre
        if ($ag['status'] === 'cancelado' && $status !== 'cancelado') {
            $dur = to_min($ag['hora_fim']) - to_min($ag['hora']);
            if ($erro = validar_horario((int) $ag['barbeiro_id'], $ag['data'], hm($ag['hora']), $dur, $id, true, false)) {
                flash('error', 'Não é possível reativar: ' . $erro);
                redirect($voltar);
            }
        }
        try {
            $pdo->prepare('UPDATE agendamentos SET status = ? WHERE id = ?')->execute([$status, $id]);
            flash('success', 'Agendamento marcado como "' . status_labels()[$status] . '".');
        } catch (PDOException $e) {
            flash('error', 'Não foi possível alterar: já existe outro agendamento neste horário.');
        }
        redirect($voltar);
    }

    if ($acao === 'salvar') {
        $id = input_int('id') ?: null;
        $modo = input('cliente_modo');
        $erros = [];
        if ($modo === 'novo') {
            $nome = input('cliente_nome');
            $tel = normalize_phone(input('cliente_telefone'));
            $email = strtolower(input('cliente_email'));
            if (str_len($nome) < 3) $erros[] = 'Informe o nome do cliente.';
            if (!valid_phone($tel)) $erros[] = 'Telefone do cliente inválido.';
            if ($email !== '' && !valid_email($email)) $erros[] = 'E-mail do cliente inválido.';
            if (!$erros) {
                $clienteId = cliente_por_telefone($nome, $tel, $email ?: null);
            }
        } else {
            $clienteId = input_int('cliente_id');
            $st = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = ?');
            $st->execute([$clienteId]);
            if (!(int) $st->fetchColumn()) $erros[] = 'Selecione um cliente.';
        }
        $data = input('data');
        $hora = substr(input('hora'), 0, 5);
        $status = input('status', 'confirmado');
        $obs = input('observacao');
        $valorIn = parse_money(input('valor'));
        if (!valid_date($data)) $erros[] = 'Data inválida.';
        if (!valid_time($hora)) $erros[] = 'Horário inválido.';
        if (!array_key_exists($status, status_labels())) $erros[] = 'Status inválido.';
        if (input_int('barbeiro_id') <= 0) $erros[] = 'Selecione o barbeiro.';
        if ($valorIn !== '' && (!is_numeric($valorIn) || (float) $valorIn < 0)) $erros[] = 'Valor inválido.';
        if (str_len($obs) > 500) $erros[] = 'Observação muito longa (máx. 500).';

        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect($voltar);
        }
        $res = salvar_agendamento([
            'cliente_id' => $clienteId, 'servico_id' => input_int('servico_id'), 'barbeiro_id' => input_int('barbeiro_id'),
            'data' => $data, 'hora' => $hora, 'status' => $status, 'observacao' => $obs,
            'valor' => $valorIn !== '' ? (float) $valorIn : null,
        ], $id, true);
        if ($res['ok']) {
            flash('success', $id ? 'Agendamento atualizado com sucesso.' : 'Agendamento criado com sucesso.');
            redirect('admin/agenda.php?' . http_build_query(['view' => input('r_view') ?: 'day', 'date' => $data, 'barbeiro' => input('r_barbeiro')]));
        }
        flash('error', $res['erro']);
        redirect($voltar);
    }

    if ($acao === 'bloquear') {
        $barbeiroId = input_int('barbeiro_id') ?: null;
        $data = input('data');
        $diaInteiro = !empty($_POST['dia_inteiro']);
        $ini = $diaInteiro ? null : substr(input('hora_inicio'), 0, 5);
        $fim = $diaInteiro ? null : substr(input('hora_fim'), 0, 5);
        $tipo = input('tipo');
        $motivo = input('motivo');
        $dataFim = input('data_fim') ?: $data;

        if (!valid_date($data) || !valid_date($dataFim) || $dataFim < $data) {
            flash('error', 'Informe um período de datas válido.');
            redirect($voltar);
        }
        if ((strtotime($dataFim) - strtotime($data)) / 86400 > 60) {
            flash('error', 'O período máximo de bloqueio é de 60 dias.');
            redirect($voltar);
        }
        if (!$diaInteiro && (!valid_time((string) $ini) || !valid_time((string) $fim) || $ini >= $fim)) {
            flash('error', 'Informe um intervalo de horário válido (início antes do fim).');
            redirect($voltar);
        }
        if (!array_key_exists($tipo, tipos_bloqueio())) {
            $tipo = 'outro';
        }
        // Não permite bloquear por cima de agendamentos ativos
        $sql = "SELECT COUNT(*) FROM agendamentos WHERE status <> 'cancelado' AND data BETWEEN ? AND ?";
        $p = [$data, $dataFim];
        if ($barbeiroId) { $sql .= ' AND barbeiro_id = ?'; $p[] = $barbeiroId; }
        if (!$diaInteiro) { $sql .= ' AND hora < ? AND hora_fim > ?'; $p[] = $fim; $p[] = $ini; }
        $st = $pdo->prepare($sql);
        $st->execute($p);
        $conflitos = (int) $st->fetchColumn();
        if ($conflitos > 0) {
            flash('error', "Existem {$conflitos} agendamento(s) ativo(s) neste período. Remaneje ou cancele antes de bloquear.");
            redirect($voltar);
        }
        $ins = $pdo->prepare('INSERT INTO bloqueios (barbeiro_id, data, hora_inicio, hora_fim, tipo, motivo) VALUES (?, ?, ?, ?, ?, ?)');
        for ($d = $data; $d <= $dataFim; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $ins->execute([$barbeiroId, $d, $ini, $fim, $tipo, $motivo !== '' ? mb_substr($motivo, 0, 255) : null]);
        }
        flash('success', 'Horário bloqueado com sucesso.');
        redirect($voltar);
    }

    if ($acao === 'excluir_bloqueio') {
        $pdo->prepare('DELETE FROM bloqueios WHERE id = ?')->execute([input_int('id')]);
        flash('success', 'Bloqueio removido. O horário voltou a ficar disponível.');
        redirect($voltar);
    }

    redirect($voltar);
}

/* ================================================================== */
/* Visualização                                                        */
/* ================================================================== */
$view = in_array(input('view'), ['day', 'week', 'month'], true) ? input('view') : 'day';
$date = valid_date(input('date')) ? input('date') : date('Y-m-d');
$filtroBarbeiro = input_int('barbeiro');
$mostrarCancelados = input('cancelados') === '1';
$hoje = date('Y-m-d');
$ts = strtotime($date);

if ($view === 'day') {
    $ini = $fim = $date;
    $prev = date('Y-m-d', strtotime('-1 day', $ts));
    $next = date('Y-m-d', strtotime('+1 day', $ts));
    $titulo = data_extenso($date) . ' de ' . date('Y', $ts);
} elseif ($view === 'week') {
    $dow = (int) date('N', $ts);
    $ini = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
    $fim = date('Y-m-d', strtotime('+6 days', strtotime($ini)));
    $prev = date('Y-m-d', strtotime('-7 days', $ts));
    $next = date('Y-m-d', strtotime('+7 days', $ts));
    $titulo = date('d/m', strtotime($ini)) . ' — ' . date('d/m/Y', strtotime($fim));
} else {
    $ini = date('Y-m-01', $ts);
    $fim = date('Y-m-t', $ts);
    $prev = date('Y-m-d', strtotime('first day of previous month', $ts));
    $next = date('Y-m-d', strtotime('first day of next month', $ts));
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $titulo = $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
}

$todosBarbeiros = $pdo->query('SELECT id, nome, ativo FROM barbeiros ORDER BY ordem, nome')->fetchAll();
$cores = ['#c9a45c', '#6aa9e8', '#5cc48a', '#d77fb3', '#e6874f', '#9d8cf0', '#4fc3c7', '#e0645a'];
$corBarbeiro = [];
foreach ($todosBarbeiros as $i => $b) {
    $corBarbeiro[(int) $b['id']] = $cores[$i % count($cores)];
}
$barbeirosVisiveis = array_values(array_filter($todosBarbeiros, fn($b) => ($filtroBarbeiro ? (int) $b['id'] === $filtroBarbeiro : (int) $b['ativo'] === 1)));

$sql = "SELECT a.*, c.nome cliente_nome, c.telefone cliente_telefone, s.nome servico_nome, b.nome barbeiro_nome
        FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id JOIN servicos s ON s.id = a.servico_id JOIN barbeiros b ON b.id = a.barbeiro_id
        WHERE a.data BETWEEN ? AND ?";
$p = [$ini, $fim];
if (!$mostrarCancelados) { $sql .= " AND a.status <> 'cancelado'"; }
if ($filtroBarbeiro) { $sql .= ' AND a.barbeiro_id = ?'; $p[] = $filtroBarbeiro; }
$st = $pdo->prepare($sql . ' ORDER BY a.data, a.hora');
$st->execute($p);
$agendamentos = $st->fetchAll();

$sql = 'SELECT bl.*, b.nome barbeiro_nome FROM bloqueios bl LEFT JOIN barbeiros b ON b.id = bl.barbeiro_id WHERE bl.data BETWEEN ? AND ?';
$p = [$ini, $fim];
if ($filtroBarbeiro) { $sql .= ' AND (bl.barbeiro_id IS NULL OR bl.barbeiro_id = ?)'; $p[] = $filtroBarbeiro; }
$st = $pdo->prepare($sql . ' ORDER BY bl.data, bl.hora_inicio');
$st->execute($p);
$bloqueios = $st->fetchAll();

// Limites da grade de horário
$lim = $pdo->query('SELECT MIN(hora_inicio) i, MAX(hora_fim) f FROM horarios')->fetch();
$gridIni = $lim['i'] ? (int) floor(to_min($lim['i']) / 60) * 60 : 8 * 60;
$gridFim = $lim['f'] ? (int) ceil(to_min($lim['f']) / 60) * 60 : 20 * 60;
foreach ($agendamentos as $a) {
    $gridIni = min($gridIni, (int) floor(to_min($a['hora']) / 60) * 60);
    $gridFim = max($gridFim, (int) ceil(to_min($a['hora_fim']) / 60) * 60);
}
$ppm = 1.5; // pixels por minuto

$servicos = $pdo->query('SELECT id, nome, preco, duracao, ativo FROM servicos ORDER BY ativo DESC, ordem, nome')->fetchAll();
$clientes = $pdo->query('SELECT id, nome, telefone FROM clientes ORDER BY nome')->fetchAll();

function ag_json(array $a): string
{
    return e(json_encode([
        'id' => (int) $a['id'], 'cliente_id' => (int) $a['cliente_id'], 'servico_id' => (int) $a['servico_id'],
        'barbeiro_id' => (int) $a['barbeiro_id'], 'data' => $a['data'], 'hora' => hm($a['hora']), 'status' => $a['status'],
        'observacao' => (string) $a['observacao'], 'valor' => number_format((float) $a['valor'], 2, ',', ''),
        'cliente_nome' => $a['cliente_nome'], 'cliente_telefone' => format_phone($a['cliente_telefone']),
        'servico_nome' => $a['servico_nome'], 'barbeiro_nome' => $a['barbeiro_nome'],
        'fim' => hm($a['hora_fim']), 'data_br' => date_br($a['data']),
        'whatsapp' => whatsapp_link('Olá, ' . explode(' ', $a['cliente_nome'])[0] . '! Sobre seu horário na ' . settings()['nome_barbearia'] . ': ' . $a['servico_nome'] . ' em ' . date_br($a['data']) . ' às ' . hm($a['hora']) . '.', $a['cliente_telefone']),
    ], JSON_UNESCAPED_UNICODE));
}

$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['view' => $view, 'date' => $date, 'barbeiro' => $filtroBarbeiro ?: null, 'cancelados' => $mostrarCancelados ? '1' : null], $over), fn($v) => $v !== null && $v !== ''));
$returnFields = '<input type="hidden" name="r_view" value="' . e($view) . '"><input type="hidden" name="r_date" value="' . e($date) . '"><input type="hidden" name="r_barbeiro" value="' . e((string) $filtroBarbeiro) . '">';

admin_header('Agenda', 'agenda', [
    'actions' => '<button type="button" class="btn btn-ghost btn-sm" data-modal-open="modal-bloqueio">' . icon('ban') . '<span class="hide-sm">Bloquear horário</span></button>'
        . '<button type="button" class="btn btn-gold btn-sm" data-new-appointment>' . icon('plus') . '<span class="hide-sm">Novo agendamento</span></button>',
]);
?>

<div class="agenda-toolbar card">
  <div class="agenda-nav">
    <a class="icon-btn" href="<?= e($qs(['date' => $prev])) ?>" aria-label="Anterior"><?= icon('chevron-left') ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= e($qs(['date' => $hoje])) ?>">Hoje</a>
    <a class="icon-btn" href="<?= e($qs(['date' => $next])) ?>" aria-label="Próximo"><?= icon('chevron-right') ?></a>
    <h2><?= e($titulo) ?></h2>
  </div>
  <form class="agenda-filters" method="get">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="date" name="date" value="<?= e($date) ?>" aria-label="Ir para data" onchange="this.form.submit()">
    <select name="barbeiro" aria-label="Filtrar barbeiro" onchange="this.form.submit()">
      <option value="">Todos os barbeiros</option>
      <?php foreach ($todosBarbeiros as $b): ?><option value="<?= (int) $b['id'] ?>" <?= $filtroBarbeiro === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['nome']) ?><?= $b['ativo'] ? '' : ' (inativo)' ?></option><?php endforeach; ?>
    </select>
    <label class="check"><input type="checkbox" name="cancelados" value="1" <?= $mostrarCancelados ? 'checked' : '' ?> onchange="this.form.submit()"> Cancelados</label>
  </form>
  <div class="segmented" role="tablist" aria-label="Visualização">
    <?php foreach (['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'] as $k => $label): ?>
      <a href="<?= e($qs(['view' => $k])) ?>" class="<?= $view === $k ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $view === $k ? 'true' : 'false' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="legend">
  <?php foreach ($barbeirosVisiveis as $b): ?><span><i style="background: <?= e($corBarbeiro[(int) $b['id']]) ?>"></i><?= e($b['nome']) ?></span><?php endforeach; ?>
  <span><i class="legend-block"></i>Bloqueado / fora do expediente</span>
</div>

<?php if ($view === 'day'): ?>
  <?php
  $dow = (int) date('w', $ts);
  $abertoCasa = periodos_barbearia($dow);
  ?>
  <?php if (!$abertoCasa): ?><div class="alert alert-warning inline">A barbearia está fechada neste dia da semana.</div><?php endif; ?>
  <div class="calendar card">
    <div class="cal-scroll">
      <div class="cal-grid" style="--cols: <?= max(1, count($barbeirosVisiveis)) ?>; --h: <?= ($gridFim - $gridIni) * $ppm ?>px; --slot: <?= 60 * $ppm ?>px">
        <div class="cal-head">
          <div class="cal-corner"></div>
          <?php foreach ($barbeirosVisiveis as $b): ?>
            <div class="cal-col-head"><span class="dot-color" style="background: <?= e($corBarbeiro[(int) $b['id']]) ?>"></span><?= e($b['nome']) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="cal-body">
          <div class="cal-times">
            <?php for ($m = $gridIni; $m < $gridFim; $m += 60): ?><span style="top: <?= ($m - $gridIni) * $ppm ?>px"><?= min_to_time($m) ?></span><?php endfor; ?>
          </div>
          <?php foreach ($barbeirosVisiveis as $b):
              $bid = (int) $b['id'];
              $periodos = periodos_trabalho($bid, $date);
              // Áreas fora do expediente
              $cursor = $gridIni;
              $fora = [];
              foreach ($periodos as [$s, $e2]) { if ($s > $cursor) $fora[] = [$cursor, $s]; $cursor = max($cursor, $e2); }
              if ($cursor < $gridFim) $fora[] = [$cursor, $gridFim];
              ?>
            <div class="cal-col" data-date="<?= e($date) ?>" data-barbeiro="<?= $bid ?>" data-grid-start="<?= $gridIni ?>" data-ppm="<?= $ppm ?>">
              <?php foreach ($fora as [$s, $e2]): ?><div class="cal-off" style="top: <?= ($s - $gridIni) * $ppm ?>px; height: <?= ($e2 - $s) * $ppm ?>px"></div><?php endforeach; ?>
              <?php foreach ($bloqueios as $bl):
                  if ($bl['barbeiro_id'] !== null && (int) $bl['barbeiro_id'] !== $bid) continue;
                  $s = $bl['hora_inicio'] ? to_min($bl['hora_inicio']) : $gridIni;
                  $e2 = $bl['hora_fim'] ? to_min($bl['hora_fim']) : $gridFim; ?>
                <div class="cal-block" style="top: <?= (max($s, $gridIni) - $gridIni) * $ppm ?>px; height: <?= (min($e2, $gridFim) - max($s, $gridIni)) * $ppm ?>px">
                  <span><?= icon('ban') ?> <?= e(tipos_bloqueio()[$bl['tipo']]) ?><?= $bl['motivo'] ? ' · ' . e($bl['motivo']) : '' ?></span>
                </div>
              <?php endforeach; ?>
              <?php foreach ($agendamentos as $a):
                  if ((int) $a['barbeiro_id'] !== $bid) continue;
                  $s = to_min($a['hora']); $e2 = to_min($a['hora_fim']); ?>
                <button type="button" class="cal-event status-<?= e($a['status']) ?><?= ($e2 - $s) < 45 ? ' is-short' : '' ?>" style="top: <?= ($s - $gridIni) * $ppm ?>px; height: <?= max(24, ($e2 - $s) * $ppm - 2) ?>px; --c: <?= e($corBarbeiro[$bid]) ?>" data-appointment="<?= ag_json($a) ?>">
                  <strong><?= e(hm($a['hora'])) ?> – <?= e(hm($a['hora_fim'])) ?></strong>
                  <span><?= e($a['cliente_nome']) ?></span>
                  <small><?= e($a['servico_nome']) ?></small>
                </button>
              <?php endforeach; ?>
              <?php if ($date === $hoje): $now = (int) date('G') * 60 + (int) date('i'); if ($now > $gridIni && $now < $gridFim): ?>
                <div class="cal-now" style="top: <?= ($now - $gridIni) * $ppm ?>px"></div>
              <?php endif; endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <p class="cal-hint muted small"><?= icon('plus') ?> Clique em um espaço livre para criar um agendamento naquele horário.</p>
  </div>

<?php elseif ($view === 'week'): ?>
  <?php $dias = []; for ($i = 0; $i < 7; $i++) { $dias[] = date('Y-m-d', strtotime("+$i days", strtotime($ini))); }
  $nb = max(1, count($barbeirosVisiveis));
  $laneOf = [];
  foreach ($barbeirosVisiveis as $i => $b) { $laneOf[(int) $b['id']] = $i; } ?>
  <div class="calendar card">
    <div class="cal-scroll">
      <div class="cal-grid is-week" style="--cols: 7; --h: <?= ($gridFim - $gridIni) * $ppm ?>px; --slot: <?= 60 * $ppm ?>px">
        <div class="cal-head">
          <div class="cal-corner"></div>
          <?php foreach ($dias as $d): ?>
            <a class="cal-col-head <?= $d === $hoje ? 'is-today' : '' ?>" href="<?= e($qs(['view' => 'day', 'date' => $d])) ?>">
              <small><?= e(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', strtotime($d))]) ?></small><b><?= date('d', strtotime($d)) ?></b>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="cal-body">
          <div class="cal-times">
            <?php for ($m = $gridIni; $m < $gridFim; $m += 60): ?><span style="top: <?= ($m - $gridIni) * $ppm ?>px"><?= min_to_time($m) ?></span><?php endfor; ?>
          </div>
          <?php foreach ($dias as $d):
              $casa = periodos_barbearia((int) date('w', strtotime($d)));
              $cursor = $gridIni; $fora = [];
              foreach ($casa as [$s, $e2]) { if ($s > $cursor) $fora[] = [$cursor, $s]; $cursor = max($cursor, $e2); }
              if ($cursor < $gridFim) $fora[] = [$cursor, $gridFim]; ?>
            <div class="cal-col" data-date="<?= e($d) ?>" data-barbeiro="<?= $filtroBarbeiro ?: '' ?>" data-grid-start="<?= $gridIni ?>" data-ppm="<?= $ppm ?>">
              <?php foreach ($fora as [$s, $e2]): ?><div class="cal-off" style="top: <?= ($s - $gridIni) * $ppm ?>px; height: <?= ($e2 - $s) * $ppm ?>px"></div><?php endforeach; ?>
              <?php foreach ($bloqueios as $bl): if ($bl['data'] !== $d) continue;
                  $s = $bl['hora_inicio'] ? to_min($bl['hora_inicio']) : $gridIni; $e2 = $bl['hora_fim'] ? to_min($bl['hora_fim']) : $gridFim;
                  $lane = $bl['barbeiro_id'] !== null && isset($laneOf[(int) $bl['barbeiro_id']]) ? $laneOf[(int) $bl['barbeiro_id']] : null; ?>
                <div class="cal-block" title="<?= e(tipos_bloqueio()[$bl['tipo']] . ($bl['barbeiro_nome'] ? ' · ' . $bl['barbeiro_nome'] : ' · Todos') . ($bl['motivo'] ? ' · ' . $bl['motivo'] : '')) ?>" style="top: <?= (max($s, $gridIni) - $gridIni) * $ppm ?>px; height: <?= (min($e2, $gridFim) - max($s, $gridIni)) * $ppm ?>px;<?= $lane !== null ? ' left: calc(' . $lane . ' * 100% / ' . $nb . '); width: calc(100% / ' . $nb . ');' : '' ?>"></div>
              <?php endforeach; ?>
              <?php foreach ($agendamentos as $a): if ($a['data'] !== $d) continue;
                  $s = to_min($a['hora']); $e2 = to_min($a['hora_fim']); $lane = $laneOf[(int) $a['barbeiro_id']] ?? 0; ?>
                <button type="button" class="cal-event compact status-<?= e($a['status']) ?>" style="top: <?= ($s - $gridIni) * $ppm ?>px; height: <?= max(20, ($e2 - $s) * $ppm - 2) ?>px; left: calc(<?= $lane ?> * 100% / <?= $nb ?> + 2px); width: calc(100% / <?= $nb ?> - 4px); --c: <?= e($corBarbeiro[(int) $a['barbeiro_id']]) ?>" data-appointment="<?= ag_json($a) ?>" title="<?= e(hm($a['hora']) . ' ' . $a['cliente_nome'] . ' · ' . $a['servico_nome'] . ' · ' . $a['barbeiro_nome']) ?>">
                  <strong><?= e(hm($a['hora'])) ?></strong><span><?= e(explode(' ', $a['cliente_nome'])[0]) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <?php
  $gridStart = strtotime('-' . ((int) date('N', strtotime($ini)) - 1) . ' days', strtotime($ini));
  $porDia = [];
  foreach ($agendamentos as $a) { $porDia[$a['data']][] = $a; }
  $blqDia = [];
  foreach ($bloqueios as $bl) { $blqDia[$bl['data']][] = $bl; }
  ?>
  <div class="month card">
    <div class="month-head"><?php foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $w): ?><span><?= $w ?></span><?php endforeach; ?></div>
    <div class="month-grid">
      <?php for ($i = 0; $i < 42; $i++):
          $d = date('Y-m-d', strtotime("+$i days", $gridStart));
          if ($i === 35 && $d > $fim) break;
          $list = $porDia[$d] ?? [];
          $fechado = !periodos_barbearia((int) date('w', strtotime($d)));
          $blq = $blqDia[$d] ?? []; ?>
        <div class="month-cell <?= $d < $ini || $d > $fim ? 'is-out' : '' ?> <?= $d === $hoje ? 'is-today' : '' ?> <?= $fechado ? 'is-closed' : '' ?>">
          <a class="month-day" href="<?= e($qs(['view' => 'day', 'date' => $d])) ?>"><b><?= (int) date('d', strtotime($d)) ?></b><?php if ($list): ?><em><?= count($list) ?></em><?php endif; ?></a>
          <?php foreach ($blq as $bl): ?><span class="month-block" title="<?= e($bl['motivo'] ?? '') ?>"><?= icon('ban') ?> <?= e(tipos_bloqueio()[$bl['tipo']]) ?><?= $bl['barbeiro_nome'] ? ' · ' . e(explode(' ', $bl['barbeiro_nome'])[0]) : '' ?></span><?php endforeach; ?>
          <?php foreach (array_slice($list, 0, 3) as $a): ?>
            <button type="button" class="month-event status-<?= e($a['status']) ?>" style="--c: <?= e($corBarbeiro[(int) $a['barbeiro_id']]) ?>" data-appointment="<?= ag_json($a) ?>"><?= e(hm($a['hora'])) ?> <?= e(explode(' ', $a['cliente_nome'])[0]) ?></button>
          <?php endforeach; ?>
          <?php if (count($list) > 3): ?><a class="month-more" href="<?= e($qs(['view' => 'day', 'date' => $d])) ?>">+<?= count($list) - 3 ?> mais</a><?php endif; ?>
          <?php if ($fechado && !$list): ?><span class="month-closed">Fechado</span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($bloqueios): ?>
<section class="card">
  <header class="card-head"><h2>Bloqueios no período</h2></header>
  <div class="table-wrap">
    <table class="table table-responsive">
      <thead><tr><th>Data</th><th>Horário</th><th>Profissional</th><th>Tipo</th><th>Motivo</th><th class="t-right">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($bloqueios as $bl): ?>
        <tr>
          <td data-label="Data"><?= e(date_br($bl['data'])) ?></td>
          <td data-label="Horário"><?= $bl['hora_inicio'] ? e(hm($bl['hora_inicio']) . ' – ' . hm($bl['hora_fim'])) : 'Dia inteiro' ?></td>
          <td data-label="Profissional"><?= e($bl['barbeiro_nome'] ?: 'Toda a barbearia') ?></td>
          <td data-label="Tipo"><span class="badge badge-neutral"><?= e(tipos_bloqueio()[$bl['tipo']]) ?></span></td>
          <td data-label="Motivo"><?= e($bl['motivo'] ?: '—') ?></td>
          <td class="t-right">
            <form method="post" data-confirm="Remover este bloqueio?"><?= csrf_field() ?><?= $returnFields ?><input type="hidden" name="acao" value="excluir_bloqueio"><input type="hidden" name="id" value="<?= (int) $bl['id'] ?>"><button class="icon-btn danger" aria-label="Remover bloqueio" title="Remover"><?= icon('trash') ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Modal: agendamento -->
<div class="modal" id="modal-agendamento" role="dialog" aria-modal="true" aria-labelledby="ag-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-lg">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="ag-title">Novo agendamento</h3>
    <div class="ag-quick" data-ag-quick hidden>
      <?php foreach (['confirmado' => ['Confirmar', 'check', 'success'], 'concluido' => ['Concluir', 'scissors', 'success'], 'cancelado' => ['Cancelar', 'ban', 'danger']] as $st => [$label, $ic, $cls]): ?>
        <form method="post" data-status-form="<?= $st ?>" <?= $st === 'cancelado' ? 'data-confirm="Cancelar este agendamento? O horário será liberado."' : '' ?>>
          <?= csrf_field() ?><?= $returnFields ?><input type="hidden" name="acao" value="status"><input type="hidden" name="id" value=""><input type="hidden" name="status" value="<?= $st ?>">
          <button class="btn btn-sm btn-<?= $cls === 'danger' ? 'danger-ghost' : 'ghost' ?>"><?= icon($ic) ?> <?= $label ?></button>
        </form>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-ghost" href="#" target="_blank" rel="noopener" data-ag-whatsapp><?= icon('whatsapp') ?> WhatsApp</a>
    </div>
    <form method="post" class="form" id="form-agendamento" novalidate>
      <?= csrf_field() ?><?= $returnFields ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="">
      <div class="field">
        <label>Cliente</label>
        <div class="segmented segmented-sm" data-client-mode>
          <button type="button" class="is-active" data-mode="existente">Cliente cadastrado</button>
          <button type="button" data-mode="novo">Novo cliente</button>
        </div>
        <input type="hidden" name="cliente_modo" value="existente">
      </div>
      <div data-mode-panel="existente" class="field">
        <select name="cliente_id" aria-label="Selecionar cliente">
          <option value="">Selecione o cliente...</option>
          <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?> — <?= e(format_phone($c['telefone'])) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div data-mode-panel="novo" class="form-row three" hidden>
        <div class="field"><label for="cn">Nome</label><input id="cn" name="cliente_nome" maxlength="120"></div>
        <div class="field"><label for="ct">Telefone</label><input id="ct" name="cliente_telefone" data-mask="phone" inputmode="tel"></div>
        <div class="field"><label for="ce">E-mail</label><input id="ce" type="email" name="cliente_email" maxlength="160"></div>
      </div>
      <div class="form-row">
        <div class="field"><label for="ag-servico">Serviço</label>
          <select id="ag-servico" name="servico_id" required data-slot-trigger>
            <?php foreach ($servicos as $s): ?><option value="<?= (int) $s['id'] ?>" data-preco="<?= e(number_format((float) $s['preco'], 2, ',', '')) ?>"><?= e($s['nome']) ?> (<?= e(duracao_label((int) $s['duracao'])) ?> · <?= e(money($s['preco'])) ?>)<?= $s['ativo'] ? '' : ' — inativo' ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="ag-barbeiro">Barbeiro</label>
          <select id="ag-barbeiro" name="barbeiro_id" required data-slot-trigger>
            <?php foreach ($todosBarbeiros as $b): if (!$b['ativo']) continue; ?><option value="<?= (int) $b['id'] ?>"><?= e($b['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row three">
        <div class="field"><label for="ag-data">Data</label><input id="ag-data" type="date" name="data" required data-slot-trigger></div>
        <div class="field"><label for="ag-hora">Horário</label><input id="ag-hora" type="time" name="hora" required step="300"></div>
        <div class="field"><label for="ag-status">Status</label>
          <select id="ag-status" name="status"><?php foreach (status_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'confirmado' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="slot-suggest" data-slot-suggest aria-live="polite"></div>
      <div class="form-row">
        <div class="field"><label for="ag-valor">Valor (R$) <span class="muted">— vazio usa o preço/promoção</span></label><input id="ag-valor" name="valor" inputmode="decimal" placeholder="Automático"></div>
        <div class="field"><label for="ag-obs">Observação</label><input id="ag-obs" name="observacao" maxlength="500"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-modal-close>Fechar</button>
        <button type="submit" class="btn btn-gold">Salvar agendamento</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: bloqueio -->
<div class="modal" id="modal-bloqueio" role="dialog" aria-modal="true" aria-labelledby="bl-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="bl-title">Bloquear horário</h3>
    <p class="muted small">Horários bloqueados deixam de aparecer para os clientes no agendamento online.</p>
    <form method="post" class="form" novalidate>
      <?= csrf_field() ?><?= $returnFields ?>
      <input type="hidden" name="acao" value="bloquear">
      <div class="form-row">
        <div class="field"><label for="bl-barb">Profissional</label>
          <select id="bl-barb" name="barbeiro_id"><option value="0">Toda a barbearia</option><?php foreach ($todosBarbeiros as $b): ?><option value="<?= (int) $b['id'] ?>" <?= $filtroBarbeiro === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['nome']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label for="bl-tipo">Tipo</label>
          <select id="bl-tipo" name="tipo"><?php foreach (tipos_bloqueio() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-row">
        <div class="field"><label for="bl-data">Data inicial</label><input id="bl-data" type="date" name="data" value="<?= e($date) ?>" required></div>
        <div class="field"><label for="bl-data-fim">Data final <span class="muted">(opcional)</span></label><input id="bl-data-fim" type="date" name="data_fim"></div>
      </div>
      <label class="check"><input type="checkbox" name="dia_inteiro" value="1" data-toggle-target="#bl-horas"> Dia inteiro</label>
      <div class="form-row" id="bl-horas">
        <div class="field"><label for="bl-ini">Início</label><input id="bl-ini" type="time" name="hora_inicio" value="12:00"></div>
        <div class="field"><label for="bl-fim">Fim</label><input id="bl-fim" type="time" name="hora_fim" value="13:00"></div>
      </div>
      <div class="field"><label for="bl-motivo">Motivo</label><input id="bl-motivo" name="motivo" maxlength="255" placeholder="Ex.: consulta médica, manutenção do ar-condicionado..."></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
        <button type="submit" class="btn btn-gold">Bloquear</button>
      </div>
    </form>
  </div>
</div>

<script>
window.AGENDA = <?= json_encode([
    'abrir' => input_int('abrir') ?: null,
    'novo' => input('novo') === '1',
    'date' => $date,
    'barbeiro' => $filtroBarbeiro ?: null,
], JSON_HEX_TAG) ?>;
</script>
<?php admin_footer(['js/admin-agenda.js']);
