<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

if (is_post()) {
    admin_post_guard('admin/promocoes.php');
    $acao = input('acao');
    $id = input_int('id');

    if ($acao === 'salvar') {
        $nome = input('nome');
        $descricao = input('descricao');
        $servicoId = input_int('servico_id') ?: null;
        $antigo = parse_money(input('preco_antigo'));
        $promo = parse_money(input('preco_promocional'));
        $ini = input('data_inicio');
        $fim = input('data_fim');
        $ativo = !empty($_POST['ativo']) ? 1 : 0;
        $erros = [];
        if (str_len($nome) < 3 || str_len($nome) > 120) $erros[] = 'Informe o nome da promoção.';
        if (!is_numeric($antigo) || (float) $antigo <= 0) $erros[] = 'Preço antigo inválido.';
        if (!is_numeric($promo) || (float) $promo <= 0) $erros[] = 'Preço promocional inválido.';
        if (is_numeric($antigo) && is_numeric($promo) && (float) $promo >= (float) $antigo) $erros[] = 'O preço promocional deve ser menor que o preço antigo.';
        if (!valid_date($ini) || !valid_date($fim)) $erros[] = 'Informe as datas de início e fim.';
        elseif ($fim < $ini) $erros[] = 'A data final deve ser igual ou posterior à inicial.';
        if ($servicoId) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM servicos WHERE id = ?');
            $st->execute([$servicoId]);
            if (!(int) $st->fetchColumn()) $erros[] = 'Serviço vinculado inválido.';
        }
        try {
            $img = upload_image('imagem', 'promocoes');
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
            $img = null;
        }
        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect('admin/promocoes.php');
        }
        $campos = [$nome, $descricao, $servicoId, (float) $antigo, (float) $promo, $ini, $fim, $ativo];
        if ($id) {
            $old = $pdo->prepare('SELECT imagem FROM promocoes WHERE id = ?');
            $old->execute([$id]);
            $imgAntiga = $old->fetchColumn();
            $pdo->prepare('UPDATE promocoes SET nome = ?, descricao = ?, servico_id = ?, preco_antigo = ?, preco_promocional = ?, data_inicio = ?, data_fim = ?, ativo = ?' . ($img ? ', imagem = ?' : '') . ' WHERE id = ?')
                ->execute(array_merge($campos, $img ? [$img] : [], [$id]));
            if ($img) delete_upload($imgAntiga);
            flash('success', 'Promoção atualizada.');
        } else {
            $pdo->prepare('INSERT INTO promocoes (nome, descricao, servico_id, preco_antigo, preco_promocional, data_inicio, data_fim, ativo, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($campos, [$img]));
            flash('success', 'Promoção criada.');
        }
    } elseif ($acao === 'toggle') {
        $pdo->prepare('UPDATE promocoes SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        flash('success', 'Status da promoção atualizado.');
    } elseif ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT imagem FROM promocoes WHERE id = ?');
        $st->execute([$id]);
        delete_upload($st->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM promocoes WHERE id = ?')->execute([$id]);
        flash('success', 'Promoção excluída.');
    }
    redirect('admin/promocoes.php');
}

$promocoes = $pdo->query('SELECT p.*, s.nome servico_nome FROM promocoes p LEFT JOIN servicos s ON s.id = p.servico_id ORDER BY p.data_fim DESC')->fetchAll();
$servicos = $pdo->query('SELECT id, nome, preco FROM servicos ORDER BY nome')->fetchAll();
$hoje = date('Y-m-d');

