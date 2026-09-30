<?php
require __DIR__ . '/includes/auth.php';
$u = require_login();

if (is_post()) {
    admin_post_guard('admin/perfil.php');
    $nome = input('nome');
    $atual = (string) ($_POST['senha_atual'] ?? '');
    $nova = (string) ($_POST['nova_senha'] ?? '');
    $conf = (string) ($_POST['confirmar'] ?? '');

    if (str_len($nome) < 3) {
        flash('error', 'Informe seu nome.');
        redirect('admin/perfil.php');
    }
    db()->prepare('UPDATE usuarios SET nome = ? WHERE id = ?')->execute([$nome, $u['id']]);

    if ($nova !== '') {
        $st = db()->prepare('SELECT senha FROM usuarios WHERE id = ?');
        $st->execute([$u['id']]);
        if (!password_verify($atual, (string) $st->fetchColumn())) {
            flash('error', 'Senha atual incorreta.');
            redirect('admin/perfil.php');
        }
        if ($msg = senha_fraca($nova)) {
            flash('error', $msg);
            redirect('admin/perfil.php');
        }
        if ($nova !== $conf) {
            flash('error', 'A confirmação não confere com a nova senha.');
            redirect('admin/perfil.php');
        }
        db()->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
    }
    flash('success', 'Perfil atualizado com sucesso.');
    redirect('admin/perfil.php');
}

admin_header('Meu perfil', '');
?>
<div class="card narrow">
  <form method="post" class="form" data-validate novalidate>
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="field"><label for="nome">Nome</label><input id="nome" name="nome" value="<?= e($u['nome']) ?>" required minlength="3"></div>
      <div class="field"><label>E-mail</label><input value="<?= e($u['email']) ?>" disabled></div>
    </div>
    <h3 class="card-subtitle">Alterar senha</h3>
    <p class="muted small">Deixe em branco para manter a senha atual.</p>
    <div class="field"><label for="senha_atual">Senha atual</label><input id="senha_atual" type="password" name="senha_atual" autocomplete="current-password"></div>
    <div class="form-row">
      <div class="field"><label for="nova_senha">Nova senha</label><input id="nova_senha" type="password" name="nova_senha" minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="confirmar">Confirmar nova senha</label><input id="confirmar" type="password" name="confirmar" minlength="8" autocomplete="new-password"></div>
    </div>
    <div><button class="btn btn-gold">Salvar alterações</button></div>
  </form>
</div>
<?php admin_footer();
