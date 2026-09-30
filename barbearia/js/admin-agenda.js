/* =====================================================================
   Agenda administrativa: modais de agendamento e sugestão de horários
   ===================================================================== */
(function () {
  'use strict';
  const modal = document.getElementById('modal-agendamento');
  const form = document.getElementById('form-agendamento');
  if (!modal || !form) return;

  const cfg = window.AGENDA || {};
  const title = modal.querySelector('#ag-title');
  const quick = modal.querySelector('[data-ag-quick]');
  const suggest = modal.querySelector('[data-slot-suggest]');
  let reqId = 0;

  function setMode(mode) {
    form.cliente_modo.value = mode;
    modal.querySelectorAll('[data-client-mode] button').forEach((b) => b.classList.toggle('is-active', b.dataset.mode === mode));
    modal.querySelectorAll('[data-mode-panel]').forEach((p) => { p.hidden = p.dataset.modePanel !== mode; });
  }
  modal.querySelectorAll('[data-client-mode] button').forEach((b) => b.addEventListener('click', () => setMode(b.dataset.mode)));

  function loadSuggestions() {
    const s = form.servico_id.value, b = form.barbeiro_id.value, d = form.data.value;
    if (!s || !b || !d) { suggest.innerHTML = ''; return; }
    const id = ++reqId;
    suggest.innerHTML = '<span class="muted small">Carregando horários livres…</span>';
    const q = new URLSearchParams({ servico: s, barbeiro: b, data: d, admin: '1' });
    if (form.id.value) q.set('ignorar', form.id.value);
    fetch(window.APP.base + '/api/disponibilidade.php?' + q, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => {
        if (id !== reqId) return;
        const livres = (res.slots || []).filter((x) => x.disponivel);
        if (!res.ok) { suggest.innerHTML = '<span class="field-error">' + (res.erro || 'Erro') + '</span>'; return; }
        if (!livres.length) { suggest.innerHTML = '<span class="muted small">' + (res.mensagem || 'Sem horários livres nesta data para este profissional.') + '</span>'; return; }
        suggest.innerHTML = '<span class="muted small">Horários livres:</span>';
        livres.forEach((x) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'chip-btn' + (form.hora.value === x.hora ? ' is-active' : '');
          btn.textContent = x.hora;
          btn.addEventListener('click', () => {
            form.hora.value = x.hora;
            suggest.querySelectorAll('.chip-btn').forEach((c) => c.classList.toggle('is-active', c === btn));
          });
          suggest.appendChild(btn);
        });
      })
      .catch(() => { suggest.innerHTML = '<span class="field-error">Falha ao consultar horários.</span>'; });
  }
  form.querySelectorAll('[data-slot-trigger]').forEach((el) => el.addEventListener('change', loadSuggestions));

  function openNew(pre = {}) {
    form.reset();
    form.id.value = '';
    setMode('existente');
    title.textContent = 'Novo agendamento';
    quick.hidden = true;
    form.data.value = pre.data || cfg.date || new Date().toISOString().slice(0, 10);
    if (pre.barbeiro || cfg.barbeiro) form.barbeiro_id.value = pre.barbeiro || cfg.barbeiro;
    if (pre.hora) form.hora.value = pre.hora;
    form.status.value = 'confirmado';
    window.UI.openModal(modal);
    loadSuggestions();
  }

  function openEdit(a) {
    form.reset();
    setMode('existente');
    form.id.value = a.id;
    form.cliente_id.value = a.cliente_id;
    form.servico_id.value = a.servico_id;
    form.barbeiro_id.value = a.barbeiro_id;
    form.data.value = a.data;
    form.hora.value = a.hora;
    form.status.value = a.status;
    form.observacao.value = a.observacao || '';
    form.valor.value = a.valor;
    title.textContent = 'Agendamento #' + String(a.id).padStart(5, '0') + ' · ' + a.cliente_nome;
    quick.hidden = false;
    quick.querySelectorAll('form').forEach((f) => {
      f.querySelector('[name=id]').value = a.id;
      f.hidden = f.dataset.statusForm === a.status;
    });
    quick.querySelector('[data-ag-whatsapp]').href = a.whatsapp;
    window.UI.openModal(modal);
    loadSuggestions();
  }

  document.addEventListener('click', (e) => {
    const ev = e.target.closest('[data-appointment]');
    if (ev) { e.stopPropagation(); openEdit(JSON.parse(ev.dataset.appointment)); return; }
    if (e.target.closest('[data-new-appointment]')) { openNew(); return; }
    const col = e.target.closest('.cal-col');
    if (col && !e.target.closest('.cal-block')) {
      const rect = col.getBoundingClientRect();
      const min = Number(col.dataset.gridStart) + (e.clientY - rect.top) / Number(col.dataset.ppm);
      const rounded = Math.floor(min / 10) * 10;
      const hora = String(Math.floor(rounded / 60)).padStart(2, '0') + ':' + String(rounded % 60).padStart(2, '0');
      openNew({ data: col.dataset.date, barbeiro: col.dataset.barbeiro, hora });
    }
  });

  if (cfg.abrir) {
    const el = [...document.querySelectorAll('[data-appointment]')].find((x) => JSON.parse(x.dataset.appointment).id === cfg.abrir);
    if (el) { openEdit(JSON.parse(el.dataset.appointment)); el.scrollIntoView({ block: 'center' }); }
  } else if (cfg.novo) {
    openNew();
  }

  // Rola a grade do dia até o horário atual / início do expediente
  const scroll = document.querySelector('.cal-scroll');
  const now = document.querySelector('.cal-now');
  if (scroll && now && !cfg.abrir) scroll.scrollTop = Math.max(0, now.offsetTop - 120);
})();