admin_header('Promoções', 'promocoes', [
    'subtitle' => 'A promoção vigente aparece em destaque no site e o preço é aplicado automaticamente no agendamento do serviço vinculado.',
    'actions' => '<button type="button" class="btn btn-gold btn-sm" data-crud-new="modal-promo" data-defaults=\'' . e(json_encode(['ativo' => 1, 'data_inicio' => $hoje, 'data_fim' => date('Y-m-d', strtotime('+30 days'))])) . '\'>' . icon('plus') . '<span class="hide-sm">Nova promoção</span></button>',
]);
?>
<div class="entity-grid wide">
  <?php foreach ($promocoes as $p):
      $vigente = $p['ativo'] && $hoje >= $p['data_inicio'] && $hoje <= $p['data_fim'];
      $status = !$p['ativo'] ? ['Inativa', 'badge-cancelado'] : ($hoje > $p['data_fim'] ? ['Encerrada', 'badge-neutral'] : ($hoje < $p['data_inicio'] ? ['Agendada', 'badge-confirmado'] : ['No ar', 'badge-concluido']));
      $desc = round((1 - $p['preco_promocional'] / $p['preco_antigo']) * 100);
      $rec = ['id' => (int) $p['id'], 'nome' => $p['nome'], 'descricao' => $p['descricao'], 'servico_id' => (string) ($p['servico_id'] ?? ''),
          'preco_antigo' => number_format((float) $p['preco_antigo'], 2, ',', ''), 'preco_promocional' => number_format((float) $p['preco_promocional'], 2, ',', ''),
          'data_inicio' => $p['data_inicio'], 'data_fim' => $p['data_fim'], 'ativo' => (int) $p['ativo'], '_imagem_url' => img_src($p['imagem'])]; ?>
    <article class="entity-card promo-item <?= $vigente ? 'is-live' : '' ?>">
      <div class="entity-media"><img src="<?= e(img_src($p['imagem'])) ?>" alt="" loading="lazy"><span class="badge <?= $status[1] ?>"><?= $status[0] ?></span><span class="discount">-<?= $desc ?>%</span></div>
      <div class="entity-body">
        <h3><?= e($p['nome']) ?></h3>
        <p class="muted small clamp-2"><?= e($p['descricao']) ?></p>
        <div class="promo-prices"><del><?= e(money($p['preco_antigo'])) ?></del><strong><?= e(money($p['preco_promocional'])) ?></strong></div>
        <p class="small muted"><?= icon('calendar') ?> <?= e(date_br($p['data_inicio'])) ?> a <?= e(date_br($p['data_fim'])) ?><br><?= icon('tag') ?> <?= $p['servico_nome'] ? 'Aplicada em: ' . e($p['servico_nome']) : 'Sem serviço vinculado (apenas divulgação)' ?></p>
      </div>
      <div class="entity-actions">
        <button type="button" class="btn btn-ghost btn-sm" data-crud-edit="modal-promo" data-record="<?= e(json_encode($rec, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('edit') ?> Editar</button>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-ghost btn-sm"><?= icon($p['ativo'] ? 'eye-off' : 'eye') ?> <?= $p['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
        <form method="post" data-confirm="Excluir esta promoção?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$promocoes): ?><p class="empty">Nenhuma promoção cadastrada.</p><?php endif; ?>
</div>

<div class="modal" id="modal-promo" role="dialog" aria-modal="true" aria-labelledby="mp-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-lg">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="mp-title" data-crud-title data-title-new="Nova promoção" data-title-edit="Editar promoção">Nova promoção</h3>
    <form method="post" enctype="multipart/form-data" class="form" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="">
      <?= image_field('imagem', 'Imagem') ?>
      <div class="form-row">
        <div class="field"><label for="p-nome">Nome *</label><input id="p-nome" name="nome" required minlength="3" maxlength="120"></div>
        <div class="field"><label for="p-serv">Serviço vinculado</label>
          <select id="p-serv" name="servico_id"><option value="">— Nenhum —</option><?php foreach ($servicos as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nome']) ?> (<?= e(money($s['preco'])) ?>)</option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="field"><label for="p-desc">Descrição</label><textarea id="p-desc" name="descricao" rows="3" maxlength="1000"></textarea></div>
      <div class="form-row">
        <div class="field"><label for="p-old">Preço antigo (R$) *</label><input id="p-old" name="preco_antigo" required inputmode="decimal" placeholder="60,00"></div>
        <div class="field"><label for="p-new">Preço promocional (R$) *</label><input id="p-new" name="preco_promocional" required inputmode="decimal" placeholder="49,90"></div>
      </div>
      <div class="form-row">
        <div class="field"><label for="p-ini">Data inicial *</label><input id="p-ini" type="date" name="data_inicio" required></div>
        <div class="field"><label for="p-fim">Data final *</label><input id="p-fim" type="date" name="data_fim" required></div>
      </div>
      <label class="switch"><input type="checkbox" name="ativo" value="1" checked><span></span> Ativa</label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Salvar</button></div>
    </form>
  </div>
</div>
<?php admin_footer();
