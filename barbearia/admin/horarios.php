<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

$barbeiros = $pdo->query('SELECT id, nome, ativo FROM barbeiros ORDER BY ativo DESC, ordem, nome')->fetchAll();
$alvo = input_int('barbeiro'); // 0 = barbearia
$back = 'admin/horarios.php' . ($alvo ? '?barbeiro=' . $alvo : '');

/** Converte as linhas de um dia em [aberto, ini, fim, intervalo, int_ini, int_fim]. */
function dia_para_form(array $rows): array
{
    if (!$rows) {
        return ['aberto' => false, 'ini' => '09:00', 'fim' => '18:00', 'intervalo' => false, 'int_ini' => '12:00', 'int_fim' => '13:00'];
    }
    $first = $rows[0];
    $last = end($rows);
    $out = ['aberto' => true, 'ini' => hm($first['hora_inicio']), 'fim' => hm($last['hora_fim']), 'intervalo' => false, 'int_ini' => '12:00', 'int_fim' => '13:00'];
    if (count($rows) > 1) {
        $out['intervalo'] = true;
        $out['int_ini'] = hm($rows[0]['hora_fim']);
        $out['int_fim'] = hm($rows[1]['hora_inicio']);
    }
    return $out;
}

if (is_post()) {
    admin_post_guard($back);
    $acao = input('acao');

    if ($acao === 'salvar_horarios') {
        $barbeiroId = input_int('barbeiro_id') ?: null;
        if ($barbeiroId) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM barbeiros WHERE id = ?');
            $st->execute([$barbeiroId]);
            if (!(int) $st->fetchColumn()) {
                flash('error', 'Barbeiro não encontrado.');
                redirect('admin/horarios.php');
            }
        }
        $modo = input('modo', 'personalizado');
        $novas = [];
        $erros = [];
        if (!$barbeiroId || $modo === 'personalizado') {
            $dias = $_POST['dia'] ?? [];
            for ($d = 0; $d < 7; $d++) {
                $row = is_array($dias[$d] ?? null) ? $dias[$d] : [];
                if (empty($row['aberto'])) continue;
                $ini = substr((string) ($row['ini'] ?? ''), 0, 5);
                $fim = substr((string) ($row['fim'] ?? ''), 0, 5);
                $nome = dias_semana()[$d];
                if (!valid_time($ini) || !valid_time($fim) || $ini >= $fim) {
                    $erros[] = "$nome: o horário de abertura deve ser anterior ao de fechamento.";
                    continue;
                }
                if (!empty($row['intervalo'])) {
                    $ii = substr((string) ($row['int_ini'] ?? ''), 0, 5);
                    $if = substr((string) ($row['int_fim'] ?? ''), 0, 5);
                    if (!valid_time($ii) || !valid_time($if) || $ii >= $if || $ii <= $ini || $if >= $fim) {
                        $erros[] = "$nome: o intervalo deve estar dentro do expediente.";
                        continue;
                    }
                    $novas[] = [$d, $ini, $ii];
                    $novas[] = [$d, $if, $fim];
                } else {
                    $novas[] = [$d, $ini, $fim];
                }
            }
        }
        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect($back);
        }
        $pdo->beginTransaction();
        if ($barbeiroId) {
            $pdo->prepare('DELETE FROM horarios WHERE barbeiro_id = ?')->execute([$barbeiroId]);
        } else {
            $pdo->exec('DELETE FROM horarios WHERE barbeiro_id IS NULL');
        }
        $ins = $pdo->prepare('INSERT INTO horarios (barbeiro_id, dia_semana, hora_inicio, hora_fim) VALUES (?, ?, ?, ?)');
        foreach ($novas as [$d, $i, $f]) {
            $ins->execute([$barbeiroId, $d, $i, $f]);
        }
        $pdo->commit();
        flash('success', $barbeiroId && $modo !== 'personalizado'
            ? 'Este barbeiro agora segue o horário de funcionamento da barbearia.'
            : 'Horários salvos. A disponibilidade do agendamento foi atualizada automaticamente.');
        redirect($back);
    }
    redirect($back);
}

// Carrega horários do alvo selecionado
$st = $alvo
    ? $pdo->prepare('SELECT * FROM horarios WHERE barbeiro_id = ? ORDER BY dia_semana, hora_inicio')
    : $pdo->prepare('SELECT * FROM horarios WHERE barbeiro_id IS NULL ORDER BY dia_semana, hora_inicio');
