const agendaEl = document.getElementById('agenda');
const paginacaoEl = document.getElementById('paginacao');
const linkTodos = document.getElementById('linkTodos');
const btnConfirmarChegada = document.getElementById('btnConfirmarChegada');
const btnDoacaoRealizada = document.getElementById('btnDoacaoRealizada');
const attendanceFeedback = document.getElementById('attendanceFeedback');

const agendamentos = [
  { nome: 'Maria Silva', horario: '08:00', status: 'Finalizado' },
  { nome: 'Jesse Pinkman', horario: '09:30', status: 'Agendado' },
  { nome: 'Ana Souza', horario: '10:15', status: 'Agendado' },
  { nome: 'Carlos Pereira', horario: '11:00', status: 'Agendado' },
  { nome: 'Fernanda Costa', horario: '13:30', status: 'Atrasado' },
  { nome: 'Pedro Almeida', horario: '14:45', status: 'Agendado' },
  { nome: 'Juliana Ramos', horario: '16:00', status: 'Agendado' },
  { nome: 'Roberto Nunes', horario: '17:30', status: 'Agendado' },
];

const ITENS_POR_PAGINA = 6;
const STATUS_CLASSE = { Agendado: 'badge-status--agendado', 'Em Andamento': 'badge-status--em-andamento', Atrasado: 'badge-status--atrasado', Finalizado: 'badge-status--finalizado' };
let paginaAtual = 1;
let atendimentoConfirmado = false;

function iniciaisDe(nome) {
  const partes = nome.trim().split(/\s+/);
  return (partes[0].charAt(0) + partes.at(-1).charAt(0)).toUpperCase();
}

function renderizarAgenda() {
  const inicio = (paginaAtual - 1) * ITENS_POR_PAGINA;
  const itensPagina = agendamentos.slice(inicio, inicio + ITENS_POR_PAGINA);
  agendaEl.innerHTML = itensPagina.map((item) => `
    <li class="agenda__item">
      <span class="agenda__iniciais">${iniciaisDe(item.nome)}</span>
      <span class="agenda__nome">${item.nome}</span><span class="agenda__horario">${item.horario}</span>
      <span class="badge-status ${STATUS_CLASSE[item.status] || ''}">${item.status}</span>
    </li>`).join('');
  renderizarPaginacao();
}

function renderizarPaginacao() {
  const totalPaginas = Math.ceil(agendamentos.length / ITENS_POR_PAGINA);
  let html = `<button class="paginacao__botao" data-pagina="anterior" ${paginaAtual === 1 ? 'disabled' : ''} aria-label="Página anterior">&#8249;</button>`;
  for (let pagina = 1; pagina <= totalPaginas; pagina += 1) html += `<button class="paginacao__botao${pagina === paginaAtual ? ' paginacao__botao--ativo' : ''}" data-pagina="${pagina}">${pagina}</button>`;
  paginacaoEl.innerHTML = `${html}<button class="paginacao__botao" data-pagina="proxima" ${paginaAtual === totalPaginas ? 'disabled' : ''} aria-label="Próxima página">&#8250;</button>`;
}

paginacaoEl.addEventListener('click', (event) => {
  const botao = event.target.closest('.paginacao__botao');
  if (!botao || botao.disabled) return;
  const totalPaginas = Math.ceil(agendamentos.length / ITENS_POR_PAGINA);
  paginaAtual = botao.dataset.pagina === 'anterior' ? paginaAtual - 1 : botao.dataset.pagina === 'proxima' ? paginaAtual + 1 : Number(botao.dataset.pagina);
  paginaAtual = Math.max(1, Math.min(paginaAtual, totalPaginas));
  renderizarAgenda();
});

btnConfirmarChegada.addEventListener('click', () => {
  if (atendimentoConfirmado) return;
  atendimentoConfirmado = true;
  agendamentos[1].status = 'Em Andamento';
  btnConfirmarChegada.textContent = 'Presença confirmada';
  btnConfirmarChegada.disabled = true;
  btnDoacaoRealizada.disabled = false;
  attendanceFeedback.textContent = 'Presença de Jesse Pinkman confirmada. Agora registre o resultado da doação.';
  atualizarEtapa(1);
  renderizarAgenda();
});

btnDoacaoRealizada.addEventListener('click', () => {
  if (!atendimentoConfirmado || !window.confirm('Confirmar que a doação de Jesse Pinkman foi realizada?')) return;
  agendamentos[1].status = 'Finalizado';
  btnDoacaoRealizada.textContent = 'Doação confirmada';
  btnDoacaoRealizada.disabled = true;
  attendanceFeedback.textContent = 'Doação confirmada com sucesso. O status do atendimento foi atualizado para concluído.';
  atualizarEtapa(2);
  renderizarAgenda();
});

function atualizarEtapa(etapaAtual) {
  document.querySelectorAll('.step').forEach((step, indice) => {
    const circle = step.querySelector('.step__circle');
    const label = step.querySelector('.step__label');
    circle.classList.toggle('step__circle--done', indice < etapaAtual);
    circle.classList.toggle('step__circle--current', indice === etapaAtual);
    label.classList.toggle('step__label--current', indice === etapaAtual);
    if (indice < etapaAtual) circle.innerHTML = '&#10003;';
  });
  document.querySelectorAll('.step__line').forEach((line, indice) => line.classList.toggle('step__line--done', indice < etapaAtual));
}

linkTodos.textContent = 'Ver todos os agendamentos';
renderizarAgenda();
