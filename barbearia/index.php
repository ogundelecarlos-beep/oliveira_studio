<?php
require __DIR__ . '/includes/bootstrap.php';

$cfg = settings();
$servicos = db()->query('SELECT * FROM servicos WHERE ativo = 1 AND destaque = 1 ORDER BY ordem, nome LIMIT 4')->fetchAll();
$barbeiros = barbeiros_ativos();
$notas = notas_barbeiros();
$galeria = db()->query('SELECT * FROM galeria WHERE ativo = 1 ORDER BY id DESC LIMIT 8')->fetchAll();
$avaliacoes = db()->query('SELECT av.*, c.nome AS cliente_nome, b.nome AS barbeiro_nome
                           FROM avaliacoes av
                           JOIN clientes c ON c.id = av.cliente_id
                           LEFT JOIN agendamentos a ON a.id = av.agendamento_id
                           LEFT JOIN barbeiros b ON b.id = a.barbeiro_id
                           WHERE av.aprovado = 1 ORDER BY av.criado_em DESC LIMIT 6')->fetchAll();
$resumo = db()->query('SELECT AVG(nota) media, COUNT(*) total FROM avaliacoes WHERE aprovado = 1')->fetch();
$promo = promocoes_vigentes()[0] ?? null;
$horarios = horario_resumo();
$categorias = categorias_galeria();

$pageTitle = '';
$active = 'inicio';
$bodyClass = 'page-home';
require __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-media">
    <img src="https://images.unsplash.com/photo-1585747860715-2ba37e788b70?auto=format&fit=crop&w=2000&q=80" alt="Barbeiro finalizando um corte masculino na navalha" fetchpriority="high">
  </div>
  <div class="container hero-content">
    <span class="eyebrow reveal"><?= icon('scissors') ?> Barbearia desde 2014 · Vila Madalena</span>
    <h1 class="hero-title display reveal">Seu estilo.<br><span class="text-outline">Seu momento.</span></h1>
    <p class="hero-sub reveal">Cuidados, estilo e precisão em cada detalhe.</p>
    <div class="hero-actions reveal">
      <a href="<?= e(url('agendamento.php')) ?>" class="btn btn-gold btn-lg">Agendar horário <?= icon('arrow-right') ?></a>
      <a href="<?= e(url('servicos.php')) ?>" class="btn btn-ghost btn-lg">Conhecer serviços</a>
    </div>
    <dl class="hero-stats reveal">
      <div><dt>Anos de casa</dt><dd>12</dd></div>
      <div><dt>Nota média</dt><dd><?= number_format((float) ($resumo['media'] ?: 5), 1, ',', '') ?><small>/5</small></dd></div>
      <div><dt>Cortes realizados</dt><dd>18<small>mil+</small></dd></div>
    </dl>
  </div>
  <a href="#servicos" class="hero-scroll" aria-label="Rolar para serviços"><span></span></a>
</section>

<div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>Corte masculino</span><i>✦</i><span>Barba na toalha quente</span><i>✦</i><span>Degradê navalhado</span><i>✦</i><span>Freestyle</span><i>✦</i><span>Sobrancelha</span><i>✦</i><span>Pigmentação</span><i>✦</i>
    <?php endfor; ?>
  </div>
</div>

<!-- SERVIÇOS -->
<section class="section" id="servicos">
  <div class="container">
    <header class="section-head">
      <div>
        <span class="eyebrow"><b>01</b> Serviços</span>
        <h2 class="section-title display">O ritual <em>completo</em></h2>
      </div>
      <p class="section-lead">Cada atendimento começa com uma conversa. Entendemos seu estilo, sua rotina e o que você quer transmitir — e só então pegamos na tesoura.</p>
    </header>

    <div class="service-grid">
      <?php foreach ($servicos as $i => $s):
          $sp = promocao_para_servico((int) $s['id'], date('Y-m-d')); ?>
        <article class="service-card reveal" style="--d: <?= $i * 80 ?>ms">
          <div class="service-media">
            <img src="<?= e(img_src($s['foto'])) ?>" alt="<?= e($s['nome']) ?>" loading="lazy">
            <span class="service-num"><?= sprintf('%02d', $i + 1) ?></span>
            <?php if ($sp): ?><span class="chip chip-gold">Promoção</span><?php endif; ?>
          </div>
          <div class="service-body">
            <h3><?= e($s['nome']) ?></h3>
            <p><?= e($s['descricao']) ?></p>
            <div class="service-meta">
              <div class="price">
                <?php if ($sp): ?><del><?= e(money($s['preco'])) ?></del><?php endif; ?>
                <strong><?= e(money($sp ? $sp['preco_promocional'] : $s['preco'])) ?></strong>
              </div>
              <span class="duration"><?= icon('clock') ?> <?= e($s['duracao']) ?> minutos</span>
            </div>
            <a href="<?= e(url('agendamento.php?servico=' . (int) $s['id'])) ?>" class="btn btn-outline btn-block">Agendar <?= icon('arrow-right') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="section-foot"><a href="<?= e(url('servicos.php')) ?>" class="link-arrow">Ver todos os serviços <?= icon('arrow-right') ?></a></div>
  </div>
</section>

<!-- SOBRE -->
<section class="section section-alt about" id="sobre">
  <div class="container about-grid">
    <div class="about-media reveal">
      <figure class="about-img about-img-1"><img src="https://images.unsplash.com/photo-1512690459411-b9245aed614b?auto=format&fit=crop&w=1000&q=80" alt="Interior da barbearia com cadeiras clássicas" loading="lazy"></figure>
      <figure class="about-img about-img-2"><img src="https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&w=800&q=80" alt="Barbeiro aparando barba" loading="lazy"></figure>
      <div class="about-badge"><strong>12</strong><span>anos de<br>tradição</span></div>
    </div>
    <div class="about-content reveal">
      <span class="eyebrow"><b>02</b> Nossa história</span>
      <h2 class="section-title display">Uma cadeira, uma navalha e <em>um propósito.</em></h2>
      <p>A <?= e($cfg['nome_barbearia']) ?> nasceu em 2014, numa sala pequena com uma única cadeira de ferro fundido herdada do avô do João. A ideia era simples: resgatar o ritual da barbearia clássica — conversa boa, toalha quente, acabamento na navalha — sem abrir mão das técnicas mais atuais.</p>
      <p>Hoje somos quatro barbeiros, um espaço de 180 m² na Vila Madalena e milhares de clientes que fazem da nossa casa um ponto de encontro. O propósito continua o mesmo: você sair daqui melhor do que entrou.</p>
      <ul class="features">
        <li><?= icon('award') ?><div><strong>Profissionais certificados</strong><span>Equipe em constante atualização técnica.</span></div></li>
        <li><?= icon('shield') ?><div><strong>Higiene rigorosa</strong><span>Material esterilizado e lâminas descartáveis.</span></div></li>
        <li><?= icon('coffee') ?><div><strong>Ambiente exclusivo</strong><span>Café especial, cerveja gelada e boa música.</span></div></li>
        <li><?= icon('calendar') ?><div><strong>Pontualidade</strong><span>Agenda online e atendimento sem espera.</span></div></li>
      </ul>
    </div>
  </div>
</section>

<!-- BARBEIROS -->
<section class="section" id="barbeiros">
  <div class="container">
    <header class="section-head">
      <div>
        <span class="eyebrow"><b>03</b> Equipe</span>
        <h2 class="section-title display">Mãos que <em>assinam</em> o corte</h2>
      </div>
      <p class="section-lead">Cada barbeiro tem sua especialidade. Escolha quem combina com o seu estilo ou deixe que a gente indique o profissional disponível.</p>
    </header>
    <div class="barber-grid">
      <?php foreach ($barbeiros as $i => $b):
          $n = $notas[(int) $b['id']] ?? ['media' => 5.0, 'total' => 0]; ?>
        <article class="barber-card reveal" style="--d: <?= $i * 80 ?>ms">
          <div class="barber-media">
            <img src="<?= e(img_src($b['foto'], 'img/avatar.svg')) ?>" alt="Foto de <?= e($b['nome']) ?>" loading="lazy">
            <div class="barber-rating"><?= stars($n['media']) ?><span><?= number_format($n['media'], 1, ',', '') ?></span></div>
          </div>
          <div class="barber-body">
            <h3><?= e($b['nome']) ?></h3>
            <ul class="tags"><?php foreach (especialidades_list($b['especialidades']) as $esp): ?><li><?= e($esp) ?></li><?php endforeach; ?></ul>
            <p><?= e($b['descricao']) ?></p>
            <a href="<?= e(url('agendamento.php?barbeiro=' . (int) $b['id'])) ?>" class="btn btn-outline btn-block">Agendar com este barbeiro</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- PROMOÇÃO -->
<?php if ($promo): ?>
<section class="promo" id="promocao">
  <div class="container">
    <div class="promo-card reveal">
      <div class="promo-media"><img src="<?= e(img_src($promo['imagem'], 'img/placeholder.svg')) ?>" alt="<?= e($promo['nome']) ?>" loading="lazy"></div>
      <div class="promo-content">
        <span class="chip chip-gold"><?= icon('tag') ?> Promoção da casa</span>
        <h2 class="display"><?= e($promo['nome']) ?></h2>
        <p><?= e($promo['descricao']) ?></p>
        <div class="promo-price">
          <span class="from">De <del><?= e(money($promo['preco_antigo'])) ?></del></span>
          <span class="to">por <strong><?= e(money($promo['preco_promocional'])) ?></strong></span>
        </div>
        <p class="promo-valid"><?= icon('clock') ?> Válido até <?= e(date_br($promo['data_fim'])) ?></p>
        <a href="<?= e(url('agendamento.php' . ($promo['servico_id'] ? '?servico=' . (int) $promo['servico_id'] : ''))) ?>" class="btn btn-gold btn-lg">Aproveitar promoção <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- GALERIA -->
<section class="section" id="galeria">
  <div class="container">
    <header class="section-head">
      <div>
        <span class="eyebrow"><b>04</b> Galeria</span>
        <h2 class="section-title display">Trabalhos <em>recentes</em></h2>
      </div>
      <div class="filters" role="tablist" aria-label="Filtrar galeria" data-filter-group="home-gallery">
        <button type="button" class="filter is-active" data-filter="all">Todos</button>
        <?php foreach (['degrade', 'social', 'barba', 'freestyle'] as $k): ?>
          <button type="button" class="filter" data-filter="<?= e($k) ?>"><?= e($categorias[$k]) ?></button>
        <?php endforeach; ?>
      </div>
    </header>
    <div class="gallery-grid" data-gallery="home-gallery">
      <?php foreach ($galeria as $g): ?>
        <a class="gallery-item" href="<?= e(img_src($g['imagem'])) ?>" data-category="<?= e($g['categoria']) ?>" data-caption="<?= e($g['titulo']) ?>">
          <img src="<?= e(img_src($g['imagem'])) ?>" alt="<?= e($g['titulo'] ?: 'Corte ' . $categorias[$g['categoria']]) ?>" loading="lazy">
          <span class="gallery-caption"><small><?= e($categorias[$g['categoria']]) ?></small><?= e($g['titulo']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="section-foot"><a href="<?= e(url('galeria.php')) ?>" class="link-arrow">Ver galeria completa <?= icon('arrow-right') ?></a></div>
  </div>
</section>

<!-- AVALIAÇÕES -->
<section class="section section-alt" id="avaliacoes">
  <div class="container">
    <header class="section-head">
      <div>
        <span class="eyebrow"><b>05</b> Avaliações</span>
        <h2 class="section-title display">Quem senta, <em>volta.</em></h2>
      </div>
      <div class="rating-summary">
        <strong><?= number_format((float) ($resumo['media'] ?: 0), 1, ',', '') ?></strong>
        <div><?= stars((float) $resumo['media']) ?><span><?= (int) $resumo['total'] ?> avaliações verificadas</span></div>
        <button type="button" class="btn btn-outline" data-modal-open="modal-avaliacao">Deixar avaliação</button>
      </div>
    </header>
    <div class="review-grid">
      <?php foreach ($avaliacoes as $i => $a): ?>
        <figure class="review-card reveal" style="--d: <?= ($i % 3) * 80 ?>ms">
          <?= stars((float) $a['nota']) ?>
          <blockquote>“<?= e($a['comentario']) ?>”</blockquote>
          <figcaption>
            <span class="avatar"><?= e(initials($a['cliente_nome'])) ?></span>
            <span><strong><?= e($a['cliente_nome']) ?></strong><small><?= $a['barbeiro_nome'] ? 'Atendido por ' . e($a['barbeiro_nome']) : 'Cliente' ?> · <?= e(date_br($a['criado_em'])) ?></small></span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
      <?php if (!$avaliacoes): ?><p class="empty">Ainda não há avaliações publicadas.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- LOCALIZAÇÃO -->
<section class="section" id="localizacao">
  <div class="container location-grid">
    <div class="location-info reveal">
      <span class="eyebrow"><b>06</b> Localização</span>
      <h2 class="section-title display">Venha nos <em>visitar</em></h2>
      <ul class="info-list">
        <li><?= icon('map-pin') ?><div><small>Endereço</small><span><?= e($cfg['endereco']) ?></span></div></li>
        <li><?= icon('phone') ?><div><small>Telefone</small><a href="tel:<?= e(only_digits($cfg['telefone'])) ?>"><?= e($cfg['telefone']) ?></a></div></li>
        <li><?= icon('whatsapp') ?><div><small>WhatsApp</small><a href="<?= e(whatsapp_link('Olá! Vim pelo site.')) ?>" target="_blank" rel="noopener"><?= e(format_phone($cfg['whatsapp'])) ?></a></div></li>
        <li><?= icon('clock') ?><div><small>Funcionamento</small>
          <?php foreach ($horarios as $h): ?><span class="hours-line"><?= e($h['dias']) ?> <b><?= e($h['horario']) ?></b></span><?php endforeach; ?>
        </div></li>
      </ul>
      <div class="btn-row">
        <a href="<?= e($cfg['mapa_link'] ?: 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($cfg['endereco'])) ?>" target="_blank" rel="noopener" class="btn btn-gold"><?= icon('navigation') ?> Como chegar</a>
        <a href="<?= e(url('contato.php')) ?>" class="btn btn-ghost">Fale conosco</a>
      </div>
    </div>
    <div class="map-wrap reveal">
      <?php if ($cfg['mapa']): ?>
        <iframe src="<?= e($cfg['mapa']) ?>" title="Mapa de localização da barbearia" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- MODAL AVALIAÇÃO -->
<div class="modal" id="modal-avaliacao" role="dialog" aria-modal="true" aria-labelledby="modal-avaliacao-title" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar"><?= icon('x') ?></button>
    <h3 id="modal-avaliacao-title" class="display">Como foi sua experiência?</h3>
    <p class="muted">Avaliações são exclusivas para clientes com atendimento concluído e são publicadas após moderação.</p>
    <form action="<?= e(url('acoes/avaliacao.php')) ?>" method="post" class="form" data-validate novalidate>
      <?= csrf_field() ?>
      <fieldset class="star-input" aria-label="Nota">
        <legend>Sua nota</legend>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" id="nota-<?= $i ?>" name="nota" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
          <label for="nota-<?= $i ?>" title="<?= $i ?> estrela<?= $i > 1 ? 's' : '' ?>"><?= icon('star') ?></label>
        <?php endfor; ?>
      </fieldset>
      <div class="form-row">
        <div class="field"><label for="av-nome">Nome</label><input id="av-nome" name="nome" required maxlength="120" autocomplete="name"></div>
        <div class="field"><label for="av-tel">Telefone usado no agendamento</label><input id="av-tel" name="telefone" required data-mask="phone" inputmode="tel" autocomplete="tel" placeholder="(11) 99999-9999"></div>
      </div>
      <div class="field"><label for="av-com">Comentário</label><textarea id="av-com" name="comentario" rows="4" required minlength="10" maxlength="600" placeholder="Conte como foi o atendimento..."></textarea></div>
      <button type="submit" class="btn btn-gold btn-block">Enviar avaliação</button>
    </form>
  </div>
</div>

<!-- LIGHTBOX -->
<?php include __DIR__ . '/includes/lightbox.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
