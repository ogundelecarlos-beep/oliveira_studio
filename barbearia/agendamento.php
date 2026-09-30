<?php
require __DIR__ . '/includes/bootstrap.php';

$cfg = settings();
$hoje = date('Y-m-d');
$servicos = db()->query('SELECT * FROM servicos WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
$barbeiros = barbeiros_ativos();
$notas = notas_barbeiros();

$servicosJs = [];
foreach ($servicos as $s) {
    $p = promocao_para_servico((int) $s['id'], $hoje);
    $servicosJs[] = [
        'id' => (int) $s['id'], 'nome' => $s['nome'], 'preco' => (float) $s['preco'],
        'promo' => $p ? (float) $p['preco_promocional'] : null, 'duracao' => (int) $s['duracao'],
    ];
}
$barbeirosJs = array_map(fn($b) => ['id' => (int) $b['id'], 'nome' => $b['nome']], $barbeiros);

// Próximos dias com indicação de funcionamento da barbearia
$bloqueiosGerais = db()->prepare('SELECT data FROM bloqueios WHERE barbeiro_id IS NULL AND hora_inicio IS NULL AND data BETWEEN ? AND ?');
$fimJanela = date('Y-m-d', strtotime('+' . (int) $cfg['dias_antecedencia'] . ' days'));
$bloqueiosGerais->execute([$hoje, $fimJanela]);
$diasBloqueados = array_flip($bloqueiosGerais->fetchAll(PDO::FETCH_COLUMN));
$abertoDow = [];
for ($d = 0; $d < 7; $d++) {
    $abertoDow[$d] = (bool) periodos_barbearia($d);
}
$dias = [];
$semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
for ($i = 0; $i <= (int) $cfg['dias_antecedencia']; $i++) {
    $t = strtotime("+$i days", strtotime($hoje));
    $ymd = date('Y-m-d', $t);
    $dias[] = [
        'data' => $ymd, 'dia' => date('d', $t), 'semana' => $i === 0 ? 'Hoje' : ($i === 1 ? 'Amanhã' : $semana[(int) date('w', $t)]),
        'mes' => $meses[(int) date('n', $t)], 'aberto' => $abertoDow[(int) date('w', $t)] && !isset($diasBloqueados[$ymd]),
        'label' => date_br($ymd), 'extenso' => data_extenso($ymd),
    ];
}

$preServico = input_int('servico');
$preBarbeiro = input_int('barbeiro');

$pageTitle = 'Agendar horário';
$pageDescription = 'Agende seu horário online em poucos passos: escolha o serviço, o barbeiro, a data e o horário.';
$active = 'agendamento';
$bodyClass = 'page-booking';
$pageScripts = ['js/agendamento.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="booking">
  <div class="container">
    <header class="booking-head">
      <span class="eyebrow"><?= icon('calendar') ?> Agendamento online</span>
      <h1 class="display">Reserve seu <em>horário</em></h1>
      <p class="muted">Rápido, sem cadastro e com confirmação pelo WhatsApp.</p>
    </header>

    <ol class="stepper" aria-label="Etapas do agendamento">
      <?php foreach (['Serviço', 'Barbeiro', 'Data', 'Horário', 'Seus dados', 'Resumo'] as $i => $label): ?>
        <li data-step-indicator="<?= $i + 1 ?>" class="<?= $i === 0 ? 'is-current' : '' ?>"><span><?= $i + 1 ?></span><em><?= e($label) ?></em></li>
      <?php endforeach; ?>
    </ol>
    <div class="progress" aria-hidden="true"><span style="width:16.6%"></span></div>

    <div class="booking-layout">
      <form class="booking-form" id="booking-form" novalidate>
        <?= csrf_field() ?>

        <!-- Etapa 1 -->
        <fieldset class="step is-active" data-step="1">
          <legend class="step-title"><span>01</span> Escolha o serviço</legend>
          <div class="option-grid">
            <?php foreach ($servicos as $s):
                $p = promocao_para_servico((int) $s['id'], $hoje); ?>
              <label class="option-card">
                <input type="radio" name="servico_id" value="<?= (int) $s['id'] ?>" <?= $preServico === (int) $s['id'] ? 'checked' : '' ?>>
                <span class="option-media"><img src="<?= e(img_src($s['foto'])) ?>" alt="" loading="lazy"></span>
                <span class="option-body">
                  <strong><?= e($s['nome']) ?></strong>
                  <small><?= icon('clock') ?> <?= e(duracao_label((int) $s['duracao'])) ?></small>
                </span>
                <span class="option-price">
                  <?php if ($p): ?><del><?= e(money($s['preco'])) ?></del><?php endif; ?>
                  <b><?= e(money($p ? $p['preco_promocional'] : $s['preco'])) ?></b>
                </span>
                <span class="option-check"><?= icon('check') ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <!-- Etapa 2 -->
        <fieldset class="step" data-step="2">
          <legend class="step-title"><span>02</span> Escolha o profissional</legend>
          <div class="option-grid option-grid-barbers">
            <label class="option-card option-any">
              <input type="radio" name="barbeiro_id" value="0">
              <span class="option-avatar any"><?= icon('users') ?></span>
              <span class="option-body"><strong>Qualquer profissional disponível</strong><small>Mais horários para você escolher</small></span>
              <span class="option-check"><?= icon('check') ?></span>
            </label>
            <?php foreach ($barbeiros as $b):
                $n = $notas[(int) $b['id']] ?? ['media' => 5.0]; ?>
              <label class="option-card">
                <input type="radio" name="barbeiro_id" value="<?= (int) $b['id'] ?>" <?= $preBarbeiro === (int) $b['id'] ? 'checked' : '' ?>>
                <span class="option-avatar"><img src="<?= e(img_src($b['foto'], 'img/avatar.svg')) ?>" alt="" loading="lazy"></span>
                <span class="option-body">
                  <strong><?= e($b['nome']) ?></strong>
                  <small><?= e(implode(' · ', array_slice(especialidades_list($b['especialidades']), 0, 2))) ?></small>
                  <span class="rating-inline sm"><?= stars($n['media']) ?> <?= number_format($n['media'], 1, ',', '') ?></span>
                </span>
                <span class="option-check"><?= icon('check') ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <!-- Etapa 3 -->
        <fieldset class="step" data-step="3">
          <legend class="step-title"><span>03</span> Escolha a data</legend>
          <div class="date-strip-wrap">
            <button type="button" class="strip-nav prev" aria-label="Dias anteriores"><?= icon('chevron-left') ?></button>
            <div class="date-strip" role="radiogroup" aria-label="Datas disponíveis">
              <?php foreach ($dias as $d): ?>
                <label class="date-pill <?= $d['aberto'] ? '' : 'is-closed' ?>" title="<?= e($d['aberto'] ? $d['extenso'] : 'Fechado') ?>">
                  <input type="radio" name="data" value="<?= e($d['data']) ?>" <?= $d['aberto'] ? '' : 'disabled' ?> data-label="<?= e($d['label']) ?>" data-extenso="<?= e($d['extenso']) ?>">
                  <small><?= e($d['semana']) ?></small><strong><?= e($d['dia']) ?></strong><small><?= $d['aberto'] ? e($d['mes']) : 'Fechado' ?></small>
                </label>
              <?php endforeach; ?>
            </div>
            <button type="button" class="strip-nav next" aria-label="Próximos dias"><?= icon('chevron-right') ?></button>
          </div>
          <p class="hint"><?= icon('calendar') ?> Agendamentos com até <?= (int) $cfg['dias_antecedencia'] ?> dias de antecedência.</p>
        </fieldset>

        <!-- Etapa 4 -->
        <fieldset class="step" data-step="4">
          <legend class="step-title"><span>04</span> Escolha o horário</legend>
          <p class="muted" data-slot-context></p>
          <div class="slot-grid" data-slots aria-live="polite"></div>
          <div class="slot-legend"><span><i class="dot dot-free"></i> Disponível</span><span><i class="dot dot-busy"></i> Ocupado</span></div>
        </fieldset>

        <!-- Etapa 5 -->
        <fieldset class="step" data-step="5">
          <legend class="step-title"><span>05</span> Seus dados</legend>
          <div class="form">
            <div class="field">
              <label for="b-nome">Nome completo *</label>
              <input id="b-nome" name="nome" required minlength="3" maxlength="120" autocomplete="name" placeholder="Como devemos te chamar?">
              <span class="field-error" data-error-for="nome"></span>
            </div>
            <div class="form-row">
              <div class="field">
                <label for="b-tel">Telefone / WhatsApp *</label>
                <input id="b-tel" name="telefone" required data-mask="phone" inputmode="tel" autocomplete="tel" placeholder="(11) 98765-4321">
                <span class="field-error" data-error-for="telefone"></span>
              </div>
              <div class="field">
                <label for="b-email">E-mail *</label>
                <input id="b-email" name="email" type="email" required maxlength="160" autocomplete="email" placeholder="voce@email.com">
                <span class="field-error" data-error-for="email"></span>
              </div>
            </div>
            <div class="field">
              <label for="b-obs">Observação <span class="muted">(opcional)</span></label>
              <textarea id="b-obs" name="observacao" rows="3" maxlength="500" placeholder="Ex.: quero manter o comprimento em cima, alergia a algum produto..."></textarea>
              <span class="field-error" data-error-for="observacao"></span>
            </div>
          </div>
        </fieldset>

        <!-- Etapa 6 -->
        <fieldset class="step" data-step="6">
          <legend class="step-title"><span>06</span> Confira e confirme</legend>
          <dl class="summary-list" data-summary-final>
            <div><dt>Serviço</dt><dd data-sum="servico">—</dd></div>
            <div><dt>Barbeiro</dt><dd data-sum="barbeiro">—</dd></div>
            <div><dt>Data</dt><dd data-sum="data">—</dd></div>
            <div><dt>Horário</dt><dd data-sum="hora">—</dd></div>
            <div><dt>Cliente</dt><dd data-sum="cliente">—</dd></div>
            <div class="total"><dt>Valor</dt><dd data-sum="valor">—</dd></div>
          </dl>
          <p class="hint"><?= icon('shield') ?> Pagamento no local. Cancelamento gratuito pelo link que você receberá após confirmar.</p>
          <div class="alert alert-error inline" data-booking-error hidden></div>
        </fieldset>

        <div class="step-actions">
          <button type="button" class="btn btn-ghost" data-prev hidden><?= icon('arrow-left') ?> Voltar</button>
          <button type="button" class="btn btn-gold" data-next disabled>Continuar <?= icon('arrow-right') ?></button>
          <button type="submit" class="btn btn-gold btn-lg" data-submit hidden>Confirmar agendamento <?= icon('check') ?></button>
        </div>
      </form>

      <aside class="booking-summary" aria-label="Resumo do agendamento">
        <h2>Resumo</h2>
        <dl class="summary-list">
          <div><dt>Serviço</dt><dd data-sum="servico">—</dd></div>
          <div><dt>Barbeiro</dt><dd data-sum="barbeiro">—</dd></div>
          <div><dt>Data</dt><dd data-sum="data">—</dd></div>
          <div><dt>Horário</dt><dd data-sum="hora">—</dd></div>
          <div class="total"><dt>Total</dt><dd data-sum="valor">—</dd></div>
        </dl>
        <div class="summary-help">
          <p>Dúvidas? Fale com a gente.</p>
          <a href="<?= e(whatsapp_link('Olá! Tenho uma dúvida sobre agendamento.')) ?>" target="_blank" rel="noopener" class="link-gold"><?= icon('whatsapp') ?> <?= e(format_phone($cfg['whatsapp'])) ?></a>
        </div>
      </aside>
    </div>

    <!-- Sucesso -->
    <div class="booking-success" data-success hidden>
      <div class="success-icon"><?= icon('check') ?></div>
      <h2 class="display">Agendamento realizado com sucesso!</h2>
      <p class="muted">Protocolo <strong data-ok="protocolo"></strong>. Confirme pelo WhatsApp para garantir seu horário.</p>
      <dl class="summary-list">
        <div><dt>Serviço</dt><dd data-ok="servico"></dd></div>
        <div><dt>Barbeiro</dt><dd data-ok="barbeiro"></dd></div>
        <div><dt>Data</dt><dd data-ok="data_extenso"></dd></div>
        <div><dt>Horário</dt><dd data-ok="hora"></dd></div>
        <div class="total"><dt>Valor</dt><dd data-ok="valor"></dd></div>
      </dl>
      <div class="btn-col">
        <a href="#" class="btn btn-whatsapp btn-lg" data-ok-whatsapp target="_blank" rel="noopener"><?= icon('whatsapp') ?> Confirmar pelo WhatsApp</a>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-ghost">Voltar ao início</a>
      </div>
      <p class="small muted">Precisa desmarcar? <a href="#" class="link-gold" data-ok-cancel>Cancelar agendamento</a></p>
    </div>
  </div>

  <div class="mobile-summary" data-mobile-summary>
    <div><small data-sum="servico">Escolha um serviço</small><strong data-sum="valor">—</strong></div>
  </div>
</section>

<script>
window.BOOKING = <?= json_encode([
    'servicos'  => $servicosJs,
    'barbeiros' => $barbeirosJs,
    'preServico'  => $preServico,
    'preBarbeiro' => $preBarbeiro,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
