<?php
require __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
$categorias = categorias_galeria();
$filtro = array_key_exists(input('categoria'), $categorias) ? input('categoria') : '';
$back = 'admin/galeria.php' . ($filtro ? '?categoria=' . $filtro : '');

if (is_post()) {
    admin_post_guard($back);
    $acao = input('acao');
    $id = input_int('id');

    if ($acao === 'upload') {
        $categoria = input('categoria');
        $titulo = input('titulo');
        if (!array_key_exists($categoria, $categorias)) {
            flash('error', 'Escolha uma categoria válida.');
            redirect($back);
        }
        // Suporta envio múltiplo (fotos[])
        $files = $_FILES['fotos'] ?? null;
        $enviadas = 0;
        $erros = [];
        if ($files && is_array($files['name'])) {
            $total = min(count($files['name']), 20);
            for ($i = 0; $i < $total; $i++) {
                $_FILES['_foto'] = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try {
                    $path = upload_image('_foto', 'galeria');
                    if ($path) {
                        $pdo->prepare('INSERT INTO galeria (imagem, titulo, categoria, ativo) VALUES (?, ?, ?, 1)')->execute([$path, $titulo !== '' ? mb_substr($titulo, 0, 120) : null, $categoria]);
                        $enviadas++;
                    }
                } catch (RuntimeException $e) {
                    $erros[] = $files['name'][$i] . ': ' . $e->getMessage();
                }
            }
        }
        if ($enviadas) flash('success', $enviadas . ' foto(s) adicionada(s) à galeria.');
        if ($erros) flash('error', implode(' ', $erros));
        if (!$enviadas && !$erros) flash('error', 'Selecione ao menos uma imagem.');
    } elseif ($acao === 'editar') {
        $categoria = input('categoria');
        if (!array_key_exists($categoria, $categorias)) {
            flash('error', 'Categoria inválida.');
            redirect($back);
        }
        $pdo->prepare('UPDATE galeria SET titulo = ?, categoria = ?, ativo = ? WHERE id = ?')->execute([input('titulo') ?: null, $categoria, !empty($_POST['ativo']) ? 1 : 0, $id]);
        flash('success', 'Foto atualizada.');
    } elseif ($acao === 'toggle') {
        $pdo->prepare('UPDATE galeria SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        flash('success', 'Visibilidade da foto atualizada.');
    } elseif ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT imagem FROM galeria WHERE id = ?');
        $st->execute([$id]);
        delete_upload($st->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM galeria WHERE id = ?')->execute([$id]);
        flash('success', 'Foto excluída.');
    }
    redirect($back);
}

$sql = 'SELECT * FROM galeria' . ($filtro ? ' WHERE categoria = ?' : '') . ' ORDER BY id DESC';
$st = $pdo->prepare($sql);
$st->execute($filtro ? [$filtro] : []);
$fotos = $st->fetchAll();
$contagem = $pdo->query('SELECT categoria, COUNT(*) FROM galeria GROUP BY categoria')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_header('Galeria', 'galeria', [
    'subtitle' => array_sum($contagem) . ' foto(s)',
    'actions' => '<button type="button" class="btn btn-gold btn-sm" data-modal-open="modal-upload">' . icon('upload') . '<span class="hide-sm">Enviar fotos</span></button>',
]);
?>
<div class="tabs-bar">
  <a href="<?= e(url('admin/galeria.php')) ?>" class="<?= !$filtro ? 'is-active' : '' ?>">Todas <em class="count"><?= array_sum($contagem) ?></em></a>
  <?php foreach ($categorias as $k => $l): ?><a href="?categoria=<?= e($k) ?>" class="<?= $filtro === $k ? 'is-active' : '' ?>"><?= e($l) ?> <em class="count"><?= (int) ($contagem[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>

<div class="photo-grid">
  <?php foreach ($fotos as $f):
      $rec = ['id' => (int) $f['id'], 'titulo' => (string) $f['titulo'], 'categoria' => $f['categoria'], 'ativo' => (int) $f['ativo'], '_preview_url' => img_src($f['imagem'])]; ?>
    <figure class="photo <?= $f['ativo'] ? '' : 'is-inactive' ?>">
      <img src="<?= e(img_src($f['imagem'])) ?>" alt="<?= e($f['titulo']) ?>" loading="lazy">
      <span class="badge badge-neutral"><?= e($categorias[$f['categoria']]) ?></span>
      <?php if (!$f['ativo']): ?><span class="badge badge-cancelado photo-status">Oculta</span><?php endif; ?>
      <figcaption>
        <span class="clamp-1"><?= e($f['titulo'] ?: 'Sem título') ?></span>
        <div class="photo-actions">
          <button type="button" class="icon-btn" data-crud-edit="modal-foto" data-record="<?= e(json_encode($rec, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Editar" title="Editar"><?= icon('edit') ?></button>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="icon-btn" aria-label="<?= $f['ativo'] ? 'Ocultar' : 'Exibir' ?>" title="<?= $f['ativo'] ? 'Desativar' : 'Ativar' ?>"><?= icon($f['ativo'] ? 'eye-off' : 'eye') ?></button></form>
          <form method="post" data-confirm="Excluir esta foto?"><?= csrf_field() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="icon-btn danger" aria-label="Excluir" title="Excluir"><?= icon('trash') ?></button></form>
        </div>
      </figcaption>
    </figure>
  <?php endforeach; ?>
  <?php if (!$fotos): ?><p class="empty">Nenhuma foto nesta categoria.</p><?php endif; ?>
</div>

<div class="modal" id="modal-upload" role="dialog" aria-modal="true" aria-labelledby="mu-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="mu-title">Enviar fotos</h3>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?><input type="hidden" name="acao" value="upload">
      <label class="dropzone">
        <?= icon('upload') ?>
        <strong>Clique para escolher as fotos</strong>
        <small class="muted">Até 20 imagens por vez · JPG, PNG ou WEBP · máx. 5 MB cada</small>
        <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required data-dropzone>
        <span class="dropzone-files" data-dropzone-files></span>
      </label>
      <div class="form-row">
        <div class="field"><label for="u-cat">Categoria</label><select id="u-cat" name="categoria" required><?php foreach ($categorias as $k => $l): ?><option value="<?= e($k) ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="u-tit">Título <span class="muted">(opcional)</span></label><input id="u-tit" name="titulo" maxlength="120"></div>
      </div>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Enviar</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-foto" role="dialog" aria-modal="true" aria-labelledby="mf-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="mf-title">Editar foto</h3>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="acao" value="editar"><input type="hidden" name="id" value="">
      <img class="photo-preview" src="" alt="" data-preview-for="preview" data-default="<?= e(url('img/placeholder.svg')) ?>">
      <div class="form-row">
        <div class="field"><label for="f-cat">Categoria</label><select id="f-cat" name="categoria"><?php foreach ($categorias as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="f-tit">Título</label><input id="f-tit" name="titulo" maxlength="120"></div>
      </div>
      <label class="switch"><input type="checkbox" name="ativo" value="1"><span></span> Visível no site</label>
      <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button><button class="btn btn-gold">Salvar</button></div>
    </form>
  </div>
</div>
<script>
document.addEventListener('change', function (e) {
  if (!e.target.matches('[data-dropzone]')) return;
  var out = document.querySelector('[data-dropzone-files]');
  out.textContent = e.target.files.length ? e.target.files.length + ' arquivo(s) selecionado(s)' : '';
});
</script>
<?php admin_footer();
