const btnNovaRequisicao = document.getElementById('btnNovaRequisicao');
const formNovaRequisicao = document.getElementById('formNovaRequisicao');

const qtyInput = document.getElementById('qtyInput');
const qtyMenos = document.getElementById('qtyMenos');
const qtyMais = document.getElementById('qtyMais');

const inputArquivo = document.getElementById('inputArquivo');
const btnTrocarArquivo = document.getElementById('btnTrocarArquivo');
const nomeArquivo = document.getElementById('nomeArquivo');
const metaArquivo = document.getElementById('metaArquivo');

if (btnNovaRequisicao) {
  btnNovaRequisicao.addEventListener('click', function () {
    formNovaRequisicao.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

if (qtyMenos && qtyMais && qtyInput) {
  qtyMenos.addEventListener('click', function () {
    const valorAtual = Number(qtyInput.value) || 1;
    qtyInput.value = Math.max(1, valorAtual - 1);
  });

  qtyMais.addEventListener('click', function () {
    const valorAtual = Number(qtyInput.value) || 0;
    qtyInput.value = valorAtual + 1;
  });
}

if (btnTrocarArquivo) {
  btnTrocarArquivo.addEventListener('click', function () {
    inputArquivo.click();
  });
}

if (inputArquivo) {
  inputArquivo.addEventListener('change', function () {
    const arquivo = this.files[0];
    if (!arquivo) return;

    const tamanhoMB = (arquivo.size / (1024 * 1024)).toFixed(1);
    nomeArquivo.textContent = arquivo.name;
    metaArquivo.textContent = `Arquivo pronto para envio (${tamanhoMB} MB)`;
    btnTrocarArquivo.textContent = 'Trocar arquivo';
  });
}

const tabelaRequisicoes = document.querySelector('.requests-table');

if (tabelaRequisicoes) {
  tabelaRequisicoes.addEventListener('click', function (e) {
    const botao = e.target.closest('.btn-confirm-sm');
    if (!botao) return;

    const linha = botao.closest('tr');
    const rotulo = linha.querySelector('.timeline__label');

    if (rotulo) rotulo.textContent = 'Entregue';

    botao.classList.remove('btn-confirm-sm');
    botao.classList.add('btn-outline-sm');
    botao.textContent = 'Ver PDF';
  });
}