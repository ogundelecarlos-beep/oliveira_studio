<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

if (is_post()) {
    admin_post_guard('admin/barbeiros.php');
    $acao = input('acao');
    $id = input_int('id');

    if ($acao === 'salvar') {
        $nome = input('nome');
        $descricao = input('descricao');
        $esp = implode(', ', especialidades_list(input('especialidades')));
        $ativo = !empty($_POST['ativo']) ? 1 : 0;
        $ordem = input_int('ordem');
        $erros = [];
        if (str_len($nome) < 3 || str_len($nome) > 120) $erros[] = 'Informe o nome do barbeiro (3 a 120 caracteres).';
        if (str_len($descricao) > 1000) $erros[] = 'A descrição pode ter no máximo 1000 caracteres.';
        if (str_len($esp) > 255) $erros[] = 'Especialidades: máximo de 255 caracteres.';
        try {
            $foto = upload_image('foto', 'barbeiros');
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
            $foto = null;
        }
        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect('admin/barbeiros.php');
        }
        if ($id) {
            $old = $pdo->prepare('SELECT foto FROM barbeiros WHERE id = ?');
            $old->execute([$id]);
            $fotoAntiga = $old->fetchColumn();
            $pdo->prepare('UPDATE barbeiros SET nome = ?, descricao = ?, especialidades = ?, ativo = ?, ordem = ?' . ($foto ? ', foto = ?' : '') . ' WHERE id = ?')
                ->execute(array_merge([$nome, $descricao, $esp, $ativo, $ordem], $foto ? [$foto] : [], [$id]));
            if ($foto) delete_upload($fotoAntiga);
            flash('success', 'Barbeiro atualizado com sucesso.');
        } else {
            $pdo->prepare('INSERT INTO barbeiros (nome, descricao, especialidades, foto, ativo, ordem) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$nome, $descricao, $esp, $foto, $ativo, $ordem]);
            flash('success', 'Barbeiro cadastrado com sucesso.');
        }
    } elseif ($acao === 'toggle') {
        $pdo->prepare('UPDATE barbeiros SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        $st = $pdo->prepare('SELECT ativo FROM barbeiros WHERE id = ?');
        $st->execute([$id]);
        flash('success', (int) $st->fetchColumn() ? 'Barbeiro ativado: já aparece no site e no agendamento.' : 'Barbeiro desativado: não aparece mais para novos agendamentos.');
    } elseif ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM agendamentos WHERE barbeiro_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            flash('error', 'Este barbeiro possui agendamentos no histórico e não pode ser excluído. Desative-o para ocultá-lo.');
        } else {
            $st = $pdo->prepare('SELECT foto FROM barbeiros WHERE id = ?');
            $st->execute([$id]);
            delete_upload($st->fetchColumn() ?: null);
            $pdo->prepare('DELETE FROM barbeiros WHERE id = ?')->execute([$id]);
            flash('success', 'Barbeiro excluído.');
        }
    }
    redirect('admin/barbeiros.php');
}

$barbeiros = $pdo->query("SELECT b.*,
    (SELECT COUNT(*) FROM agendamentos a WHERE a.barbeiro_id = b.id AND a.status = 'concluido') atendimentos,
    (SELECT COUNT(*) FROM agendamentos a WHERE a.barbeiro_id = b.id AND a.status IN ('pendente','confirmado') AND a.data >= CURDATE()) futuros
    FROM barbeiros b ORDER BY b.ativo DESC, b.ordem, b.nome")->fetchAll();
$notas = notas_barbeiros();

admin_header('Barbeiros', 'barbeiros', [
    'subtitle' => count($barbeiros) . ' profissional(is)',
    'actions' => '<button type="button" class="btn btn-gold btn-sm" data-crud-new="modal-barbeiro" data-defaults=\'{"ativo":1,"ordem":' . (count($barbeiros) + 1) . '}\'>' . icon('plus') . '<span class="hide-sm">Novo barbeiro</span></button>',
]);
?>
<div class="entity-grid">
  <?php foreach ($barbeiros as $b):
      $n = $notas[(int) $b['id']] ?? null;
      $rec = ['id' => (int) $b['id'], 'nome' => $b['nome'], 'descricao' => $b['descricao'], 'especialidades' => $b['especialidades'], 'ativo' => (int) $b['ativo'], 'ordem' => (int) $b['ordem'], '_foto_url' => img_src($b['foto'], 'img/avatar.svg')]; ?>
    <article class="entity-card <?= $b['ativo'] ? '' : 'is-inactive' ?>">
      <div class="entity-media"><img src="<?= e(img_src($b['foto'], 'img/avatar.svg')) ?>" alt="" class="avatar-img" loading="lazy"><span class="badge <?= $b['ativo'] ? 'badge-concluido' : 'badge-cancelado' ?>"><?= $b['ativo'] ? 'Ativo' : 'Inativo' ?></span></div>
      <div class="entity-body">
        <h3><?= e($b['nome']) ?></h3>
        <?php if ($n): ?><div class="rating-inline sm"><?= stars($n['media']) ?> <?= number_format($n['media'], 1, ',', '') ?> (<?= $n['total'] ?>)</div><?php endif; ?>
        <ul class="tags"><?php foreach (especialidades_list($b['especialidades']) as $esp): ?><li><?= e($esp) ?></li><?php endforeach; ?></ul>
        <p class="muted small clamp-3"><?= e($b['descricao']) ?></p>
        <dl class="entity-stats"><div><dt>Atendimentos</dt><dd><?= (int) $b['atendimentos'] ?></dd></div><div><dt>Agendados</dt><dd><?= (int) $b['futuros'] ?></dd></div></dl>
      </div>
      <div class="entity-actions">
        <button type="button" class="btn btn-ghost btn-sm" data-crud-edit="modal-barbeiro" data-record="<?= e(json_encode($rec, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('edit') ?> Editar</button>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn-ghost btn-sm"><?= icon($b['ativo'] ? 'eye-off' : 'eye') ?> <?= $b['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
        <form method="post" data-confirm="Excluir <?= e($b['nome']) ?>? Esta ação não pode ser desfeita."><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$barbeiros): ?><p class="empty">Nenhum barbeiro cadastrado.</p><?php endif; ?>
</div>

<div class="modal" id="modal-barbeiro" role="dialog" aria-modal="true" aria-labelledby="mb-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-lg">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="mb-title" data-crud-title data-title-new="Novo barbeiro" data-title-edit="Editar barbeiro">Novo barbeiro</h3>
    <form method="post" enctype="multipart/form-data" class="form" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="">
      <?= image_field('foto', 'Foto', null, 'img/avatar.svg') ?>
      <div class="form-row">
        <div class="field"><label for="b-nome">Nome *</label><input id="b-nome" name="nome" required minlength="3" maxlength="120"></div>
        <div class="field"><label for="b-ordem">Ordem de exibição</label><input id="b-ordem" type="number" name="ordem" min="0" max="999" value="0"></div>
      </div>
      <div class="field"><label for="b-esp">Especialidades <span class="muted">(separe por vírgula)</span></label><input id="b-esp" name="especialidades" maxlength="255" placeholder="Degradê, Barba, Freestyle"></div>
      <div class="field"><label for="b-desc">Descrição</label><textarea id="b-desc" name="descricao" rows="4" maxlength="1000"></textarea></div>
      <label class="switch"><input type="checkbox" name="ativo" value="1" checked><span></span> Ativo (aparece no site e no agendamento)</label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Salvar</button></div>
    </form>
  </div>
</div>
<?php admin_footer();
