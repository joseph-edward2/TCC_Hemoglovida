/* ======================================================
   HEMOGLOVIDA — ESTOQUE (HOSPITAL)
   ======================================================
   Todos os dados vêm das rotas JSON do hospital. A página
   só monta elementos com document.createElement/textContent.
   ====================================================== */

const cartoesEl = document.getElementById('estoqueCards');
const graficoEl = document.getElementById('estoqueGrafico');
const escalaEl = document.getElementById('estoqueEscala');
const tabelaEl = document.getElementById('estoqueTabela');
const paginacaoEl = document.getElementById('estoquePaginacao');
const mensagemEl = document.getElementById('estoqueMensagem');
const alertaEl = document.getElementById('estoqueAlerta');
const alertaTextoEl = document.getElementById('estoqueAlertaTexto');
const atualizadoEl = document.getElementById('estoqueAtualizado');
const tituloEl = document.getElementById('estoqueTitulo');
const periodoEl = document.getElementById('estoquePeriodo');
const infoEl = document.getElementById('estoqueInfo');
const modalOverlay = document.getElementById('modalInventarioOverlay');
const selectBolsa = document.getElementById('selectBolsa');
const statusBolsa = document.getElementById('statusBolsa');
const pacienteDestino = document.getElementById('pacienteDestino');
const btnConfirmar = document.getElementById('btnConfirmarInventario');

const urls = {
  resumo: cartoesEl ? cartoesEl.dataset.url : '',
  consumo: graficoEl ? graficoEl.dataset.url : '',
  bolsas: tabelaEl ? tabelaEl.dataset.url : ''
};

const classesStatus = {
  'em_transito': 'status-tag--em-transito',
  'disponivel': 'status-tag--disponivel',
  'reservada': 'status-tag--reservado',
  'utilizada': 'status-tag--utilizado',
  'descartada': 'status-tag--descartado'
};

let paginaAtual = 1;
let bolsasDaPagina = new Map();

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

function exibirMensagemEstoque(texto, tipo) {
  if (!mensagemEl) return;

  mensagemEl.textContent = texto;
  mensagemEl.className = 'estoque-mensagem estoque-' + tipo;
  mensagemEl.hidden = false;
}

function formatarData(valor) {
  if (!valor) return '—';

  const partes = String(valor).slice(0, 10).split('-');

  if (partes.length === 3) {
    return partes[2] + '/' + partes[1] + '/' + partes[0];
  }

  return new Date(valor).toLocaleDateString('pt-BR');
}

function formatarDataHora(valor) {
  if (!valor) return '—';

  return new Date(valor).toLocaleString('pt-BR');
}

