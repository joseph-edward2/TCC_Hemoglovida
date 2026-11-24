(function () {
  'use strict';

  const $ = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  const DIAS  = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  const LINHA_DIA = [1, 2, 3, 4, 5, 6, 0];

  const diaUtil = { open: true, start: '07:00', end: '18:00', cap: 60 };
  const state = {
    pattern: agenda.settings?.pattern ?? [
      { open: false, start: '00:00', end: '00:00', cap: 0 },
      ...Array.from({ length: 5 }, () => ({ ...diaUtil })),
      { ...diaUtil, end: '12:00', cap: 30 },
    ],
    overrides: Object.fromEntries((agenda.settings?.overrides ?? []).map(({ date, ...day }) => [date, day])),
    blocked: agenda.settings?.blocked ?? [],
  };

  let selecionado = agenda.date;
  const [anoInicial, mesInicial] = selecionado.split('-').map(Number);
  let view = { ano: anoInicial, mes: mesInicial - 1 };
  let agendamentos = agenda.bookings;
  let versao = agenda.revision;
  const painel = $('.dashboard');

  const pad = (n) => String(n).padStart(2, '0');
  const chave = (a, m, d) => `${a}-${pad(m + 1)}-${pad(d)}`;
  const diaSemana = (a, m, d) => new Date(a, m, d).getDay();

  function paraMinutos(str) {
    if (!str) return null;
    const [hora, minuto] = str.split(':').map(Number);
    return hora * 60 + minuto;
  }
  const paraHora = (min) => `${pad(Math.floor(min / 60))}:${pad(min % 60)}`;

  function gerarHorarios(p) {
    if (!p.open) return [];
    const ini = paraMinutos(p.start);
    const fim = paraMinutos(p.end);
    if (ini === null || fim === null || fim <= ini) return [];
    const slots = Math.max(1, Math.ceil((fim - ini) / 60));
    const base = Math.floor(p.cap / slots);
    let resto = p.cap - base * slots;
    const lista = [];
    for (let t = ini; t < fim; t += 60) {
      let vagas = base;
      if (resto > 0) { vagas += 1; resto -= 1; }
      lista.push({ start: paraHora(t), end: paraHora(Math.min(t + 60, fim)), vagas, active: true });
    }
    return lista;
  }

  function configDoDia(data) {
    const [a, m, d] = data.split('-').map(Number);
    const dw = diaSemana(a, m - 1, d);
    if (state.blocked.includes(data)) return { open: false, horarios: [], bloqueado: true, dw };
    const ov = state.overrides[data];
    if (ov) return { open: ov.open, horarios: ov.horarios, bloqueado: false, dw };
    const p = state.pattern[dw];
    return { open: p.open, horarios: gerarHorarios(p), bloqueado: false, dw };
  }

  function overrideDoDia(data) {
    if (!state.overrides[data]) {
      const c = configDoDia(data);
      state.overrides[data] = { open: c.open, horarios: c.horarios.map((h) => ({ ...h })) };
    }
    return state.overrides[data];
  }

  function ocupadosNoHorario(data, horario) {
    return Object.entries(agendamentos[data] ?? {})
      .filter(([hora]) => hora >= horario.start && hora < horario.end)
      .reduce((total, [, quantidade]) => total + quantidade, 0);
  }

  function vagasLivres(data, config) {
    if (!config.open) return 0;
    return config.horarios.filter((h) => h.active)
      .reduce((total, h) => total + Math.max(0, h.vagas - ocupadosNoHorario(data, h)), 0);
  }

  function desenharCalendario() {
    const grid = $('.calendar-grid');
    $('.calendar-header h2').textContent = `${MESES[view.mes]} ${view.ano}`;
    const inicio = new Date(view.ano, view.mes, 1 - new Date(view.ano, view.mes, 1).getDay());
    grid.innerHTML = '';
    for (let i = 0; i < 42; i += 1) {
      const d = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate() + i);
      const data = chave(d.getFullYear(), d.getMonth(), d.getDate());
      const cel = document.createElement('div');
      cel.className = 'calendar-day';
      cel.textContent = pad(d.getDate());

      if (d.getMonth() !== view.mes) {
        cel.classList.add('is-outside');
      } else {
        const c = configDoDia(data);
        if (data === selecionado) cel.classList.add('is-selected');
        if (vagasLivres(data, c) === 0) {
          cel.classList.add('is-indisponivel');
        } else {
          const dot = document.createElement('span');
          dot.className = 'dot';
          cel.appendChild(dot);
        }
        cel.addEventListener('click', () => selecionar(data));
      }
      grid.appendChild(cel);
    }
  }

  async function mudarMes(delta) {
    const data = new Date(view.ano, view.mes + delta, 1);
    const primeiroDia = chave(data.getFullYear(), data.getMonth(), 1);
    const url = new URL(painel.dataset.calendarUrl);
    url.searchParams.set('mes', primeiroDia.slice(0, 7));
    const botoes = $$('.calendar-nav button');
    botoes.forEach((btn) => { btn.disabled = true; });
    try {
      const resposta = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!resposta.ok) throw new Error();
      const resultado = await resposta.json();
      agendamentos = resultado.bookings;
      view = { ano: data.getFullYear(), mes: data.getMonth() };
      selecionar(primeiroDia);
    } catch {
      alert('Não foi possível carregar os agendamentos do mês.');
    } finally {
      botoes.forEach((btn) => { btn.disabled = false; });
    }
  }

  function selecionar(data) {
    selecionado = data;
    desenharCalendario();
    desenharPainel();
  }

  function desenharPainel() {
    const c = configDoDia(selecionado);
    const [, m, d] = selecionado.split('-').map(Number);

    $('.day-config-head h2').textContent = `Configurar ${pad(d)} de ${MESES[m - 1]}`;
    $('.day-config-head p').textContent = DIAS[c.dw];

    const chk = $('.toggle-box .switch input');
    chk.checked = c.open && !c.bloqueado;
    chk.disabled = c.bloqueado;

    desenharHorarios(c);
    atualizarTotal(c);
  }

  function habilitarHorarios(v) {
    $$('.horario-vagas').forEach((i) => { i.disabled = !v; });
    $$('.horario-row .switch input').forEach((i) => { i.disabled = !v; });
    $$('.horario-remove').forEach((b) => { b.disabled = !v; });
    $('.btn-dashed').disabled = !v || configDoDia(selecionado).horarios.at(-1)?.end === '23:59';
  }

  function desenharHorarios(c) {
    const lista = $('.horario-list');
    lista.innerHTML = '';
    if (c.bloqueado || !c.open) {
      const msg = c.bloqueado ? 'Este dia está bloqueado para agendamentos.' : 'Hemocentro fechado neste dia.';
      lista.innerHTML = `<p style="color:var(--ink-soft);font-size:13px;">${msg}</p>`;
      habilitarHorarios(false);
      return;
    }
    c.horarios.forEach((h, i) => {
      const row = document.createElement('div');
      row.className = 'horario-row';
      row.dataset.i = i;
      row.innerHTML = `
        <div class="horario-time">${h.start} - ${h.end}<small>${ocupadosNoHorario(selecionado, h)} agendamento(s)</small></div>
        <input class="horario-vagas" type="text" value="${h.vagas}">
        <label class="switch"><input type="checkbox" ${h.active ? 'checked' : ''}><span class="track"></span></label>
        <button type="button" class="horario-remove" title="Remover" style="border:none;background:none;color:var(--brand);cursor:pointer;font-size:14px;">✕</button>
      `;
      lista.appendChild(row);
    });
    habilitarHorarios(true);
  }

  function atualizarTotal(c) {
    c = c || configDoDia(selecionado);
    const total = (c.open && !c.bloqueado)
      ? c.horarios.filter((h) => h.active).reduce((s, h) => s + (Number(h.vagas) || 0), 0)
      : 0;
    const ocupados = Object.values(agendamentos[selecionado] ?? {}).reduce((soma, n) => soma + n, 0);
    $('.day-config-subhead .total').innerHTML = `${total} vagas no total<br>${ocupados} agendamento(s)<br>${vagasLivres(selecionado, c)} vagas livres`;
  }

  $('.toggle-box .switch input').addEventListener('change', (e) => {
    const ov = overrideDoDia(selecionado);
    ov.open = e.target.checked;
    if (ov.open && !ov.horarios.length) {
      const [a, m, d] = selecionado.split('-').map(Number);
      ov.horarios = gerarHorarios(state.pattern[diaSemana(a, m - 1, d)]);
    }
    desenharCalendario();
    desenharPainel();
  });

  $('.horario-list').addEventListener('input', (e) => {
    if (e.target.classList.contains('horario-vagas')) {
      e.target.value = e.target.value.replace(/\D/g, '');
    }
  });
  $('.horario-list').addEventListener('change', (e) => {
    const row = e.target.closest('.horario-row');
    if (!row) return;
    const h = overrideDoDia(selecionado).horarios[row.dataset.i];
    if (!h) return;
    if (e.target.classList.contains('horario-vagas')) {
      h.vagas = parseInt(e.target.value, 10) || 0;
      e.target.value = h.vagas;
    } else {
      h.active = e.target.checked;
    }
    atualizarTotal();
    desenharCalendario();
  });
  $('.horario-list').addEventListener('click', (e) => {
    if (!e.target.closest('.horario-remove')) return;
    const row = e.target.closest('.horario-row');
    overrideDoDia(selecionado).horarios.splice(row.dataset.i, 1);
    desenharHorarios(configDoDia(selecionado));
    atualizarTotal();
    desenharCalendario();
  });

  $('.btn-dashed').addEventListener('click', () => {
    const ov = overrideDoDia(selecionado);
    if (!ov.open) return;
    const ultimo = ov.horarios[ov.horarios.length - 1];
    const inicio = ultimo ? ultimo.end : '08:00';
    const minuto = paraMinutos(inicio);
    if (minuto >= 1439) return;
    ov.horarios.push({ start: inicio, end: paraHora(Math.min(minuto + 60, 1439)), vagas: 0, active: true });
    desenharHorarios(configDoDia(selecionado));
    atualizarTotal();
    desenharCalendario();
  });

  const [btnBloquear, btnLiberar, btnAplicar] = $$('.toolbar button');
  btnBloquear.addEventListener('click', () => {
    if (!state.blocked.includes(selecionado)) state.blocked.push(selecionado);
    desenharCalendario();
    desenharPainel();
  });
  btnLiberar.addEventListener('click', () => {
    state.blocked = state.blocked.filter((d) => d !== selecionado);
    desenharCalendario();
    desenharPainel();
  });
  btnAplicar.addEventListener('click', () => {
    if (!confirm('Remover as exceções manuais do mês exibido e aplicar o padrão semanal?')) return;
    Object.keys(state.overrides).forEach((d) => {
      const [a, m] = d.split('-').map(Number);
      if (a === view.ano && m - 1 === view.mes) delete state.overrides[d];
    });
    state.blocked = state.blocked.filter((d) => {
      const [a, m] = d.split('-').map(Number);
      return !(a === view.ano && m - 1 === view.mes);
    });
    desenharCalendario();
    desenharPainel();
  });

  const [btnAnterior, btnProximo] = $$('.calendar-nav button');
  btnAnterior.addEventListener('click', () => mudarMes(-1));
  btnProximo.addEventListener('click', () => mudarMes(1));

  function desenharTabelaSemanal() {
    $$('.weekly-table tbody tr').forEach((row, i) => {
      const p = state.pattern[LINHA_DIA[i]];
      const status = row.querySelector('.status-check');
      const [ini, fim, cap] = row.querySelectorAll('input');
      row.classList.toggle('is-disabled', !p.open);
      status.setAttribute('aria-pressed', String(p.open));
      status.className = p.open ? 'status-check is-open' : 'status-check is-closed';
      status.innerHTML = p.open
        ? '<span class="check-box is-checked"><i class="ph-bold ph-check"></i></span> Aberto'
        : '<span class="check-box"></span> Fechado';
      ini.value = p.start; ini.disabled = !p.open;
      fim.value = p.end;   fim.disabled = !p.open;
      cap.value = p.cap;   cap.disabled = !p.open;
    });
  }

  $$('.weekly-table tbody tr').forEach((row, i) => {
    const dw = LINHA_DIA[i];
    const status = row.querySelector('.status-check');
    const [ini, fim, cap] = row.querySelectorAll('input');

    status.style.cursor = 'pointer';
    status.addEventListener('click', () => {
      state.pattern[dw].open = !state.pattern[dw].open;
      desenharTabelaSemanal();
      desenharCalendario();
      desenharPainel();
    });
    ini.addEventListener('change', () => {
      state.pattern[dw].start = ini.value.trim();
      desenharCalendario();
      desenharPainel();
    });
    fim.addEventListener('change', () => {
      state.pattern[dw].end = fim.value.trim();
      desenharCalendario();
      desenharPainel();
    });
    cap.addEventListener('input', () => { cap.value = cap.value.replace(/\D/g, ''); });
    cap.addEventListener('change', () => {
      state.pattern[dw].cap = parseInt(cap.value, 10) || 0;
      desenharCalendario();
      desenharPainel();
    });
  });

  $('.page-actions .btn-primary').addEventListener('click', async (e) => {
    const botao = e.currentTarget;
    botao.disabled = true;
    try {
      const resposta = await fetch(painel.dataset.saveUrl, {
        method: 'PUT',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          revision: versao,
          pattern: state.pattern,
          blocked: state.blocked,
          overrides: Object.entries(state.overrides).map(([date, day]) => ({ date, ...day })),
        }),
      });
      const resultado = await resposta.json();
      if (!resposta.ok) {
        alert(Object.values(resultado.errors ?? {}).flat()[0] ?? resultado.message ?? 'Não foi possível salvar a agenda.');
        return;
      }
      versao = resultado.revision;
      alert('Alterações salvas com sucesso!');
    } catch {
      alert('Não foi possível confirmar o salvamento da agenda.');
    } finally {
      botao.disabled = false;
    }
  });

  desenharCalendario();
  desenharPainel();
  desenharTabelaSemanal();
})();
