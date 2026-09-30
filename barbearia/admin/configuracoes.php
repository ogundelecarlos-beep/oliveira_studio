<?php
require __DIR__ . '/includes/auth.php';
require_admin();
$pdo = db();

if (is_post()) {
    admin_post_guard('admin/configuracoes.php');
    $cfg = settings();
    $d = [
        'nome_barbearia' => input('nome_barbearia'),
        'slogan'         => input('slogan'),
        'endereco'       => input('endereco'),
        'telefone'       => input('telefone'),
        'whatsapp'       => only_digits(input('whatsapp')),
        'email'          => strtolower(input('email')),
        'instagram'      => ltrim(input('instagram'), '@'),
        'facebook'       => input('facebook'),
        'tiktok'         => ltrim(input('tiktok'), '@'),
        'mapa'           => input('mapa'),
        'mapa_link'      => input('mapa_link'),
        'horario_funcionamento' => input('horario_funcionamento'),
        'antecedencia_minima'   => input_int('antecedencia_minima', 30),
        'dias_antecedencia'     => input_int('dias_antecedencia', 45),
    ];
    // Aceita o código <iframe> completo do Google Maps e extrai apenas a URL
    if (preg_match('/src=["\']([^"\']+)["\']/i', $d['mapa'], $m)) {
        $d['mapa'] = html_entity_decode($m[1]);
    }
    $erros = [];
    if (str_len($d['nome_barbearia']) < 2 || str_len($d['nome_barbearia']) > 120) $erros[] = 'Informe o nome da barbearia.';
    if ($d['telefone'] !== '' && !valid_phone(only_digits($d['telefone']))) $erros[] = 'Telefone inválido.';
    $wa = strlen($d['whatsapp']) === 13 ? substr($d['whatsapp'], 2) : $d['whatsapp'];
    if ($d['whatsapp'] !== '' && !valid_phone($wa)) $erros[] = 'WhatsApp inválido (informe DDD + número).';
    if ($d['whatsapp'] !== '' && strlen($d['whatsapp']) <= 11) $d['whatsapp'] = '55' . $d['whatsapp'];
    if ($d['email'] !== '' && !valid_email($d['email'])) $erros[] = 'E-mail inválido.';
    if ($d['instagram'] !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $d['instagram'])) $erros[] = 'Usuário do Instagram inválido.';
    if ($d['tiktok'] !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $d['tiktok'])) $erros[] = 'Usuário do TikTok inválido.';
    foreach (['facebook' => 'Facebook', 'mapa' => 'Mapa (embed)', 'mapa_link' => 'Link "Como chegar"'] as $k => $l) {
        if ($d[$k] !== '' && !preg_match('~^https://~i', $d[$k])) $erros[] = "$l: informe uma URL iniciando com https://.";
    }
    if ($d['mapa'] !== '' && !preg_match('~^https://(www\.)?google\.[a-z.]+/maps~i', $d['mapa'])) $erros[] = 'O mapa deve ser uma URL de incorporação do Google Maps.';
    if ($d['antecedencia_minima'] < 0 || $d['antecedencia_minima'] > 1440) $erros[] = 'Antecedência mínima: entre 0 e 1440 minutos.';
    if ($d['dias_antecedencia'] < 1 || $d['dias_antecedencia'] > 180) $erros[] = 'Janela de agendamento: entre 1 e 180 dias.';
    try {
        $logo = upload_image('logo', 'logo');
    } catch (RuntimeException $e) {
        $erros[] = $e->getMessage();
        $logo = null;
    }
    if ($erros) {
        flash('error', implode(' ', $erros));
        redirect('admin/configuracoes.php');
    }
    if ($logo) {
        delete_upload($cfg['logo']);
        $d['logo'] = $logo;
    } elseif (!empty($_POST['remover_logo'])) {
        delete_upload($cfg['logo']);
        $d['logo'] = null;
    }
    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
    $pdo->prepare("UPDATE configuracoes SET $set WHERE id = 1")->execute(array_map(fn($v) => $v === '' ? null : $v, array_values($d)));
    flash('success', 'Configurações salvas com sucesso.');
    redirect('admin/configuracoes.php');
}