function renderizarResumo(dados) {
  if (tituloEl && dados.hospital && dados.hospital.nome) {
    tituloEl.textContent = 'Estoque do Hospital — ' + dados.hospital.nome;
  }

  if (atualizadoEl) {
    atualizadoEl.textContent = 'Atualizado em ' + formatarDataHora(dados.atualizado_em);
  }

  if (alertaEl && alertaTextoEl) {
    alertaEl.hidden = !dados.total_criticos;
    alertaTextoEl.textContent = dados.total_criticos > 0
      ? dados.total_criticos + ' tipo(s) sanguíneo(s) abaixo do estoque mínimo de segurança: '
        + dados.tipos_criticos.join(', ') + '.'
      : '';
  }

  const btnRequisicao = document.getElementById('btnRequisicao');

  if (btnRequisicao && btnRequisicao.dataset.url && dados.tipos_criticos && dados.tipos_criticos.length > 0) {
    btnRequisicao.href = btnRequisicao.dataset.url
      + '?tipos=' + encodeURIComponent(dados.tipos_criticos.join(','));
  }

  limpar(cartoesEl);
  if (!cartoesEl) return;

  if (!dados.cards || dados.cards.length === 0) {
    cartoesEl.appendChild(criarElemento('p', 'estoque-vazio', 'Nenhum tipo sanguíneo disponível.'));
    return;
  }

  dados.cards.forEach(function (card) {
    const cartao = criarElemento('div', 'stock-card' + (card.critico ? ' stock-card--critical' : ''));
    const cabecalho = criarElemento('div', 'stock-card__head');

    cabecalho.appendChild(criarElemento('span', 'stock-card__type', card.tipo_bolsa));

    if (card.critico) {
      cabecalho.appendChild(criarElemento('span', 'stock-card__badge', 'CRÍTICO'));
    }

    const contagem = criarElemento('div', 'stock-card__count');
    contagem.appendChild(criarElemento('strong', '', card.quantidade));
    contagem.appendChild(criarElemento('span', '', 'bolsas'));

    const barra = criarElemento('div', 'stock-card__bar');
    const preenchimento = criarElemento('div', 'stock-card__bar-fill');
    preenchimento.style.width = Math.max(0, Math.min(100, Number(card.percentual) || 0)) + '%';
    barra.appendChild(preenchimento);

    const meta = criarElemento('div', 'stock-card__meta');
    meta.appendChild(criarElemento('span', '', 'Meta: ' + card.meta + ' bolsas'));
    meta.appendChild(criarElemento('span', '', card.percentual + '% do ideal'));

    const vencimento = criarElemento(
      'div',
      'stock-card__expiry' + (card.a_vencer > 0 ? ' stock-card__expiry--warning' : ''),
      card.a_vencer > 0
        ? card.a_vencer + ' bolsas vencendo em 7 dias'
        : 'Nenhuma expiração próxima'
    );

    cartao.appendChild(cabecalho);
    cartao.appendChild(contagem);
    cartao.appendChild(barra);
    cartao.appendChild(meta);
    cartao.appendChild(vencimento);
    cartoesEl.appendChild(cartao);
  });
}

function renderizarConsumo(dados) {
  limpar(escalaEl);
  limpar(graficoEl);
  if (!graficoEl) return;

  const escalaMaxima = Number(dados.escala_max) || 20;

  for (let indice = 4; indice >= 0; indice -= 1) {
    escalaEl.appendChild(criarElemento('span', '', Math.round(escalaMaxima * indice / 4)));
  }

  const descricoes = [];

  (dados.semanas || []).forEach(function (semana) {
    const semanaEl = criarElemento('div', 'bar-chart__week');
    const barras = criarElemento('div', 'bar-chart__bars');

    ['saidas', 'entradas'].forEach(function (tipo) {
      const valor = Number(semana[tipo]) || 0;
      const altura = Math.max(0, Math.min(160, valor / escalaMaxima * 160));
      const barra = criarElemento('div', 'bar-chart__bar bar-chart__bar--' + tipo);
      barra.style.height = altura + 'px';
      barra.appendChild(criarElemento('span', '', valor));
      barras.appendChild(barra);
    });

    semanaEl.appendChild(barras);
    semanaEl.appendChild(criarElemento('span', 'bar-chart__label', semana.rotulo));
    graficoEl.appendChild(semanaEl);

    descricoes.push(
      String(semana.rotulo).toLowerCase() + ': ' + semana.saidas + ' saídas e ' + semana.entradas + ' entradas'
    );
  });

  graficoEl.setAttribute('aria-label', 'Consumo semanal: ' + descricoes.join('; ') + '.');

  if (periodoEl && dados.periodo) {
    periodoEl.textContent = 'De ' + formatarData(dados.periodo.inicio) + ' a ' + formatarData(dados.periodo.fim);
  }
}

function popularSeletorBolsas(bolsas) {
  limpar(selectBolsa);
  if (!selectBolsa) return;

  bolsas.forEach(function (bolsa) {
    const opcao = criarElemento('option', '', bolsa.isbt_formatado + ' — ' + bolsa.tipo_bolsa);
    opcao.value = bolsa.isbt;
    selectBolsa.appendChild(opcao);
  });
}

