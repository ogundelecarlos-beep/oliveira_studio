<?php
require __DIR__ . '/includes/auth.php';
$me = require_admin();
$pdo = db();

if (is_post()) {
    admin_post_guard('admin/usuarios.php');
    $acao = input('acao');
    $id = input_int('id');

    if ($acao === 'salvar') {
        $nome = input('nome');
        $email = strtolower(input('email'));
        $tipo = input('tipo') === 'admin' ? 'admin' : 'funcionario';
        $ativo = !empty($_POST['ativo']) ? 1 : 0;
        $senha = (string) ($_POST['senha'] ?? '');
        $erros = [];
        if (str_len($nome) < 3) $erros[] = 'Informe o nome.';
        if (!valid_email($email)) $erros[] = 'E-mail inválido.';
        $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $dup->execute([$email, $id]);
        if ($dup->fetch()) $erros[] = 'Já existe um usuário com este e-mail.';
        if (!$id || $senha !== '') {
            if ($msg = senha_fraca($senha)) $erros[] = $msg;
        }
        if ($id === (int) $me['id'] && ($tipo !== 'admin' || !$ativo)) $erros[] = 'Você não pode remover seu próprio acesso de administrador.';
        if ($erros) {
            flash('error', implode(' ', $erros));
            redirect('admin/usuarios.php');
        }
        if ($id) {
            $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, tipo = ?, ativo = ? WHERE id = ?')->execute([$nome, $email, $tipo, $ativo, $id]);
            if ($senha !== '') {
                $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
            }
            flash('success', 'Usuário atualizado.');
        } else {
            $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, ?, ?)')->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $tipo, $ativo]);
            flash('success', 'Usuário criado. Compartilhe a senha inicial com segurança.');
        }
    } elseif ($acao === 'excluir') {
        if ($id === (int) $me['id']) {
            flash('error', 'Você não pode excluir o próprio usuário.');
        } else {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            flash('success', 'Usuário excluído.');
        }
    }
    redirect('admin/usuarios.php');
}

$usuarios = $pdo->query('SELECT id, nome, email, tipo, ativo, criado_em FROM usuarios ORDER BY tipo, nome')->fetchAll();
admin_header('Usuários', 'usuarios', [
    'subtitle' => 'Quem pode acessar o painel',
    'actions' => '<button type="button" class="btn btn-gold btn-sm" data-crud-new="modal-usuario" data-defaults=\'{"ativo":1,"tipo":"funcionario"}\'>' . icon('plus') . '<span class="hide-sm">Novo usuário</span></button>',
]);
?>
<section class="card">
  <div class="table-wrap">
    <table class="table table-responsive">
      <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Criado em</th><th class="t-right">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($usuarios as $x):
          $rec = ['id' => (int) $x['id'], 'nome' => $x['nome'], 'email' => $x['email'], 'tipo' => $x['tipo'], 'ativo' => (int) $x['ativo']]; ?>
        <tr>
          <td data-label="Nome"><div class="cell-user"><span class="avatar sm"><?= e(initials($x['nome'])) ?></span><strong><?= e($x['nome']) ?></strong><?= (int) $x['id'] === (int) $me['id'] ? ' <small class="muted">(você)</small>' : '' ?></div></td>
          <td data-label="E-mail"><?= e($x['email']) ?></td>
          <td data-label="Perfil"><span class="badge <?= $x['tipo'] === 'admin' ? 'badge-gold' : 'badge-neutral' ?>"><?= $x['tipo'] === 'admin' ? 'Administrador' : 'Funcionário' ?></span></td>
          <td data-label="Status"><span class="badge <?= $x['ativo'] ? 'badge-concluido' : 'badge-cancelado' ?>"><?= $x['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
          <td data-label="Criado em"><?= e(date_br($x['criado_em'])) ?></td>
          <td class="t-right actions-cell">
            <button type="button" class="icon-btn" data-crud-edit="modal-usuario" data-record="<?= e(json_encode($rec, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Editar" title="Editar"><?= icon('edit') ?></button>
            <?php if ((int) $x['id'] !== (int) $me['id']): ?>
              <form method="post" data-confirm="Excluir o usuário <?= e($x['nome']) ?>?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<p class="muted small">Funcionários acessam agenda, clientes, serviços, barbeiros, avaliações, galeria e mensagens. Somente administradores gerenciam usuários e configurações.</p>

<div class="modal" id="modal-usuario" role="dialog" aria-modal="true" aria-labelledby="mus-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="mus-title" data-crud-title data-title-new="Novo usuário" data-title-edit="Editar usuário">Novo usuário</h3>
    <form method="post" class="form" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="">
      <div class="field"><label for="u-nome">Nome</label><input id="u-nome" name="nome" required minlength="3" maxlength="120"></div>
      <div class="field"><label for="u-email">E-mail</label><input id="u-email" type="email" name="email" required maxlength="160" autocomplete="off"></div>
      <div class="form-row">
        <div class="field"><label for="u-tipo">Perfil</label><select id="u-tipo" name="tipo"><option value="funcionario">Funcionário</option><option value="admin">Administrador</option></select></div>
        <div class="field"><label for="u-senha">Senha <span class="muted" data-show-edit hidden>(vazio mantém)</span></label><input id="u-senha" type="password" name="senha" minlength="8" autocomplete="new-password" placeholder="Mín. 8, letras e números"></div>
      </div>
      <label class="switch"><input type="checkbox" name="ativo" value="1" checked><span></span> Acesso ativo</label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Salvar</button></div>
    </form>
  </div>
</div>
<?php admin_footer();
