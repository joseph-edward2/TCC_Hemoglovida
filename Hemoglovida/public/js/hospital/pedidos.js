/* ======================================================
   HEMOGLOVIDA — PEDIDOS (HOSPITAL)
   ======================================================
   Todos os dados vêm das rotas JSON da aplicação. A página
   só monta nós com document.createElement/textContent.
   ====================================================== */

const btnNovaRequisicao = document.getElementById('btnNovaRequisicao');
const formNovaRequisicao = document.getElementById('formNovaRequisicao');
const formRequisicao = document.getElementById('formRequisicao');
const msgEl = document.getElementById('msg');

const resumoEl = document.getElementById('resumoPedidos');
const listaPedidosEl = document.getElementById('listaPedidos');
const listaHemocentrosEl = document.getElementById('listaHemocentros');
const buscaHemocentroEl = document.getElementById('buscaHemocentro');
const hemocentroBuscaEl = document.getElementById('hemocentroBusca');
const paginacaoEl = document.getElementById('paginacaoMonitor');
const msgMonitorEl = document.getElementById('msgMonitor');
const atualizadoEl = document.getElementById('atualizadoEm');
const btnAtualizar = document.getElementById('btnAtualizar');

const tipoSanguineoEl = document.getElementById('tipoSanguineo');
const tipoSanguineoValor = document.getElementById('tipoSanguineoValor');
const qtyInput = document.getElementById('qtyInput');
const qtyMenos = document.getElementById('qtyMenos');
const qtyMais = document.getElementById('qtyMais');

const inputArquivo = document.getElementById('inputArquivo');
const btnTrocarArquivo = document.getElementById('btnTrocarArquivo');
const nomeArquivo = document.getElementById('nomeArquivo');
const metaArquivo = document.getElementById('metaArquivo');

const overlayChegada = document.getElementById('overlayChegada');
const btnFecharChegada = document.getElementById('btnFecharChegada');
const resumoChegadaEl = document.getElementById('resumoChegada');
const listaBolsasChegadaEl = document.getElementById('listaBolsasChegada');
const conferiBolsasEl = document.getElementById('conferiBolsas');
const btnConfirmarRecebimento = document.getElementById('btnConfirmarRecebimento');
const msgChegadaEl = document.getElementById('msgChegada');

const urls = {
  resumo: resumoEl ? resumoEl.dataset.url : '',
  pedidos: listaPedidosEl ? listaPedidosEl.dataset.url : '',
  hemocentros: listaHemocentrosEl ? listaHemocentrosEl.dataset.url : '',
  bolsas: listaPedidosEl ? listaPedidosEl.dataset.bolsasUrl : '',
  confirmar: listaPedidosEl ? listaPedidosEl.dataset.confirmarUrl : ''
};

const tiposValidos = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
const modosDoc = new Map();

let paginaAtual = 1;
let pedidoSelecionado = null;
let hemocentroSelecionado = '';
let temporizadorHemocentros = null;
let hemocentrosCache = [];
let hemocentrosVisiveis = [];
let indiceHemocentroAtivo = -1;
let dropdownHemocentroAberto = false;
let suprimirAberturaNoFoco = false;

function criarElemento(tag, classe, texto) {
  const elemento = document.createElement(tag);

  if (classe) {
    elemento.className = classe;
  }

  if (texto !== undefined && texto !== null) {
    elemento.textContent = String(texto);
  }

  return elemento;
}

function limpar(elemento) {
  if (!elemento) return;

  while (elemento.firstChild) {
    elemento.removeChild(elemento.firstChild);
  }
}

function doisDigitos(valor) {
  return String(Number(valor) || 0).padStart(2, '0');
}