$cfg = settings();
admin_header('Configurações', 'configuracoes', ['subtitle' => 'Informações exibidas no site público']);
?>
<form method="post" enctype="multipart/form-data" class="settings-form" data-validate novalidate>
  <?= csrf_field() ?>
  <section class="card">
    <header class="card-head"><h2>Identidade</h2></header>
    <div class="form">
      <div class="field image-field"><label>Logo</label>
        <div class="image-picker logo-picker">
          <?php if ($cfg['logo']): ?><img src="<?= e(img_src($cfg['logo'])) ?>" alt="Logo atual" data-preview-for="logo"><?php else: ?><img src="<?= e(url('img/logo.svg')) ?>" alt="Logo padrão" data-preview-for="logo"><?php endif; ?>
          <div>
            <label class="btn btn-ghost btn-sm file-btn"><?= icon('upload') ?> Enviar logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview></label>
            <small class="muted">PNG com fundo transparente, altura mínima 88px. Sem logo, o site usa o símbolo padrão + nome.</small>
            <?php if ($cfg['logo']): ?><label class="check"><input type="checkbox" name="remover_logo" value="1"> Remover logo e usar o padrão</label><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="form-row">
        <div class="field"><label for="nome">Nome da barbearia *</label><input id="nome" name="nome_barbearia" value="<?= e($cfg['nome_barbearia']) ?>" required maxlength="120"></div>
        <div class="field"><label for="slogan">Slogan</label><input id="slogan" name="slogan" value="<?= e($cfg['slogan']) ?>" maxlength="160"></div>
      </div>
    </div>
  </section>

  <section class="card">
    <header class="card-head"><h2>Contato e endereço</h2></header>
    <div class="form">
      <div class="field"><label for="end">Endereço</label><input id="end" name="endereco" value="<?= e($cfg['endereco']) ?>" maxlength="255"></div>
      <div class="form-row three">
        <div class="field"><label for="tel">Telefone</label><input id="tel" name="telefone" value="<?= e($cfg['telefone']) ?>" data-mask="phone"></div>
        <div class="field"><label for="wa">WhatsApp</label><input id="wa" name="whatsapp" value="<?= e(format_phone($cfg['whatsapp'])) ?>" data-mask="phone"></div>
        <div class="field"><label for="email">E-mail</label><input id="email" type="email" name="email" value="<?= e($cfg['email']) ?>" maxlength="160"></div>
      </div>
    </div>
  </section>

  <section class="card">
    <header class="card-head"><h2>Redes sociais</h2></header>
    <div class="form-row three">
      <div class="field"><label for="ig">Instagram (usuário)</label><input id="ig" name="instagram" value="<?= e($cfg['instagram']) ?>" placeholder="suabarbearia"></div>
      <div class="field"><label for="tt">TikTok (usuário)</label><input id="tt" name="tiktok" value="<?= e($cfg['tiktok']) ?>" placeholder="suabarbearia"></div>
      <div class="field"><label for="fb">Facebook (URL)</label><input id="fb" name="facebook" value="<?= e($cfg['facebook']) ?>" placeholder="https://facebook.com/..."></div>
    </div>
  </section>

  <section class="card">
    <header class="card-head"><h2>Google Maps</h2></header>
    <div class="form">
      <div class="field"><label for="mapa">URL de incorporação (ou cole o código &lt;iframe&gt; do Google Maps)</label><textarea id="mapa" name="mapa" rows="2"><?= e($cfg['mapa']) ?></textarea>
        <small class="muted">No Google Maps: Compartilhar → Incorporar um mapa → Copiar HTML.</small></div>
      <div class="field"><label for="ml">Link do botão “Como chegar”</label><input id="ml" name="mapa_link" value="<?= e($cfg['mapa_link']) ?>" placeholder="https://www.google.com/maps/dir/?api=1&destination=..."></div>
    </div>
  </section>

  <section class="card">
    <header class="card-head">
      <div><h2>Horários e agendamento</h2><p class="muted small">Os dias e horários de atendimento são definidos em <a class="link-gold" href="<?= e(url('admin/horarios.php')) ?>">Horários</a>.</p></div>
    </header>
    <div class="form">
      <div class="field"><label for="hf">Observação sobre o funcionamento</label><input id="hf" name="horario_funcionamento" value="<?= e($cfg['horario_funcionamento']) ?>" maxlength="255" placeholder="Ex.: Atendimento com hora marcada."></div>
      <div class="form-row">
        <div class="field"><label for="am">Antecedência mínima (minutos)</label><input id="am" type="number" name="antecedencia_minima" value="<?= (int) $cfg['antecedencia_minima'] ?>" min="0" max="1440"></div>
        <div class="field"><label for="da">Agendar com até (dias)</label><input id="da" type="number" name="dias_antecedencia" value="<?= (int) $cfg['dias_antecedencia'] ?>" min="1" max="180"></div>
      </div>
    </div>
  </section>

  <div class="sticky-actions"><button class="btn btn-gold btn-lg">Salvar configurações</button></div>
</form>
<?php admin_footer();
