/* =====================================================================
   Painel administrativo — comportamentos gerais
   (modais, máscaras, validação e alertas vêm de script.js)
   ===================================================================== */
(function () {
  'use strict';
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* ---------- Sidebar (celular) ---------- */
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const close = () => document.body.classList.remove('sidebar-open');
  if (toggle) toggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
  $$('[data-sidebar-close]').forEach((el) => el.addEventListener('click', close));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

  /* ---------- Menu do usuário: fecha ao clicar fora ---------- */
  document.addEventListener('click', (e) => {
    $$('details.user-menu[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  /* ---------- Mostrar/ocultar senha ---------- */
  $$('[data-toggle-password]').forEach((btn) => btn.addEventListener('click', () => {
    const input = btn.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
  }));

  /* ---------- Pré-visualização de imagem ---------- */
  document.addEventListener('change', (e) => {
    const input = e.target;
    if (!input.matches('input[type=file][data-preview]')) return;
    const img = document.querySelector('[data-preview-for="' + input.name + '"]');
    const file = input.files && input.files[0];
    if (!img || !file) return;
    if (file.size > 5 * 1024 * 1024) { alert('A imagem deve ter no máximo 5 MB.'); input.value = ''; return; }
    img.src = URL.createObjectURL(file);
  });

  /* ---------- Checkbox que mostra/oculta um bloco ---------- */
  $$('[data-toggle-target]').forEach((cb) => {
    const target = document.querySelector(cb.dataset.toggleTarget);
    const sync = () => { if (target) target.hidden = cb.checked; };
    cb.addEventListener('change', sync);
    sync();
  });

  /* ---------- CRUD genérico: botão "novo" e "editar" preenchem o modal ----------
     <button data-crud-new="modal-id">        limpa o formulário
     <button data-crud-edit="modal-id" data-record='{"id":1,...}'> preenche pelos nomes dos campos */
  function fill(modal, data, isNew) {
    const form = modal.querySelector('form');
    form.reset();
    $$('[data-crud-title]', modal).forEach((t) => { t.textContent = isNew ? t.dataset.titleNew : t.dataset.titleEdit; });
    $$('input, select, textarea', form).forEach((el) => {
      if (['_csrf', 'acao'].includes(el.name) || el.type === 'file') return;
      if (!(el.name in data)) { if (el.name === 'id') el.value = ''; return; }
      const v = data[el.name];
      if (el.type === 'checkbox') el.checked = !!Number(v) || v === true;
      else if (el.type === 'radio') el.checked = String(el.value) === String(v);
      else if (el.multiple) $$('option', el).forEach((o) => { o.selected = (v || []).map(String).includes(o.value); });
      else el.value = v ?? '';
    });
    $$('[data-preview-for]', modal).forEach((img) => {
      const key = img.dataset.previewFor;
      img.src = data['_' + key + '_url'] || img.dataset.default;
    });
    $$('[data-show-edit]', modal).forEach((el) => { el.hidden = isNew; });
    $$('.field.has-error', form).forEach((f) => f.classList.remove('has-error'));
    window.UI.openModal(modal);
  }
  document.addEventListener('click', (e) => {
    const n = e.target.closest('[data-crud-new]');
    if (n) { const m = document.getElementById(n.dataset.crudNew); if (m) fill(m, JSON.parse(n.dataset.defaults || '{}'), true); return; }
    const ed = e.target.closest('[data-crud-edit]');
    if (ed) { const m = document.getElementById(ed.dataset.crudEdit); if (m) fill(m, JSON.parse(ed.dataset.record), false); }
  });

  /* ---------- Busca instantânea em tabelas ---------- */
  $$('[data-table-search]').forEach((input) => {
    const table = document.querySelector(input.dataset.tableSearch);
    if (!table) return;
    const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    input.addEventListener('input', () => {
      const q = norm(input.value.trim());
      let visible = 0;
      $$('tbody tr[data-row]', table).forEach((tr) => {
        const show = !q || norm(tr.textContent).includes(q);
        tr.hidden = !show;
        if (show) visible++;
      });
      const empty = table.querySelector('[data-search-empty]');
      if (empty) empty.hidden = visible > 0;
    });
  });

  /* ---------- Linhas clicáveis ---------- */
  document.addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a, button, form, input, select')) window.location.href = tr.dataset.href;
  });
})();