function normalizarTexto(valor) {
  return String(valor || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

function formatarHora(valor) {
  if (!valor) return '--:--';

  const data = new Date(valor);

  if (Number.isNaN(data.getTime())) return '--:--';

  return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function formatarData(valor) {
  if (!valor) return '—';

  const partes = String(valor).slice(0, 10).split('-');

  if (partes.length === 3) {
    return partes[2] + '/' + partes[1] + '/' + partes[0];
  }

  return valor;
}

function selecionarTipo(tipo) {
  if (!tipoSanguineoEl || !tipoSanguineoValor) return;

  tipoSanguineoEl.querySelectorAll('.blood-select__btn').forEach(function (botao) {
    botao.classList.toggle(
      'blood-select__btn--active',
      botao.textContent.trim() === tipo,
    );
  });

  tipoSanguineoValor.value = tipo;
}

function agendarCarregamentoHemocentros() {
  if (temporizadorHemocentros) {
    clearTimeout(temporizadorHemocentros);
  }

  temporizadorHemocentros = setTimeout(function () {
    temporizadorHemocentros = null;
    carregarHemocentros();
  }, 250);
}

function abrirDropdownHemocentros() {
  if (!listaHemocentrosEl) return;

  dropdownHemocentroAberto = true;
  listaHemocentrosEl.hidden = false;

  if (buscaHemocentroEl) {
    buscaHemocentroEl.setAttribute('aria-expanded', 'true');
  }
}

function fecharDropdownHemocentros() {
  dropdownHemocentroAberto = false;
  indiceHemocentroAtivo = -1;

  if (listaHemocentrosEl) {
    listaHemocentrosEl.hidden = true;
  }

  if (buscaHemocentroEl) {
    buscaHemocentroEl.setAttribute('aria-expanded', 'false');
  }
}

function atualizarDestaqueHemocentros() {
  if (!listaHemocentrosEl) return;

  listaHemocentrosEl.querySelectorAll('.hemo-option').forEach(function (opcao, indice) {
    opcao.classList.toggle('hemo-option--ativo', indice === indiceHemocentroAtivo);
  });

  const ativo = listaHemocentrosEl.querySelector('.hemo-option--ativo');

  if (ativo) {
    ativo.scrollIntoView({ block: 'nearest' });
  }
}

function selecionarHemocentro(hemocentro) {
  hemocentroSelecionado = hemocentro.cnes_hem;

  if (buscaHemocentroEl) {
    buscaHemocentroEl.value = hemocentro.rotulo;
  }

  suprimirAberturaNoFoco = true;
  fecharDropdownHemocentros();

  if (buscaHemocentroEl) {
    buscaHemocentroEl.focus({ preventScroll: true });
  }
}

function renderizarHemocentros() {
  if (!listaHemocentrosEl) return;

  limpar(listaHemocentrosEl);
  hemocentrosVisiveis = [];

  const termo = normalizarTexto(buscaHemocentroEl ? buscaHemocentroEl.value : '');

  if (!termo) {
    fecharDropdownHemocentros();

    return;
  }

  hemocentrosVisiveis = hemocentrosCache.filter(function (hemocentro) {
    return normalizarTexto([
      hemocentro.rotulo,
      hemocentro.cnes_hem,
      hemocentro.contato,
    ].join(' ')).includes(termo);
  });

  if (hemocentrosVisiveis.length === 0) {
    const mensagem = hemocentrosCache.length === 0
      ? 'Nenhum hemocentro disponível.'
      : 'Nenhum hemocentro encontrado.';

    listaHemocentrosEl.appendChild(criarElemento('p', 'form-hint', mensagem));
    abrirDropdownHemocentros();

    return;
  }

  hemocentrosVisiveis.forEach(function (hemocentro) {
    const opcao = criarElemento('label', 'hemo-option');
    const radio = criarElemento('input', 'hemo-option__radio');
    const detalhes = criarElemento('div', '');
    const situacao = hemocentro.estoque && hemocentro.estoque.situacao;

    radio.type = 'radio';
    radio.name = 'cnes_hem';
    radio.value = hemocentro.cnes_hem;
    radio.checked = hemocentro.cnes_hem === hemocentroSelecionado;
    radio.disabled = situacao === 'Sem estoque';
    radio.addEventListener('change', function () {
      selecionarHemocentro(hemocentro);
    });

    detalhes.appendChild(criarElemento('div', 'hemo-option__name', hemocentro.rotulo));
    detalhes.appendChild(criarElemento(
      'div',
      'hemo-option__meta',
      'CNES ' + (hemocentro.cnes_hem || '—')
        + ' · ' + (hemocentro.endereco || 'Endereço não informado')
        + ' · ' + (hemocentro.contato || 'Sem contato'),
    ));

    if (situacao) {
      const classeSituacao = situacao === 'Estoque Disponível'
        ? 'hemo-option__status--disponivel'
        : situacao === 'Estoque Baixo'
          ? 'hemo-option__status--baixo'
          : 'hemo-option__status--sem-estoque';

      detalhes.appendChild(criarElemento(
        'div',
        'hemo-option__status ' + classeSituacao,
        situacao,
      ));
    }

    opcao.appendChild(radio);
    opcao.appendChild(detalhes);
    listaHemocentrosEl.appendChild(opcao);
  });

  if (indiceHemocentroAtivo >= hemocentrosVisiveis.length) {
    indiceHemocentroAtivo = hemocentrosVisiveis.length - 1;
  }

  abrirDropdownHemocentros();
  atualizarDestaqueHemocentros();
}

function navegarHemocentros(evento) {
  if (evento.key === 'Escape') {
    if (dropdownHemocentroAberto) {
      evento.preventDefault();
      fecharDropdownHemocentros();
    }

    return;
  }

  if (evento.key === 'Enter') {
    if (confirmarHemocentroAtivo()) {
      evento.preventDefault();
    }

    return;
  }

  if (evento.key !== 'ArrowDown' && evento.key !== 'ArrowUp') {
    return;
  }

  evento.preventDefault();

  if (!dropdownHemocentroAberto) {
    renderizarHemocentros();
  }

  if (hemocentrosVisiveis.length === 0) {
    return;
  }

  if (evento.key === 'ArrowDown') {
    indiceHemocentroAtivo = Math.min(indiceHemocentroAtivo + 1, hemocentrosVisiveis.length - 1);
  } else {
    indiceHemocentroAtivo = Math.max(indiceHemocentroAtivo - 1, 0);
  }

  atualizarDestaqueHemocentros();
}

function confirmarHemocentroAtivo() {
  if (!dropdownHemocentroAberto || hemocentrosVisiveis.length === 0) {
    return false;
  }

  const indice = indiceHemocentroAtivo >= 0 ? indiceHemocentroAtivo : 0;

  if (hemocentrosVisiveis[indice]) {
    selecionarHemocentro(hemocentrosVisiveis[indice]);

    return true;
  }

  return false;
}

async function carregarHemocentros() {
  if (!listaHemocentrosEl) return;

  const parametros = new URLSearchParams();
  const tipo = tipoSanguineoValor ? tipoSanguineoValor.value : '';
  const quantidade = qtyInput ? Number(qtyInput.value) : 1;

  if (tipo) parametros.set('tipo', tipo);
  if (quantidade >= 1 && quantidade <= 50) parametros.set('quantidade', String(quantidade));

  const sufixo = parametros.toString() ? '?' + parametros.toString() : '';

  try {
    hemocentrosCache = await buscarDaAPI(urls.hemocentros + sufixo);
    renderizarHemocentros();
  } catch (erro) {
    hemocentrosCache = [];
    hemocentrosVisiveis = [];
    limpar(listaHemocentrosEl);
    listaHemocentrosEl.appendChild(criarElemento('p', 'form-hint', erro.message));

    if (buscaHemocentroEl && buscaHemocentroEl.value.trim()) {
      abrirDropdownHemocentros();
    } else {
      fecharDropdownHemocentros();
    }
  }
}

function renderizarResumo(dados) {
  if (!resumoEl) return;

  const cartoes = [
    ['Requisições Pendentes', 'pendentes', false],
    ['Em Separação', 'em_separacao', false],
    ['Enviados', 'enviados', false],
    ['Críticos', 'criticos', true]
  ];

  limpar(resumoEl);

  cartoes.forEach(function ([rotulo, campo, critico]) {
    const cartao = criarElemento(
      'div',
      'summary-card' + (critico ? ' summary-card--critical' : ''),
    );
    const conteudo = criarElemento('div', '');

    conteudo.appendChild(criarElemento('div', 'summary-card__label', rotulo));
    conteudo.appendChild(criarElemento('div', 'summary-card__value', doisDigitos(dados[campo])));
    cartao.appendChild(conteudo);
    resumoEl.appendChild(cartao);
  });
}

function criarTimeline(pedido) {
  const celula = criarElemento('td', '');
  const timeline = criarElemento('div', 'timeline');
  const etapaAtual = Number(pedido.etapa) || 1;

  for (let passo = 1; passo <= 5; passo++) {
    let classeDot = 'timeline__dot';

    if (passo < etapaAtual) {
      classeDot += ' timeline__dot--done';
    } else if (passo === etapaAtual) {
      classeDot += ' timeline__dot--current';
    }

    timeline.appendChild(criarElemento('span', classeDot));

    if (passo < 5) {
      timeline.appendChild(criarElemento(
        'span',
        'timeline__line' + (passo < etapaAtual ? ' timeline__line--done' : ''),
      ));
    }
  }

  celula.appendChild(timeline);
  celula.appendChild(criarElemento('span', 'timeline__label', pedido.status_label));

  return celula;
}

function abrirPdf(pedido) {
  if (!pedido.url_requisicao) return;

  window.open(pedido.url_requisicao, '_blank', 'noopener');
}

function criarAcoesDoc(pedido) {
  const celula = criarElemento('td', 'doc-cell');
  const acoes = criarElemento('div', 'doc-actions');
  const botao = criarElemento('button', 'btn-outline-sm doc-btn');
  const seta = criarElemento('button', 'doc-arrow');

  botao.type = 'button';
  seta.type = 'button';
  seta.setAttribute('aria-label', 'Alternar entre confirmar chegada e ver PDF');

  let modo = modosDoc.get(pedido.id_ped) || (pedido.pode_confirmar_chegada ? 'confirmar' : 'pdf');

  function atualizarAcoes() {
    const podeAlternar = pedido.pode_confirmar_chegada;

    seta.hidden = !podeAlternar;
    botao.disabled = false;

    if (modo === 'confirmar' && podeAlternar) {
      botao.textContent = 'Confirmar chegada';
      seta.textContent = '→';
      botao.onclick = function () { abrirModalChegada(pedido); };
      return;
    }

    if (pedido.tem_requisicao) {
      botao.textContent = 'Ver PDF';
      botao.onclick = function () { abrirPdf(pedido); };
      seta.textContent = '←';
      return;
    }

    botao.textContent = 'Sem PDF';
    botao.disabled = true;
    botao.onclick = null;
  }

  seta.addEventListener('click', function () {
    if (!pedido.pode_confirmar_chegada) return;

    modo = modo === 'confirmar' ? 'pdf' : 'confirmar';
    modosDoc.set(pedido.id_ped, modo);
    atualizarAcoes();
  });

  atualizarAcoes();
  acoes.appendChild(botao);
  acoes.appendChild(seta);
  celula.appendChild(acoes);

  return celula;
}

function renderizarPedidos(dados) {
  if (!listaPedidosEl) return;

  limpar(listaPedidosEl);
  limpar(paginacaoEl);

  const pedidos = dados.data || [];

  if (pedidos.length === 0) {
    const linha = criarElemento('tr', '');
    const celula = criarElemento('td', 'lista-vazia', 'Nenhum pedido encontrado.');
    celula.colSpan = 5;
    linha.appendChild(celula);
    listaPedidosEl.appendChild(linha);
    renderizarPaginacao(dados);

    return;
  }

  pedidos.forEach(function (pedido) {
    const linha = criarElemento('tr', pedido.pode_confirmar_chegada ? 'linha-confirmavel' : '');

    const tipoCelula = criarElemento('td', '');
    tipoCelula.appendChild(criarElemento('span', 'blood-tag', pedido.tipo_sanguineo_ped));
    linha.appendChild(tipoCelula);

    const quantidade = criarElemento('td', '', pedido.quant_ped + ' unid.');
    linha.appendChild(quantidade);

    const urgenciaCelula = criarElemento('td', '');
    urgenciaCelula.appendChild(criarElemento(
      'span',
      'urgencia-tag urgencia-tag--' + pedido.urgencia_ped,
      String(pedido.urgencia_label || '').toUpperCase(),
    ));
    linha.appendChild(urgenciaCelula);

    linha.appendChild(criarTimeline(pedido));
    linha.appendChild(criarAcoesDoc(pedido));

    listaPedidosEl.appendChild(linha);
  });

  renderizarPaginacao(dados);
}

function renderizarPaginacao(dados) {
  if (!paginacaoEl) return;

  const meta = dados.meta || {};
  const total = Number(meta.total) || 0;
  const porPagina = Number(meta.per_page) || 20;
  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));

  paginaAtual = Number(meta.current_page) || paginaAtual;

  paginacaoEl.appendChild(criarElemento(
    'span',
    'paginacao__info',
    'Página ' + paginaAtual + ' de ' + totalPaginas + ' · ' + total + ' pedido(s)',
  ));

  function adicionarBotao(rotulo, pagina, classeExtra, desabilitado) {
    const botao = criarElemento('button', 'paginacao__botao ' + classeExtra, rotulo);
    botao.type = 'button';
    botao.disabled = desabilitado;
    botao.addEventListener('click', function () {
      if (!botao.disabled) carregarPedidos(pagina);
    });
    paginacaoEl.appendChild(botao);
  }

  adicionarBotao('‹', paginaAtual - 1, 'paginacao__botao--nav', paginaAtual <= 1);

  for (let pagina = 1; pagina <= totalPaginas; pagina++) {
    adicionarBotao(
      String(pagina),
      pagina,
      pagina === paginaAtual ? 'paginacao__botao--ativo' : '',
      pagina === paginaAtual,
    );
  }

  adicionarBotao('›', paginaAtual + 1, 'paginacao__botao--nav', paginaAtual >= totalPaginas);
}

async function carregarPedidos(pagina) {
  paginaAtual = Number(pagina) || 1;

  try {
    const consultas = [
      buscarDaAPI(urls.resumo),
      buscarDaAPI(urls.pedidos + '?por_pagina=5&pagina=' + paginaAtual),
    ];
    const [resumo, lista] = await Promise.all(consultas);

    renderizarResumo(resumo);
    renderizarPedidos(lista);

    if (atualizadoEl) {
      atualizadoEl.textContent = 'Atualizado às ' + formatarHora(resumo.atualizado_em);
    }

    if (msgMonitorEl) msgMonitorEl.hidden = true;
  } catch (erro) {
    if (msgMonitorEl) mostrarMensagem(msgMonitorEl, erro.message, 'error');
  }
}

function fecharModalChegada() {
  if (!overlayChegada) return;

  overlayChegada.classList.remove('show');
  overlayChegada.hidden = true;
  pedidoSelecionado = null;
}

async function abrirModalChegada(pedido) {
  if (!overlayChegada) return;

  pedidoSelecionado = pedido;

  if (resumoChegadaEl) {
    resumoChegadaEl.textContent = 'Pedido ' + pedido.id_ped
      + ' — ' + pedido.quant_ped + ' bolsa(s) solicitada(s)'
      + ' — ' + (pedido.bolsas_em_transito || 0) + ' bolsa(s) a caminho.';
  }

  limpar(listaBolsasChegadaEl);

  if (msgChegadaEl) msgChegadaEl.hidden = true;
  if (conferiBolsasEl) conferiBolsasEl.checked = false;
  if (btnConfirmarRecebimento) btnConfirmarRecebimento.disabled = true;

  overlayChegada.hidden = false;
  overlayChegada.classList.add('show');

  try {
    const url = urls.bolsas.replace('__ID__', String(pedido.id_ped));
    const bolsas = await buscarDaAPI(url);

    if (bolsas.length === 0) {
      listaBolsasChegadaEl.appendChild(
        criarElemento('li', 'modal__bolsa-vazia', 'Nenhuma bolsa em trânsito para este pedido.'),
      );
      return;
    }

    bolsas.forEach(function (bolsa) {
      const item = criarElemento('li', 'modal__bolsa');
      item.appendChild(criarElemento('strong', '', bolsa.isbt_formatado));
      item.appendChild(criarElemento(
        'span',
        '',
        (bolsa.tipo_bolsa || '—') + ' · Validade ' + formatarData(bolsa.data_venc_bolsa)
          + ' · ' + (bolsa.origem || '—'),
      ));
      listaBolsasChegadaEl.appendChild(item);
    });
  } catch (erro) {
    if (msgChegadaEl) mostrarMensagem(msgChegadaEl, erro.message, 'error');
  }
}

function reiniciarFormulario() {
  if (!formRequisicao) return;

  formRequisicao.reset();
  selecionarTipo('');
  hemocentroSelecionado = '';
  hemocentrosVisiveis = [];
  fecharDropdownHemocentros();

  if (nomeArquivo) nomeArquivo.textContent = 'Nenhum arquivo selecionado';
  if (metaArquivo) metaArquivo.textContent = 'Envie o PDF assinado da requisição';
  if (btnTrocarArquivo) btnTrocarArquivo.textContent = 'Escolher arquivo';
}

if (btnNovaRequisicao && formNovaRequisicao) {
  btnNovaRequisicao.addEventListener('click', function () {
    formNovaRequisicao.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

if (tipoSanguineoEl) {
  tipoSanguineoEl.addEventListener('click', function (evento) {
    const botao = evento.target.closest('.blood-select__btn');

    if (!botao) return;

    selecionarTipo(botao.textContent.trim());
    carregarHemocentros();
  });
}

if (qtyMenos && qtyInput) {
  qtyMenos.addEventListener('click', function () {
    const atual = Number(qtyInput.value) || 1;
    qtyInput.value = String(Math.max(1, atual - 1));
    agendarCarregamentoHemocentros();
  });
}

if (qtyMais && qtyInput) {
  qtyMais.addEventListener('click', function () {
    const atual = Number(qtyInput.value) || 0;
    qtyInput.value = String(Math.min(50, atual + 1));
    agendarCarregamentoHemocentros();
  });
}

if (qtyInput) {
  qtyInput.addEventListener('change', agendarCarregamentoHemocentros);
}

if (buscaHemocentroEl) {
  buscaHemocentroEl.addEventListener('input', function () {
    hemocentroSelecionado = '';
    indiceHemocentroAtivo = -1;
    renderizarHemocentros();
  });

  buscaHemocentroEl.addEventListener('focus', function () {
    if (suprimirAberturaNoFoco) {
      suprimirAberturaNoFoco = false;

      return;
    }

    if (buscaHemocentroEl.value.trim()) {
      renderizarHemocentros();
    }
  });

  buscaHemocentroEl.addEventListener('keydown', navegarHemocentros);
}

document.addEventListener('click', function (evento) {
  if (hemocentroBuscaEl && hemocentroBuscaEl.contains(evento.target)) {
    return;
  }

  fecharDropdownHemocentros();
});

if (btnTrocarArquivo && inputArquivo) {
  btnTrocarArquivo.addEventListener('click', function () {
    inputArquivo.click();
  });
}

if (inputArquivo) {
  inputArquivo.addEventListener('change', function () {
    const arquivo = inputArquivo.files[0];

    if (!arquivo) return;

    const tamanhoMB = (arquivo.size / (1024 * 1024)).toFixed(1);

    if (nomeArquivo) nomeArquivo.textContent = arquivo.name;
    if (metaArquivo) metaArquivo.textContent = 'Arquivo pronto para envio (' + tamanhoMB + ' MB)';
    if (btnTrocarArquivo) btnTrocarArquivo.textContent = 'Trocar arquivo';
  });
}

if (formRequisicao) {
  formRequisicao.addEventListener('submit', async function (evento) {
    evento.preventDefault();

    if (msgEl) msgEl.hidden = true;

    const tipo = tipoSanguineoValor ? tipoSanguineoValor.value : '';

    if (!tipo) {
      if (msgEl) mostrarMensagem(msgEl, 'Selecione o tipo sanguíneo requerido.', 'error');
      return;
    }

    if (!inputArquivo || inputArquivo.files.length === 0) {
      if (msgEl) mostrarMensagem(msgEl, 'Envie o PDF assinado da requisição.', 'error');
      return;
    }

    const botao = document.getElementById('btnEnviarRequisicao');
    const restaurar = iniciarCarregamento(botao, 'Enviando…');

    try {
      await enviarParaAPI(formRequisicao.dataset.url, new FormData(formRequisicao), 'POST');

      if (msgEl) mostrarMensagem(msgEl, 'Requisição enviada com sucesso!', 'success');
      reiniciarFormulario();
      await carregarPedidos(1);
      carregarHemocentros();
    } catch (erro) {
      if (msgEl) mostrarMensagem(msgEl, erro.message, 'error');
    } finally {
      restaurar();
    }
  });
}

if (btnAtualizar) {
  btnAtualizar.addEventListener('click', function () {
    carregarPedidos(paginaAtual);
  });
}

if (conferiBolsasEl && btnConfirmarRecebimento) {
  conferiBolsasEl.addEventListener('change', function () {
    btnConfirmarRecebimento.disabled = !conferiBolsasEl.checked;
  });
}

if (btnFecharChegada) {
  btnFecharChegada.addEventListener('click', fecharModalChegada);
}

if (overlayChegada) {
  overlayChegada.addEventListener('click', function (evento) {
    if (evento.target === overlayChegada) fecharModalChegada();
  });
}

if (btnConfirmarRecebimento) {
  btnConfirmarRecebimento.addEventListener('click', async function () {
    if (!pedidoSelecionado) return;

    const botao = btnConfirmarRecebimento;
    const url = urls.confirmar.replace('__ID__', String(pedidoSelecionado.id_ped));
    const restaurar = iniciarCarregamento(botao, 'Confirmando…');

    try {
      const resposta = await enviarParaAPI(url, {}, 'POST');
      const mensagem = resposta.entrega_parcial
        ? 'Chegada parcial registrada. Ainda faltam bolsas para concluir o pedido.'
        : 'Chegada confirmada e pedido finalizado.';
      const idPedido = pedidoSelecionado.id_ped;

      fecharModalChegada();
      modosDoc.delete(idPedido);

      if (msgMonitorEl) mostrarMensagem(msgMonitorEl, mensagem, 'success');
      await carregarPedidos(paginaAtual);
    } catch (erro) {
      if (msgChegadaEl) mostrarMensagem(msgChegadaEl, erro.message, 'error');
    } finally {
      restaurar();
    }
  });
}

const parametrosUrl = new URLSearchParams(window.location.search);
const tiposDaUrl = parametrosUrl.get('tipos');

if (tiposDaUrl) {
  const tipoPreselecionado = tiposDaUrl
    .split(',')
    .map(function (tipo) { return tipo.trim(); })
    .find(function (tipo) { return tiposValidos.includes(tipo); });

  if (tipoPreselecionado) selecionarTipo(tipoPreselecionado);
}

carregarPedidos();
carregarHemocentros();