$st->execute($alvo ? [$alvo] : []);
$rows = $st->fetchAll();
$porDia = array_fill(0, 7, []);
foreach ($rows as $r) {
    $porDia[(int) $r['dia_semana']][] = $r;
}
$personalizado = !$alvo || count($rows) > 0;
if ($alvo && !$rows) {
    // Pré-preenche com o horário da casa para facilitar a personalização
    $casa = $pdo->query('SELECT * FROM horarios WHERE barbeiro_id IS NULL ORDER BY dia_semana, hora_inicio')->fetchAll();
    foreach ($casa as $r) {
        $porDia[(int) $r['dia_semana']][] = $r;
    }
}

$bloqueios = $pdo->query('SELECT bl.*, b.nome barbeiro_nome FROM bloqueios bl LEFT JOIN barbeiros b ON b.id = bl.barbeiro_id
                          WHERE bl.data >= CURDATE() ORDER BY bl.data, bl.hora_inicio LIMIT 100')->fetchAll();
$ordemDias = [1, 2, 3, 4, 5, 6, 0];

admin_header('Horários', 'horarios', ['subtitle' => 'Funcionamento, jornadas e bloqueios']);
?>
<div class="tabs-bar">
  <a href="<?= e(url('admin/horarios.php')) ?>" class="<?= !$alvo ? 'is-active' : '' ?>"><?= icon('settings') ?> Barbearia</a>
  <?php foreach ($barbeiros as $b): ?>
    <a href="<?= e(url('admin/horarios.php?barbeiro=' . (int) $b['id'])) ?>" class="<?= $alvo === (int) $b['id'] ? 'is-active' : '' ?>"><?= e($b['nome']) ?><?= $b['ativo'] ? '' : ' (inativo)' ?></a>
  <?php endforeach; ?>
</div>

<section class="card">
  <header class="card-head">
    <div>
      <h2><?= $alvo ? 'Jornada de ' . e(array_column($barbeiros, 'nome', 'id')[$alvo] ?? '') : 'Horário de funcionamento' ?></h2>
      <p class="muted small"><?= $alvo ? 'A jornada do barbeiro sempre respeita o horário de funcionamento da barbearia.' : 'Dias desmarcados ficam fechados. Os clientes só conseguem agendar dentro destes horários.' ?></p>
    </div>
  </header>
  <form method="post" class="form" data-hours-form>
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar_horarios">
    <input type="hidden" name="barbeiro_id" value="<?= (int) $alvo ?>">
    <?php if ($alvo): ?>
      <div class="radio-cards">
        <label class="radio-card"><input type="radio" name="modo" value="casa" <?= $personalizado ? '' : 'checked' ?> data-hours-mode><span><strong>Seguir horário da barbearia</strong><small>Atende em todo o expediente da casa.</small></span></label>
        <label class="radio-card"><input type="radio" name="modo" value="personalizado" <?= $personalizado ? 'checked' : '' ?> data-hours-mode><span><strong>Jornada personalizada</strong><small>Define dias, horários e intervalo próprios.</small></span></label>
      </div>
    <?php endif; ?>
    <div class="hours-grid" data-hours-grid <?= $personalizado ? '' : 'hidden' ?>>
      <?php foreach ($ordemDias as $d): $f = dia_para_form($porDia[$d]); ?>
        <div class="hours-row <?= $f['aberto'] ? '' : 'is-closed' ?>" data-day-row>
          <label class="switch day-name"><input type="checkbox" name="dia[<?= $d ?>][aberto]" value="1" <?= $f['aberto'] ? 'checked' : '' ?> data-day-open><span></span> <?= e(dias_semana()[$d]) ?></label>
          <div class="hours-fields">
            <label><small>Abre</small><input type="time" name="dia[<?= $d ?>][ini]" value="<?= e($f['ini']) ?>"></label>
            <label><small>Fecha</small><input type="time" name="dia[<?= $d ?>][fim]" value="<?= e($f['fim']) ?>"></label>
            <label class="check"><input type="checkbox" name="dia[<?= $d ?>][intervalo]" value="1" <?= $f['intervalo'] ? 'checked' : '' ?> data-day-break> Intervalo</label>
            <label data-break-field><small>De</small><input type="time" name="dia[<?= $d ?>][int_ini]" value="<?= e($f['int_ini']) ?>"></label>
            <label data-break-field><small>Até</small><input type="time" name="dia[<?= $d ?>][int_fim]" value="<?= e($f['int_fim']) ?>"></label>
          </div>
          <span class="closed-label">Fechado</span>
        </div>
      <?php endforeach; ?>
    </div>
    <div><button class="btn btn-gold">Salvar horários</button></div>
  </form>
</section>

<section class="card">
  <header class="card-head">
    <div><h2>Bloqueios programados</h2><p class="muted small">Feriados, folgas, manutenção e compromissos. Horários bloqueados não aparecem no agendamento.</p></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/agenda.php')) ?>" data-modal-open="modal-bloqueio"><?= icon('ban') ?> Novo bloqueio</a>
  </header>
  <div class="table-wrap">
    <table class="table table-responsive">
      <thead><tr><th>Data</th><th>Horário</th><th>Profissional</th><th>Tipo</th><th>Motivo</th><th class="t-right">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($bloqueios as $bl): ?>
        <tr>
          <td data-label="Data"><strong><?= e(date_br($bl['data'])) ?></strong> <small class="muted"><?= e(dias_semana()[(int) date('w', strtotime($bl['data']))]) ?></small></td>
          <td data-label="Horário"><?= $bl['hora_inicio'] ? e(hm($bl['hora_inicio']) . ' – ' . hm($bl['hora_fim'])) : 'Dia inteiro' ?></td>
          <td data-label="Profissional"><?= e($bl['barbeiro_nome'] ?: 'Toda a barbearia') ?></td>
          <td data-label="Tipo"><span class="badge badge-neutral"><?= e(tipos_bloqueio()[$bl['tipo']]) ?></span></td>
          <td data-label="Motivo"><?= e($bl['motivo'] ?: '—') ?></td>
          <td class="t-right"><form method="post" action="<?= e(url('admin/agenda.php')) ?>" data-confirm="Remover este bloqueio?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir_bloqueio"><input type="hidden" name="voltar" value="horarios"><input type="hidden" name="id" value="<?= (int) $bl['id'] ?>"><button class="icon-btn danger" aria-label="Remover" title="Remover"><?= icon('trash') ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bloqueios): ?><tr><td colspan="6" class="empty">Nenhum bloqueio futuro.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="modal-bloqueio" role="dialog" aria-modal="true" aria-labelledby="bl-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="bl-title">Novo bloqueio</h3>
    <form method="post" action="<?= e(url('admin/agenda.php')) ?>" class="form" novalidate>
      <?= csrf_field() ?><input type="hidden" name="acao" value="bloquear"><input type="hidden" name="voltar" value="horarios">
      <div class="form-row">
        <div class="field"><label for="bl-barb">Profissional</label><select id="bl-barb" name="barbeiro_id"><option value="0">Toda a barbearia</option><?php foreach ($barbeiros as $b): ?><option value="<?= (int) $b['id'] ?>" <?= $alvo === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="bl-tipo">Tipo</label><select id="bl-tipo" name="tipo"><?php foreach (tipos_bloqueio() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-row">
        <div class="field"><label for="bl-data">Data inicial</label><input id="bl-data" type="date" name="data" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="field"><label for="bl-data-fim">Data final <span class="muted">(opcional)</span></label><input id="bl-data-fim" type="date" name="data_fim"></div>
      </div>
      <label class="check"><input type="checkbox" name="dia_inteiro" value="1" checked data-toggle-target="#bl-horas"> Dia inteiro</label>
      <div class="form-row" id="bl-horas">
        <div class="field"><label for="bl-ini">Início</label><input id="bl-ini" type="time" name="hora_inicio" value="12:00"></div>
        <div class="field"><label for="bl-fim">Fim</label><input id="bl-fim" type="time" name="hora_fim" value="13:00"></div>
      </div>
      <div class="field"><label for="bl-motivo">Motivo</label><input id="bl-motivo" name="motivo" maxlength="255" placeholder="Ex.: Feriado municipal"></div>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Bloquear</button></div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-day-row]').forEach(function (row) {
    var open = row.querySelector('[data-day-open]'), brk = row.querySelector('[data-day-break]');
    function sync() {
      row.classList.toggle('is-closed', !open.checked);
      row.querySelectorAll('[data-break-field]').forEach(function (f) { f.hidden = !brk.checked; });
    }
    open.addEventListener('change', sync); brk.addEventListener('change', sync); sync();
  });
  var grid = document.querySelector('[data-hours-grid]');
  document.querySelectorAll('[data-hours-mode]').forEach(function (r) {
    r.addEventListener('change', function () { grid.hidden = r.value !== 'personalizado' || !r.checked; });
  });
});
</script>
<?php admin_footer();
