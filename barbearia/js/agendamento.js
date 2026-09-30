/* =====================================================================
   Fluxo de agendamento em 6 etapas
   ===================================================================== */
(function () {
  'use strict';

  const form = document.getElementById('booking-form');
  if (!form || !window.BOOKING) return;

  const cfg = window.BOOKING;
  const steps = [...form.querySelectorAll('.step')];
  const indicators = [...document.querySelectorAll('[data-step-indicator]')];
  const progress = document.querySelector('.progress span');
  const btnPrev = form.querySelector('[data-prev]');
  const btnNext = form.querySelector('[data-next]');
  const btnSubmit = form.querySelector('[data-submit]');
  const slotsBox = form.querySelector('[data-slots]');
  const slotContext = form.querySelector('[data-slot-context]');
  const errorBox = form.querySelector('[data-booking-error]');
  const TOTAL = steps.length;

  const state = { step: 1, servico: null, barbeiro: null, data: null, dataLabel: '', dataExtenso: '', hora: null, preco: null };
  let slotRequest = 0;

  const money = (v) => 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const byId = (list, id) => list.find((x) => x.id === Number(id));
  const phoneDigits = (v) => v.replace(/\D/g, '');

  function validPhone(v) {
    const d = phoneDigits(v);
    if (!/^[1-9][1-9]\d{8,9}$/.test(d)) return false;
    if (d.length === 11 && d[2] !== '9') return false;
    return !/^(\d)\1+$/.test(d);
  }
  const validEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);

  function fieldErrors() {
    const errs = {};
    const nome = form.nome.value.trim();
    if (nome.length < 3 || !/^[\p{L}\s'.-]+$/u.test(nome)) errs.nome = 'Informe seu nome completo (apenas letras).';
    if (!validPhone(form.telefone.value)) errs.telefone = 'Informe um telefone válido com DDD.';
    if (!validEmail(form.email.value.trim())) errs.email = 'Informe um e-mail válido.';
    if (form.observacao.value.length > 500) errs.observacao = 'Máximo de 500 caracteres.';
    return errs;
  }

  function showFieldErrors(errs) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
      const k = el.dataset.errorFor;
      el.textContent = errs[k] || '';
      el.closest('.field').classList.toggle('has-error', !!errs[k]);
    });
  }

  function stepValid(n) {
    switch (n) {
      case 1: return !!state.servico;
      case 2: return state.barbeiro !== null;
      case 3: return !!state.data;
      case 4: return !!state.hora;
      case 5: return Object.keys(fieldErrors()).length === 0;
      default: return true;
    }
  }

  function updateSummary() {
    const s = state.servico ? byId(cfg.servicos, state.servico) : null;
    const b = state.barbeiro === null ? null : (state.barbeiro === 0 ? { nome: 'Qualquer profissional' } : byId(cfg.barbeiros, state.barbeiro));
    const preco = state.preco !== null ? state.preco : (s ? (s.promo ?? s.preco) : null);
    const values = {
      servico: s ? s.nome : '—',
      barbeiro: b ? b.nome : '—',
      data: state.data ? state.dataLabel : '—',
      hora: state.hora || '—',
      valor: preco !== null ? money(preco) : '—',
      cliente: form.nome.value.trim() ? form.nome.value.trim() + ' · ' + form.telefone.value : '—',
    };
    document.querySelectorAll('[data-sum]').forEach((el) => {
      const k = el.dataset.sum;
      if (k === 'servico' && !s && el.closest('[data-mobile-summary]')) { el.textContent = 'Escolha um serviço'; return; }
      el.textContent = values[k];
    });
    if (state.step === 6 && s) {
      const dur = s.duracao >= 60 ? Math.floor(s.duracao / 60) + 'h' + (s.duracao % 60 ? String(s.duracao % 60).padStart(2, '0') : '') : s.duracao + ' min';
      form.querySelector('[data-summary-final] [data-sum="servico"]').textContent = s.nome + ' (' + dur + ')';
      form.querySelector('[data-summary-final] [data-sum="data"]').textContent = state.dataExtenso + ' · ' + state.dataLabel;
    }
  }

  function goTo(n) {
    state.step = Math.max(1, Math.min(TOTAL, n));
    steps.forEach((s) => s.classList.toggle('is-active', Number(s.dataset.step) === state.step));
    indicators.forEach((li) => {
      const i = Number(li.dataset.stepIndicator);
      li.classList.toggle('is-current', i === state.step);
      li.classList.toggle('is-done', i < state.step);
    });
    progress.style.width = (state.step / TOTAL) * 100 + '%';
    btnPrev.hidden = state.step === 1;
    btnNext.hidden = state.step === TOTAL;
    btnSubmit.hidden = state.step !== TOTAL;
    errorBox.hidden = true;
    refreshNext();
    updateSummary();
    if (state.step === 4) loadSlots();
    if (state.step === 3) scrollToSelectedDate();
    const top = document.querySelector('.stepper').getBoundingClientRect().top + window.scrollY - 100;
    if (window.scrollY > top) window.scrollTo({ top, behavior: 'smooth' });
    const first = steps[state.step - 1].querySelector('input:not([disabled]), textarea');
    if (state.step === 5 && first) setTimeout(() => first.focus({ preventScroll: true }), 250);
  }

  function refreshNext() {
    btnNext.disabled = state.step === 5 ? false : !stepValid(state.step);
  }

  function resetFrom(step) {
    if (step <= 3) { state.hora = null; state.preco = null; }
    if (step <= 2) { /* nada extra */ }
  }

  /* ---------- Horários ---------- */
  function loadSlots() {
    if (!state.servico || state.barbeiro === null || !state.data) return;
    const reqId = ++slotRequest;
    slotsBox.innerHTML = '<div class="slot-loading"><span></span><span></span><span></span></div>';
    const b = state.barbeiro === 0 ? 'qualquer profissional' : byId(cfg.barbeiros, state.barbeiro).nome;
    slotContext.textContent = state.dataExtenso + ' · ' + b;

    const q = new URLSearchParams({ servico: state.servico, barbeiro: state.barbeiro, data: state.data });
    fetch(window.APP.base + '/api/disponibilidade.php?' + q, { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((res) => {
        if (reqId !== slotRequest) return;
        if (!res.ok) throw new Error(res.erro || 'Erro ao carregar horários.');
        state.preco = res.preco;
        updateSummary();
        slotsBox.innerHTML = '';
        const livres = res.slots.filter((s) => s.disponivel);
        if (state.hora && !livres.some((s) => s.hora === state.hora)) state.hora = null;
        if (!res.slots.length || !livres.length) {
          slotsBox.innerHTML = '<div class="empty-state">' + (res.mensagem || 'Nenhum horário disponível.') +
            '<button type="button" class="btn btn-ghost btn-sm" data-back-date>Escolher outra data</button></div>';
          slotsBox.querySelector('[data-back-date]').addEventListener('click', () => goTo(3));
        }
        if (livres.length) {
          res.slots.forEach((s) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slot' + (s.hora === state.hora ? ' is-selected' : '');
            btn.textContent = s.hora;
            btn.disabled = !s.disponivel;
            btn.setAttribute('aria-pressed', s.hora === state.hora ? 'true' : 'false');
            if (!s.disponivel) btn.title = 'Horário ocupado';
            btn.addEventListener('click', () => {
              state.hora = s.hora;
              slotsBox.querySelectorAll('.slot').forEach((x) => { x.classList.remove('is-selected'); x.setAttribute('aria-pressed', 'false'); });
              btn.classList.add('is-selected');
              btn.setAttribute('aria-pressed', 'true');
              refreshNext();
              updateSummary();
            });
            slotsBox.appendChild(btn);
          });
        }
        refreshNext();
      })
      .catch((err) => {
        if (reqId !== slotRequest) return;
        slotsBox.innerHTML = '<div class="empty-state">' + err.message + '<button type="button" class="btn btn-ghost btn-sm" data-retry>Tentar novamente</button></div>';
        slotsBox.querySelector('[data-retry]').addEventListener('click', loadSlots);
      });
  }

  /* ---------- Datas ---------- */
  const strip = form.querySelector('.date-strip');
  function scrollToSelectedDate() {
    const sel = strip.querySelector('input:checked');
    if (sel) sel.parentElement.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  }
  form.querySelectorAll('.strip-nav').forEach((btn) => {
    btn.addEventListener('click', () => strip.scrollBy({ left: (btn.classList.contains('next') ? 1 : -1) * strip.clientWidth * 0.8, behavior: 'smooth' }));
  });

  /* ---------- Eventos ---------- */
  form.addEventListener('change', (e) => {
    const t = e.target;
    if (t.name === 'servico_id') { state.servico = Number(t.value); resetFrom(1); if (state.step === 1) setTimeout(() => goTo(2), 180); }
    if (t.name === 'barbeiro_id') { state.barbeiro = Number(t.value); resetFrom(2); if (state.step === 2) setTimeout(() => goTo(3), 180); }
    if (t.name === 'data') {
      state.data = t.value; state.dataLabel = t.dataset.label; state.dataExtenso = t.dataset.extenso; resetFrom(3);
      if (state.step === 3) setTimeout(() => goTo(4), 180);
    }
    refreshNext();
    updateSummary();
  });

  form.addEventListener('input', (e) => {
    if (['nome', 'telefone', 'email', 'observacao'].includes(e.target.name)) {
      const f = e.target.closest('.field');
      if (f && f.classList.contains('has-error')) showFieldErrors(fieldErrors());
      updateSummary();
    }
  });

  btnPrev.addEventListener('click', () => goTo(state.step - 1));
  btnNext.addEventListener('click', () => {
    if (state.step === 5) {
      const errs = fieldErrors();
      showFieldErrors(errs);
      if (Object.keys(errs).length) {
        const firstErr = form.querySelector('.field.has-error input, .field.has-error textarea');
        if (firstErr) firstErr.focus();
        return;
      }
    }
    if (stepValid(state.step)) goTo(state.step + 1);
  });
  indicators.forEach((li) => li.addEventListener('click', () => {
    const target = Number(li.dataset.stepIndicator);
    if (target < state.step) goTo(target);
  }));

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    for (let i = 1; i <= 5; i++) {
      if (!stepValid(i)) { goTo(i); if (i === 5) showFieldErrors(fieldErrors()); return; }
    }
    btnSubmit.disabled = true;
    btnSubmit.classList.add('is-loading');
    errorBox.hidden = true;

    const body = new FormData();
    body.append('_csrf', window.APP.csrf);
    body.append('servico_id', state.servico);
    body.append('barbeiro_id', state.barbeiro);
    body.append('data', state.data);
    body.append('hora', state.hora);
    ['nome', 'telefone', 'email', 'observacao'].forEach((k) => body.append(k, form[k].value.trim()));

    fetch(window.APP.base + '/api/agendar.php', { method: 'POST', body, headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((res) => {
        if (!res.ok) {
          errorBox.textContent = res.erro || 'Não foi possível concluir o agendamento.';
          errorBox.hidden = false;
          if (res.campos) {
            showFieldErrors(res.campos);
            if (res.campos.nome || res.campos.telefone || res.campos.email) { goTo(5); showFieldErrors(res.campos); }
          } else if (/horário/i.test(res.erro || '')) {
            // Horário foi ocupado por outra pessoa: volta para escolher outro
            state.hora = null;
            setTimeout(() => { goTo(4); }, 1400);
          }
          return;
        }
        showSuccess(res);
      })
      .catch(() => {
        errorBox.textContent = 'Falha de conexão. Verifique sua internet e tente novamente.';
        errorBox.hidden = false;
      })
      .finally(() => { btnSubmit.disabled = false; btnSubmit.classList.remove('is-loading'); });
  });

  function showSuccess(res) {
    const box = document.querySelector('[data-success]');
    Object.entries(res.agendamento).forEach(([k, v]) => box.querySelectorAll('[data-ok="' + k + '"]').forEach((el) => { el.textContent = k === 'protocolo' ? '#' + v : v; }));
    box.querySelector('[data-ok-whatsapp]').href = res.whatsapp_url;
    box.querySelector('[data-ok-cancel]').href = res.cancelar_url;
    document.querySelector('.booking-layout').hidden = true;
    document.querySelector('.stepper').hidden = true;
    document.querySelector('.progress').hidden = true;
    document.querySelector('[data-mobile-summary]').hidden = true;
    box.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /* ---------- Pré-seleção via URL (?servico= / ?barbeiro=) ---------- */
  const preS = form.querySelector('input[name="servico_id"]:checked');
  const preB = form.querySelector('input[name="barbeiro_id"]:checked');
  if (preS) state.servico = Number(preS.value);
  if (preB) state.barbeiro = Number(preB.value);
  goTo(state.servico ? (state.barbeiro !== null ? 3 : 2) : 1);
})();