function renderizarPaginacao(resposta) {
  limpar(paginacaoEl);
  if (!paginacaoEl) return;

  const meta = resposta.meta || {};
  const atual = Number(meta.current_page) || 1;
  const ultima = Number(meta.last_page) || 1;

  if (ultima <= 1) return;

  function adicionarBotao(texto, pagina, desabilitado, ativo) {
    const botao = criarElemento('button', 'paginacao__botao', texto);
    botao.type = 'button';
    botao.disabled = desabilitado;

    if (ativo) {
      botao.classList.add('paginacao__botao--ativo');
    }

    botao.addEventListener('click', function () {
      carregarBolsas(pagina);
    });

    paginacaoEl.appendChild(botao);
  }

  adicionarBotao('‹', atual - 1, atual === 1, false);

  const inicio = Math.max(1, atual - 2);
  const fim = Math.min(ultima, atual + 2);

  for (let pagina = inicio; pagina <= fim; pagina += 1) {
    adicionarBotao(String(pagina), pagina, false, pagina === atual);
  }

  adicionarBotao('›', atual + 1, atual === ultima, false);
}

function renderizarBolsas(resposta) {
  const bolsas = resposta.data || [];
  const meta = resposta.meta || {};

  paginaAtual = Number(meta.current_page) || 1;
  bolsasDaPagina = new Map();

  limpar(tabelaEl);
  if (!tabelaEl) return;

  if (bolsas.length === 0) {
    const linha = criarElemento('tr');
    const celula = criarElemento('td', 'estoque-vazio', 'Nenhuma bolsa encontrada para este hospital.');
    celula.colSpan = 7;
    linha.appendChild(celula);
    tabelaEl.appendChild(linha);

    if (infoEl) {
      infoEl.textContent = 'Nenhuma bolsa encontrada.';
    }

    popularSeletorBolsas([]);
    renderizarPaginacao(resposta);
    return;
  }

  bolsas.forEach(function (bolsa) {
    bolsasDaPagina.set(bolsa.isbt, bolsa);

    const linha = criarElemento('tr');

    linha.appendChild(criarElemento('td', '', bolsa.isbt_formatado));

    const celulaTipo = criarElemento('td');
    celulaTipo.appendChild(criarElemento('span', 'blood-tag', bolsa.tipo_bolsa));
    linha.appendChild(celulaTipo);

    linha.appendChild(criarElemento('td', '', bolsa.origem));
    linha.appendChild(criarElemento('td', '', formatarDataHora(bolsa.data_hora_recebimento_bolsa)));

    const celulaValidade = criarElemento('td', bolsa.vencida ? 'estoque-vencida' : '', formatarData(bolsa.data_venc_bolsa));
    linha.appendChild(celulaValidade);

    const celulaStatus = criarElemento('td');
    const classeStatus = classesStatus[bolsa.disponibilidade_bolsa] || '';
    celulaStatus.appendChild(criarElemento('span', 'status-tag ' + classeStatus, bolsa.disponibilidade_label));
    linha.appendChild(celulaStatus);

    const celulaAcao = criarElemento('td');
    const botaoVer = criarElemento('button', 'table-action-btn', 'Ver');
    botaoVer.type = 'button';
    botaoVer.setAttribute('data-abrir-inventario', '');
    botaoVer.setAttribute('data-bolsa', bolsa.isbt);
    botaoVer.addEventListener('click', function () {
      abrirModal(bolsa.isbt);
    });
    celulaAcao.appendChild(botaoVer);
    linha.appendChild(celulaAcao);

    tabelaEl.appendChild(linha);
  });

  popularSeletorBolsas(bolsas);

  if (infoEl) {
    infoEl.textContent = 'Mostrando ' + meta.from + ' a ' + meta.to + ' de ' + meta.total + ' bolsas.';
  }

  renderizarPaginacao(resposta);
}

async function carregarResumo() {
  const dados = await buscarDaAPI(urls.resumo);
  renderizarResumo(dados);
}

async function carregarConsumo() {
  const dados = await buscarDaAPI(urls.consumo);
  renderizarConsumo(dados);
}

async function carregarBolsas(pagina) {
  const separador = urls.bolsas.includes('?') ? '&' : '?';
  const resposta = await buscarDaAPI(urls.bolsas + separador + 'pagina=' + pagina + '&por_pagina=20');
  renderizarBolsas(resposta);
}

