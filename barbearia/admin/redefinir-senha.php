<?php
require __DIR__ . '/includes/auth.php';

$token = input('token');
$registro = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db()->prepare('SELECT r.id, r.usuario_id FROM recuperacao_senha r JOIN usuarios u ON u.id = r.usuario_id
                         WHERE r.token_hash = ? AND r.usado = 0 AND r.expira_em > NOW() AND u.ativo = 1');
    $st->execute([hash('sha256', $token)]);
    $registro = $st->fetch() ?: null;
}

$erro = '';
if (is_post() && $registro) {
    $senha = (string) ($_POST['senha'] ?? '');
    $conf = (string) ($_POST['confirmar'] ?? '');
    if (!csrf_valid()) {
        $erro = 'Sessão expirada. Atualize a página.';
    } elseif ($msg = senha_fraca($senha)) {
        $erro = $msg;
    } elseif ($senha !== $conf) {
        $erro = 'As senhas não conferem.';
    } else {
        db()->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $registro['usuario_id']]);
        db()->prepare('UPDATE recuperacao_senha SET usado = 1 WHERE usuario_id = ?')->execute([$registro['usuario_id']]);
        flash('success', 'Senha alterada com sucesso! Entre com sua nova senha.');
        redirect('admin/login.php');
    }
}

auth_page_start('Nova senha');
?>
  <h1>Criar nova senha</h1>
  <?php if (!$registro): ?>
    <div class="alert alert-error inline">Este link é inválido ou expirou. Solicite uma nova recuperação.</div>
    <a class="btn btn-gold btn-block" href="<?= e(url('admin/recuperar-senha.php')) ?>">Solicitar novo link</a>
  <?php else: ?>
    <p class="muted">Use no mínimo 8 caracteres, com letras e números.</p>
    <?php if ($erro): ?><div class="alert alert-error inline"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" class="form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field"><label for="senha">Nova senha</label><input id="senha" type="password" name="senha" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="confirmar">Confirmar senha</label><input id="confirmar" type="password" name="confirmar" required minlength="8" autocomplete="new-password"></div>
      <button type="submit" class="btn btn-gold btn-block btn-lg">Salvar nova senha</button>
    </form>
  <?php endif; ?>
<?php auth_page_end();
