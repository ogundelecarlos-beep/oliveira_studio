<?php
/**
 * Recuperação de senha: gera um token de uso único (válido por 1 hora),
 * armazenado como hash SHA-256, e envia o link por e-mail.
 * Em modo APP_DEBUG, se o envio de e-mail falhar, o link é exibido na tela.
 */
require __DIR__ . '/includes/auth.php';

$enviado = false;
$linkDebug = '';
$erro = '';

if (is_post()) {
    $email = strtolower(input('email'));
    if (!csrf_valid()) {
        $erro = 'Sessão expirada. Atualize a página.';
    } elseif (!valid_email($email)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (login_bloqueado(client_ip())) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos.';
    } else {
        $st = db()->prepare('SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE recuperacao_senha SET usado = 1 WHERE usuario_id = ? AND usado = 0')->execute([$u['id']]);
            db()->prepare('INSERT INTO recuperacao_senha (usuario_id, token_hash, expira_em) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
                ->execute([$u['id'], hash('sha256', $token)]);
            $link = absolute_url('admin/redefinir-senha.php?token=' . $token);
            $assunto = 'Redefinição de senha — ' . settings()['nome_barbearia'];
            $corpo = "Olá, {$u['nome']}!\n\nRecebemos um pedido para redefinir sua senha do painel.\n"
                . "Acesse o link abaixo (válido por 1 hora):\n\n{$link}\n\nSe não foi você, ignore este e-mail.";
            $ok = @mail($email, $assunto, $corpo, 'Content-Type: text/plain; charset=UTF-8');
            if (!$ok && APP_DEBUG) {
                $linkDebug = $link;
            }
        } else {
            registrar_falha_login(client_ip(), $email);
        }
        // Mesma resposta para e-mails existentes ou não (evita enumeração de usuários)
        $enviado = true;
    }
}

auth_page_start('Recuperar senha');
?>
  <h1>Recuperar senha</h1>
  <?php if ($enviado): ?>
    <div class="alert alert-success inline">Se o e-mail estiver cadastrado, você receberá um link para criar uma nova senha em instantes.</div>
    <?php if ($linkDebug): ?>
      <div class="alert alert-warning inline"><span><strong>Modo de desenvolvimento:</strong> nenhum servidor de e-mail configurado. Use o link abaixo:<br><a class="link-gold" href="<?= e($linkDebug) ?>"><?= e($linkDebug) ?></a></span></div>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">Informe o e-mail cadastrado. Enviaremos um link para você criar uma nova senha.</p>
    <?php if ($erro): ?><div class="alert alert-error inline"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" class="form" data-validate novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="email">E-mail</label><input id="email" type="email" name="email" required autocomplete="email" autofocus></div>
      <button type="submit" class="btn btn-gold btn-block btn-lg">Enviar link</button>
    </form>
  <?php endif; ?>
  <a class="auth-link" href="<?= e(url('admin/login.php')) ?>">Voltar para o login</a>
<?php auth_page_end();
