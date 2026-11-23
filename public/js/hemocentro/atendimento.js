const agendaEl = document.getElementById('agenda');
const agendamentos = Array.from(agendaEl.querySelectorAll('.agenda__item'));
const paginacaoEl = document.getElementById('paginacao');
const btnConfirmarChegada = document.getElementById('btnConfirmarChegada');
const btnDoacaoRealizada = document.getElementById('btnDoacaoRealizada');
const atendimentoErro = document.getElementById('atendimentoErro');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const badgeHorarioEl = document.getElementById('badgeHorario');
const pacienteIniciaisEl = document.getElementById('pacienteIniciais');
const pacienteNomeEl = document.getElementById('pacienteNome');
const pacienteTipoEl = document.getElementById('pacienteTipo');
const pacienteCpfEl = document.getElementById('pacienteCpf');
const passos = document.querySelectorAll('.stepper .step');
const linhas = document.querySelectorAll('.stepper .step__line');
const ITENS_POR_PAGINA = 6;

const situacoes = new Map([
  ['agendado', { rotulo: 'Agendado', classe: 'agendado', etapa: 1 }],
  ['atrasado', { rotulo: 'Atrasado', classe: 'atrasado', etapa: 1 }],
  ['chegou', { rotulo: 'Em Andamento', classe: 'em-andamento', etapa: 2 }],
  ['concluido', { rotulo: 'Finalizado', classe: 'finalizado', etapa: 3 }]
]);
const indisponivel = { rotulo: 'Indisponível', classe: 'agendado', etapa: 0 };

let pacienteAtual = agendamentos.find(item => item.dataset.status === 'chegou')
  ?? agendamentos.find(item => situacoes.get(item.dataset.status)?.etapa === 1)
  ?? agendamentos[0];
let paginaAtual = Math.floor(agendamentos.indexOf(pacienteAtual) / ITENS_POR_PAGINA) + 1;
let enviando = false;

function iniciaisDe(nome) {
  const partes = nome.trim().split(/\s+/);
  return (partes[0].charAt(0) + partes.at(-1).charAt(0)).toUpperCase();
}

function renderizarAgenda() {
  const inicio = (paginaAtual - 1) * ITENS_POR_PAGINA;
  agendamentos.forEach((item, indice) => {
    item.hidden = indice < inicio || indice >= inicio + ITENS_POR_PAGINA;
    item.classList.toggle('agenda__item--atual', item === pacienteAtual);
    item.setAttribute('aria-pressed', String(item === pacienteAtual));
    item.querySelector('.agenda__iniciais').textContent = iniciaisDe(item.querySelector('.agenda__nome').textContent);

    const situacao = situacoes.get(item.dataset.status) ?? indisponivel;
    const badge = item.querySelector('.badge-status');
    badge.className = 'badge-status badge-status--' + situacao.classe;
    badge.textContent = situacao.rotulo;
  });

  renderizarPaginacao();
}

function renderizarPaginacao() {
  const totalPaginas = Math.ceil(agendamentos.length / ITENS_POR_PAGINA);
  if (totalPaginas <= 1) return;

  let html = `<button type="button" class="paginacao__botao" data-pagina="${paginaAtual - 1}" aria-label="Página anterior" ${paginaAtual === 1 ? 'disabled' : ''}>&#8249;</button>`;

  for (let pagina = 1; pagina <= totalPaginas; pagina++) {
    const ativa = pagina === paginaAtual;
    html += `<button type="button" class="paginacao__botao${ativa ? ' paginacao__botao--ativo' : ''}" data-pagina="${pagina}" ${ativa ? 'aria-current="page"' : ''}>${pagina}</button>`;
  }

  html += `<button type="button" class="paginacao__botao" data-pagina="${paginaAtual + 1}" aria-label="Próxima página" ${paginaAtual === totalPaginas ? 'disabled' : ''}>&#8250;</button>`;
  paginacaoEl.innerHTML = html;
}

function renderizarPaciente() {
  const nome = pacienteAtual.querySelector('.agenda__nome').textContent;
  badgeHorarioEl.textContent = pacienteAtual.querySelector('.agenda__horario').textContent;
  pacienteIniciaisEl.textContent = iniciaisDe(nome);
  pacienteNomeEl.textContent = nome;
  pacienteTipoEl.textContent = 'Tipo Sanguíneo: ' + pacienteAtual.dataset.tipo;
  pacienteCpfEl.textContent = 'CPF: ' + pacienteAtual.dataset.cpf;
}

function renderizarPassos() {
  const { etapa } = situacoes.get(pacienteAtual.dataset.status) ?? indisponivel;

  passos.forEach((passo, indice) => {
    const circulo = passo.querySelector('.step__circle');
    const rotulo = passo.querySelector('.step__label');
    const concluido = indice < etapa;
    const atual = indice === etapa;

    circulo.classList.toggle('step__circle--done', concluido);
    circulo.classList.toggle('step__circle--current', atual);
    circulo.textContent = concluido ? '✓' : '';
    rotulo.classList.toggle('step__label--current', atual);
  });

  linhas.forEach((linha, indice) => {
    linha.classList.toggle('step__line--done', indice < etapa);
  });

  btnConfirmarChegada.disabled = enviando || etapa !== 1;
  btnDoacaoRealizada.disabled = enviando || etapa !== 2;
}

async function executarEtapa(url) {
  enviando = true;
  atendimentoErro.hidden = true;
  renderizarPassos();

  try {
    const resposta = await fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken }
    });

    if (!resposta.ok) {
      atendimentoErro.textContent = resposta.status === 409
        ? 'O status do agendamento mudou. Atualize a página e tente novamente.'
        : 'Não foi possível salvar o atendimento. Tente novamente.';
      atendimentoErro.hidden = false;
      return;
    }

    const { status } = await resposta.json();
    pacienteAtual.dataset.status = status;
    renderizarAgenda();
  } catch {
    atendimentoErro.textContent = 'Não foi possível salvar o atendimento. Tente novamente.';
    atendimentoErro.hidden = false;
  } finally {
    enviando = false;
    renderizarPassos();
  }
}

function tratarSelecaoAgenda(event) {
  const item = event.target.closest('.agenda__item');
  if (enviando || !item) return;

  pacienteAtual = item;
  atendimentoErro.hidden = true;
  renderizarPaciente();
  renderizarAgenda();
  renderizarPassos();
}

paginacaoEl.addEventListener('click', (event) => {
  const botao = event.target.closest('.paginacao__botao');
  if (!botao || botao.disabled) return;

  paginaAtual = Number(botao.dataset.pagina);
  renderizarAgenda();
});

btnConfirmarChegada.addEventListener('click', () => executarEtapa(pacienteAtual.dataset.chegada));
btnDoacaoRealizada.addEventListener('click', () => executarEtapa(pacienteAtual.dataset.concluir));
agendaEl.addEventListener('click', tratarSelecaoAgenda);
agendaEl.addEventListener('keydown', (event) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    tratarSelecaoAgenda(event);
  }
});

renderizarPaciente();
renderizarAgenda();
renderizarPassos();
