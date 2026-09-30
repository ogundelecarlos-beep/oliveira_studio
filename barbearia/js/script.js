/* =====================================================================
   Scripts do site público (também reutilizados no painel)
   ===================================================================== */
(function () {
  'use strict';

  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const base = (window.APP && window.APP.base) || '';

  /* ---------- Imagens: fallback elegante se a foto não carregar ---------- */
  const FALLBACK = base + '/img/placeholder.svg';
  function fallback(img) {
    if (img.dataset.fallback) return;
    img.dataset.fallback = '1';
    img.src = img.closest('.barber-media, .barber-profile-media, .option-avatar, .avatar-img') ? base + '/img/avatar.svg' : FALLBACK;
  }
  $$('img').forEach((img) => {
    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) fallback(img);
    img.addEventListener('error', () => fallback(img), { once: true });
  });

  /* ---------- Header ---------- */
  const header = $('.site-header');
  if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  const toggle = $('.nav-toggle');
  const nav = $('#main-nav');
  if (toggle && nav) {
    const close = () => {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Abrir menu');
      document.body.classList.remove('nav-open');
    };
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
      document.body.classList.toggle('nav-open', open);
    });
    $$('a', nav).forEach((a) => a.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    window.matchMedia('(min-width: 1080px)').addEventListener('change', close);
  }

  /* ---------- Animações de entrada ---------- */
  const reveals = $$('.reveal');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('is-visible'));
  }

  /* ---------- Máscara de telefone ---------- */
  function maskPhone(v) {
    const d = v.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d.length ? '(' + d : '';
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }
  document.addEventListener('input', (e) => {
    if (e.target.matches('[data-mask="phone"]')) e.target.value = maskPhone(e.target.value);
  });
  $$('[data-mask="phone"]').forEach((i) => { if (i.value) i.value = maskPhone(i.value); });

  /* ---------- Validação genérica (formulários com data-validate) ---------- */
  function validateField(el) {
    const field = el.closest('.field');
    if (!field) return true;
    let msg = '';
    const v = el.value.trim();
    if (el.required && !v) msg = 'Campo obrigatório.';
    else if (v && el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) msg = 'E-mail inválido.';
    else if (v && el.matches('[data-mask="phone"]')) {
      const d = v.replace(/\D/g, '');
      if (!/^[1-9][1-9]\d{8,9}$/.test(d) || (d.length === 11 && d[2] !== '9')) msg = 'Telefone inválido. Use DDD + número.';
    } else if (v && el.minLength > 0 && v.length < el.minLength) msg = 'Mínimo de ' + el.minLength + ' caracteres.';
    else if (v && el.type === 'number' && el.min !== '' && Number(v) < Number(el.min)) msg = 'Valor mínimo: ' + el.min + '.';
    let err = field.querySelector('.field-error');
    if (msg && !err) { err = document.createElement('span'); err.className = 'field-error'; field.appendChild(err); }
    if (err) err.textContent = msg;
    field.classList.toggle('has-error', !!msg);
    return !msg;
  }
  $$('form[data-validate]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const fields = $$('input:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([type=file]), textarea, select', form).filter((f) => !f.closest('.hp') && !f.disabled);
      const ok = fields.map(validateField).every(Boolean);
      if (!ok) {
        e.preventDefault();
        const first = form.querySelector('.has-error input, .has-error textarea, .has-error select');
        if (first) first.focus();
      }
    });
    form.addEventListener('blur', (e) => { if (e.target.matches('input, textarea, select')) validateField(e.target); }, true);
  });

  /* ---------- Confirmação antes de enviar ---------- */
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) e.preventDefault();
  });

  /* ---------- Alertas ---------- */
  $$('.alert').forEach((a) => {
    const btn = $('.alert-close', a);
    if (btn) btn.addEventListener('click', () => a.remove());
    if (a.closest('.flash-stack')) setTimeout(() => { a.classList.add('is-leaving'); setTimeout(() => a.remove(), 400); }, 6500);
  });

  /* ---------- Modais ---------- */
  let lastFocus = null;
  function openModal(m) {
    lastFocus = document.activeElement;
    m.hidden = false;
    document.body.classList.add('modal-open');
    requestAnimationFrame(() => m.classList.add('is-open'));
    const f = m.querySelector('input:not([type=hidden]):not([type=radio]), select, textarea, button');
    if (f) setTimeout(() => f.focus(), 60);
  }
  function closeModal(m) {
    m.classList.remove('is-open');
    document.body.classList.remove('modal-open');
    setTimeout(() => { m.hidden = true; }, 200);
    if (lastFocus) lastFocus.focus();
  }
  window.UI = { openModal, closeModal };
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) { const m = document.getElementById(opener.dataset.modalOpen); if (m) { e.preventDefault(); openModal(m); } }
    const closer = e.target.closest('[data-modal-close]');
    if (closer) closeModal(closer.closest('.modal'));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { const m = $('.modal.is-open'); if (m) closeModal(m); }
  });

  /* ---------- Galeria: filtros ---------- */
  $$('[data-filter-group]').forEach((group) => {
    const grid = $('[data-gallery="' + group.dataset.filterGroup + '"]');
    if (!grid) return;
    const empty = grid.parentElement.querySelector('[data-gallery-empty]');
    const apply = (cat) => {
      $$('.filter', group).forEach((b) => {
        const on = b.dataset.filter === cat;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', String(on));
      });
      let visible = 0;
      $$('.gallery-item', grid).forEach((it) => {
        const show = cat === 'all' || it.dataset.category === cat;
        it.classList.toggle('is-hidden', !show);
        if (show) visible++;
      });
      if (empty) empty.hidden = visible > 0;
    };
    $$('.filter', group).forEach((b) => { b.setAttribute('role', 'tab'); b.addEventListener('click', () => apply(b.dataset.filter)); });
    if (group.dataset.initial && group.dataset.initial !== 'all') apply(group.dataset.initial);
  });

  /* ---------- Lightbox ---------- */
  const lb = $('#lightbox');
  if (lb) {
    const img = $('img', lb);
    const cap = $('figcaption', lb);
    let items = [];
    let idx = 0;
    const show = (i) => {
      idx = (i + items.length) % items.length;
      const it = items[idx];
      const thumb = $('img', it);
      delete img.dataset.fallback;
      img.src = thumb && thumb.dataset.fallback ? thumb.src : it.getAttribute('href');
      img.alt = thumb ? thumb.alt : '';
      cap.textContent = it.dataset.caption || '';
    };
    const close = () => { lb.classList.remove('is-open'); document.body.classList.remove('modal-open'); setTimeout(() => { lb.hidden = true; }, 200); };
    document.addEventListener('click', (e) => {
      const it = e.target.closest('.gallery-item');
      if (!it) return;
      e.preventDefault();
      items = $$('.gallery-item:not(.is-hidden)', it.parentElement);
      lb.hidden = false;
      document.body.classList.add('modal-open');
      requestAnimationFrame(() => lb.classList.add('is-open'));
      show(items.indexOf(it));
    });
    $('.lightbox-close', lb).addEventListener('click', close);
    $('.prev', lb).addEventListener('click', () => show(idx - 1));
    $('.next', lb).addEventListener('click', () => show(idx + 1));
    lb.addEventListener('click', (e) => { if (e.target === lb) close(); });
    img.addEventListener('error', () => fallback(img));
    document.addEventListener('keydown', (e) => {
      if (lb.hidden) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(idx - 1);
      if (e.key === 'ArrowRight') show(idx + 1);
    });
  }
})();