async function carregarTudo() {
  try {
    await Promise.all([
      carregarResumo(),
      carregarConsumo(),
      carregarBolsas(paginaAtual)
    ]);
  } catch (erro) {
    exibirMensagemEstoque(erro.message, 'erro');
  }
}

function preencherStatus(permitidos, detalhe, estadoFinal) {
  limpar(statusBolsa);
  if (!statusBolsa) return;

  if (estadoFinal || !permitidos || permitidos.length === 0) {
    const atual = criarElemento(
      'option',
      '',
      detalhe.disponibilidade_label + ' (estado final)'
    );
    atual.value = detalhe.disponibilidade_bolsa;
    statusBolsa.appendChild(atual);
    statusBolsa.disabled = true;
    btnConfirmar.disabled = true;
    return;
  }

  statusBolsa.disabled = false;
  btnConfirmar.disabled = false;

  permitidos.forEach(function (permitido) {
    const opcao = criarElemento('option', '', permitido.rotulo);
    opcao.value = permitido.valor;
    statusBolsa.appendChild(opcao);
  });
}

function preencherDetalhes(resposta) {
  const detalhe = resposta.data || resposta;

  selectBolsa.value = detalhe.isbt;
  pacienteDestino.value = detalhe.paciente_destino_bolsa || '';
  document.getElementById('infoDataEnvio').value = formatarDataHora(detalhe.data_hora_envio_bolsa);
  document.getElementById('infoAboRh').value = detalhe.tipo_bolsa || '—';
  document.getElementById('infoDataValidade').value = formatarData(detalhe.data_venc_bolsa);
  document.getElementById('infoOrigem').value = detalhe.origem || '—';
  document.getElementById('infoIDDoacao').value = detalhe.isbt_formatado || detalhe.isbt;

  preencherStatus(resposta.status_permitidos, detalhe, resposta.estado_final);
}

async function abrirModal(isbt) {
  if (!modalOverlay || !isbt) return;

  modalOverlay.classList.add('show');

  try {
    const resposta = await buscarDaAPI(urls.bolsas + '/' + encodeURIComponent(isbt));
    preencherDetalhes(resposta);
  } catch (erro) {
    exibirMensagemEstoque(erro.message, 'erro');
  }
}

function fecharModal() {
  if (modalOverlay) {
    modalOverlay.classList.remove('show');
  }
}

document.querySelectorAll('[data-abrir-inventario]').forEach(function (botao) {
  botao.addEventListener('click', function () {
    const isbt = botao.dataset.bolsa
      || (bolsasDaPagina.size > 0 ? bolsasDaPagina.keys().next().value : null)
      || (selectBolsa && selectBolsa.options.length > 0 ? selectBolsa.options[0].value : null);

    abrirModal(isbt);
  });
});

selectBolsa.addEventListener('change', function () {
  abrirModal(this.value);
});

document.getElementById('modalInventarioClose').addEventListener('click', fecharModal);

modalOverlay.addEventListener('click', function (evento) {
  if (evento.target === modalOverlay) {
    fecharModal();
  }
});

btnConfirmar.addEventListener('click', async function () {
  const isbt = selectBolsa.value;
  if (!isbt) return;

  const restaurar = iniciarCarregamento(btnConfirmar, 'Salvando...');

  try {
    await enviarParaAPI(urls.bolsas + '/' + encodeURIComponent(isbt), {
      disponibilidade_bolsa: statusBolsa.value,
      paciente_destino_bolsa: pacienteDestino.value || null
    }, 'PATCH');

    fecharModal();
    exibirMensagemEstoque('Alterações confirmadas com sucesso.', 'sucesso');
    await carregarTudo();
  } catch (erro) {
    exibirMensagemEstoque(erro.message, 'erro');
  } finally {
    restaurar();
  }
});

document.getElementById('btnImprimir').addEventListener('click', function () {
  window.print();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', carregarTudo);
} else {
  carregarTudo();
}
