<?php
require __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect('admin/dashboard.php');
}

$email = '';
$erro = '';
if (is_post()) {
    $email = strtolower(input('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    $ip = client_ip();

    if (!csrf_valid()) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif (login_bloqueado($ip)) {
        $erro = 'Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.';
    } elseif (!valid_email($email) || $senha === '') {
        $erro = 'Informe e-mail e senha válidos.';
    } else {
        $st = db()->prepare('SELECT * FROM usuarios WHERE email = ? AND ativo = 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($senha, $u['senha'])) {
            if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
            }
            db()->prepare('DELETE FROM login_tentativas WHERE ip = ?')->execute([$ip]);
            login_user($u);
            flash('success', 'Bem-vindo(a), ' . explode(' ', $u['nome'])[0] . '!');
            redirect('admin/dashboard.php');
        }
        registrar_falha_login($ip, $email);
        $erro = 'E-mail ou senha incorretos.';
    }
}

auth_page_start('Entrar');
?>
  <h1>Acesso ao painel</h1>
  <p class="muted">Entre com suas credenciais para gerenciar a barbearia.</p>
  <?= render_flashes() ?>
  <?php if ($erro): ?><div class="alert alert-error inline" role="alert"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" class="form" data-validate novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
    </div>
    <div class="field">
      <label for="senha">Senha</label>
      <div class="password-wrap">
        <input id="senha" type="password" name="senha" required autocomplete="current-password">
        <button type="button" class="icon-btn" data-toggle-password aria-label="Mostrar senha"><?= icon('eye') ?></button>
      </div>
    </div>
    <button type="submit" class="btn btn-gold btn-block btn-lg">Entrar</button>
  </form>
  <a class="auth-link" href="<?= e(url('admin/recuperar-senha.php')) ?>">Esqueci minha senha</a>
<?php
auth_page_end();
