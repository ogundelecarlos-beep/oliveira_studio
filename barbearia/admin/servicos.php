<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

if (is_post()) {
    admin_post_guard('admin/servicos.php');
    $acao = input('acao');
    $id = input_int('id');

    if ($acao === 'salvar') {
        $nome = input('nome');
        $descricao = input('descricao');
        $preco = parse_money(input('preco'));
        $duracao = input_int('duracao');
        $ativo = !empty($_POST['ativo']) ? 1 : 0;
        $destaque = !empty($_POST['destaque']) ? 1 : 0;
        $ordem = input_int('ordem');
        $erros = [];
        if (str_len($nome) < 3 || str_len($nome) > 120) $erros[] = 'Informe o nome do serviço.';
        if (!is_numeric($preco) || (float) $preco < 0 || (float) $preco > 99999) $erros[] = 'Preço inválido.';
        if ($duracao < 5 || $duracao > 480) $erros[] = 'A duração deve estar entre 5 e 480 minutos.';
        if (str_len($descricao) > 1000) $erros[] = 'Descrição muito longa (máx. 1000).';
        try {
            $foto = upload_image('foto', 'servicos');
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
            $foto = null;
        }
        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect('admin/servicos.php');
        }
        if ($id) {
            $old = $pdo->prepare('SELECT foto FROM servicos WHERE id = ?');
            $old->execute([$id]);
            $fotoAntiga = $old->fetchColumn();
            $pdo->prepare('UPDATE servicos SET nome = ?, descricao = ?, preco = ?, duracao = ?, ativo = ?, destaque = ?, ordem = ?' . ($foto ? ', foto = ?' : '') . ' WHERE id = ?')
                ->execute(array_merge([$nome, $descricao, (float) $preco, $duracao, $ativo, $destaque, $ordem], $foto ? [$foto] : [], [$id]));
            if ($foto) delete_upload($fotoAntiga);
            flash('success', 'Serviço atualizado. Agendamentos já feitos mantêm o valor e a duração originais.');
        } else {
            $pdo->prepare('INSERT INTO servicos (nome, descricao, preco, duracao, foto, ativo, destaque, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$nome, $descricao, (float) $preco, $duracao, $foto, $ativo, $destaque, $ordem]);
            flash('success', 'Serviço criado com sucesso.');
        }
    } elseif ($acao === 'toggle') {
        $pdo->prepare('UPDATE servicos SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        flash('success', 'Status do serviço atualizado.');
    } elseif ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM agendamentos WHERE servico_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            flash('error', 'Este serviço já foi utilizado em agendamentos e não pode ser excluído. Desative-o para ocultá-lo do site.');
        } else {
            $st = $pdo->prepare('SELECT foto FROM servicos WHERE id = ?');
            $st->execute([$id]);
            delete_upload($st->fetchColumn() ?: null);
            $pdo->prepare('DELETE FROM servicos WHERE id = ?')->execute([$id]);
            flash('success', 'Serviço excluído.');
        }
    }
    redirect('admin/servicos.php');
}

$servicos = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM agendamentos a WHERE a.servico_id = s.id AND a.status = 'concluido') realizados
                         FROM servicos s ORDER BY s.ativo DESC, s.ordem, s.nome")->fetchAll();

admin_header('Serviços', 'servicos', [
    'subtitle' => count($servicos) . ' serviço(s) cadastrados',
    'actions' => '<button type="button" class="btn btn-gold btn-sm" data-crud-new="modal-servico" data-defaults=\'{"ativo":1,"duracao":40,"ordem":' . (count($servicos) + 1) . '}\'>' . icon('plus') . '<span class="hide-sm">Novo serviço</span></button>',
]);
?>
<section class="card">
  <div class="toolbar"><div class="search"><?= icon('search') ?><input type="search" placeholder="Filtrar serviços" data-table-search="#tabela-servicos" aria-label="Filtrar serviços"></div></div>
  <div class="table-wrap">
    <table class="table table-responsive" id="tabela-servicos">
      <thead><tr><th>Serviço</th><th>Preço</th><th>Duração</th><th class="t-center">Realizados</th><th>Status</th><th class="t-right">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($servicos as $s):
          $rec = ['id' => (int) $s['id'], 'nome' => $s['nome'], 'descricao' => $s['descricao'], 'preco' => number_format((float) $s['preco'], 2, ',', ''), 'duracao' => (int) $s['duracao'], 'ativo' => (int) $s['ativo'], 'destaque' => (int) $s['destaque'], 'ordem' => (int) $s['ordem'], '_foto_url' => img_src($s['foto'])]; ?>
        <tr data-row class="<?= $s['ativo'] ? '' : 'is-inactive' ?>">
          <td data-label="Serviço"><div class="cell-media"><img src="<?= e(img_src($s['foto'])) ?>" alt="" loading="lazy"><div><strong><?= e($s['nome']) ?></strong><?php if ($s['destaque']): ?> <span class="badge badge-gold">Home</span><?php endif; ?><small class="muted d-block clamp-1"><?= e($s['descricao']) ?></small></div></div></td>
          <td data-label="Preço"><strong><?= e(money($s['preco'])) ?></strong></td>
          <td data-label="Duração"><?= e(duracao_label((int) $s['duracao'])) ?></td>
          <td data-label="Realizados" class="t-center"><span class="pill"><?= (int) $s['realizados'] ?></span></td>
          <td data-label="Status"><span class="badge <?= $s['ativo'] ? 'badge-concluido' : 'badge-cancelado' ?>"><?= $s['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
          <td class="t-right actions-cell">
            <button type="button" class="icon-btn" data-crud-edit="modal-servico" data-record="<?= e(json_encode($rec, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Editar" title="Editar"><?= icon('edit') ?></button>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="icon-btn" aria-label="<?= $s['ativo'] ? 'Desativar' : 'Ativar' ?>" title="<?= $s['ativo'] ? 'Desativar' : 'Ativar' ?>"><?= icon($s['ativo'] ? 'eye-off' : 'eye') ?></button></form>
            <form method="post" data-confirm="Excluir o serviço &quot;<?= e($s['nome']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <tr data-search-empty hidden><td colspan="6" class="empty">Nenhum serviço corresponde à busca.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="modal-servico" role="dialog" aria-modal="true" aria-labelledby="ms-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-lg">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="ms-title" data-crud-title data-title-new="Novo serviço" data-title-edit="Editar serviço">Novo serviço</h3>
    <form method="post" enctype="multipart/form-data" class="form" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="">
      <?= image_field('foto', 'Foto do serviço') ?>
      <div class="field"><label for="s-nome">Nome *</label><input id="s-nome" name="nome" required minlength="3" maxlength="120"></div>
      <div class="form-row three">
        <div class="field"><label for="s-preco">Preço (R$) *</label><input id="s-preco" name="preco" required inputmode="decimal" placeholder="35,00"></div>
        <div class="field"><label for="s-dur">Duração (min) *</label><input id="s-dur" type="number" name="duracao" required min="5" max="480" step="5"></div>
        <div class="field"><label for="s-ordem">Ordem</label><input id="s-ordem" type="number" name="ordem" min="0" max="999"></div>
      </div>
      <div class="field"><label for="s-desc">Descrição</label><textarea id="s-desc" name="descricao" rows="3" maxlength="1000"></textarea></div>
      <div class="switch-row">
        <label class="switch"><input type="checkbox" name="ativo" value="1" checked><span></span> Ativo</label>
        <label class="switch"><input type="checkbox" name="destaque" value="1"><span></span> Destaque na página inicial</label>
      </div>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Salvar</button></div>
    </form>
  </div>
</div>
<?php admin_footer();
