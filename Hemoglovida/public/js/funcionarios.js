/* Hemoglovida — Gestão de Funcionários (compartilhado hospital/hemocentro) */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };

  var modal = $('funcModal');
  var modalExcluir = $('funcModalExcluir');
  var form = $('funcForm');
  var formExcluir = $('funcFormExcluir');
  var campoBusca = $('funcBusca');
  var filtroCargo = $('funcCargo');
  var linhas = Array.prototype.slice.call(document.querySelectorAll('#funcCorpo tr'));
  var estadoVazio = $('funcEmptyState');

  /* ---------- Máscara de CPF ---------- */
  function onlyDigits(value) {
    return (value || '').replace(/\D/g, '');
  }

  function formatarCpf(value) {
    var digits = onlyDigits(value).slice(0, 11);
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2');
  }

  var cpfInput = $('funcCpf');
  if (cpfInput) {
    cpfInput.value = formatarCpf(cpfInput.value);
    cpfInput.addEventListener('input', function () {
      cpfInput.value = formatarCpf(cpfInput.value);
    });
  }

  /* ---------- Modais ---------- */
  function abrir(el) {
    if (el) {
      el.hidden = false;
      document.body.style.overflow = 'hidden';
    }
  }

  function fechar(el) {
    if (el) {
      el.hidden = true;
      document.body.style.overflow = '';
    }
  }

  function fecharTodos() {
    fechar(modal);
    fechar(modalExcluir);
  }

  var abrirNovo = $('funcNovoBtn');
  if (abrirNovo) {
    abrirNovo.addEventListener('click', function () {
      prepararCadastro();
      abrir(modal);
      $('funcNome').focus();
    });
  }

  function vincularFechamento(el, ids) {
    if (!el) { return; }
    ids.forEach(function (id) {
      var botao = $(id);
      if (botao) { botao.addEventListener('click', function () { fechar(el); }); }
    });
    el.addEventListener('click', function (event) {
      if (event.target === el) { fechar(el); }
    });
  }

  vincularFechamento(modal, ['funcFechar', 'funcCancelar']);
  vincularFechamento(modalExcluir, ['funcFecharExcluir', 'funcCancelarExcluir']);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { fecharTodos(); }
  });

  /* ---------- Alternar senha ---------- */
  var toggleSenha = $('funcToggleSenha');
  if (toggleSenha) {
    toggleSenha.addEventListener('click', function () {
      var senha = $('funcSenha');
      var mostrar = senha.type === 'password';
      senha.type = mostrar ? 'text' : 'password';
      toggleSenha.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
      toggleSenha.innerHTML = '<i class="ph ph-' + (mostrar ? 'eye-slash' : 'eye') + '"></i>';
    });
  }

  /* ---------- Cadastro x edição ---------- */
  function configurarModoEdicao(codigo, nome, cpf, cargo) {
    form.reset();
    $('funcModalTitulo').textContent = 'Editar Funcionário';
    $('funcMethod').value = 'PUT';
    $('funcCodigo').value = codigo || '';
    form.action = form.dataset.updateTemplate.replace('__CODIGO__', codigo || '');

    var linha = linhas.find(function (item) { return item.dataset.codigo === String(codigo); });
    $('funcCpf').value = cpf || (linha ? linha.dataset.cpf : '') || '';
    $('funcCpf').disabled = true;
    $('funcCpf').required = false;
    $('funcCpfHint').hidden = true;
    $('funcSenha').required = false;
    $('funcSenhaHint').hidden = false;
    $('funcNome').value = nome || '';
    $('funcCargoForm').value = cargo || '';
  }

  function prepararCadastro() {
    form.reset();
    $('funcModalTitulo').textContent = 'Cadastrar Novo Funcionário';
    $('funcMethod').value = 'POST';
    $('funcCodigo').value = '';
    form.action = form.dataset.store;
    $('funcCpf').disabled = false;
    $('funcCpf').required = true;
    $('funcCpfHint').hidden = false;
    $('funcSenha').required = false;
    $('funcSenhaHint').hidden = true;
    $('funcConfirmarCampo').hidden = false;
  }

  document.querySelectorAll('.func-editar').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var linha = botao.closest('tr');
      configurarModoEdicao(
        linha.dataset.codigo,
        linha.dataset.nome,
        linha.dataset.cpf,
        linha.dataset.cargo
      );
      abrir(modal);
      $('funcNome').focus();
    });
  });

  document.querySelectorAll('.func-excluir').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var linha = botao.closest('tr');
      formExcluir.action = linha.dataset.delete;
      $('funcExcluirNome').textContent = linha.dataset.nome;
      abrir(modalExcluir);
    });
  });

  /* ---------- Busca e filtro ---------- */
  function aplicarFiltros() {
    var termo = (campoBusca ? campoBusca.value : '').toLowerCase().trim();
    var digitosTermo = onlyDigits(termo);
    var cargo = filtroCargo ? filtroCargo.value : '';
    var visiveis = 0;

    linhas.forEach(function (linha) {
      var corresponde = linha.dataset.nome.toLowerCase().indexOf(termo) !== -1
        || (digitosTermo.length > 0 && onlyDigits(linha.dataset.cpf).indexOf(digitosTermo) !== -1);
      if (cargo) { corresponde = corresponde && linha.dataset.cargo === cargo; }
      linha.hidden = !corresponde;
      if (corresponde) { visiveis++; }
    });

    if (estadoVazio) { estadoVazio.hidden = visiveis > 0; }
  }

  if (campoBusca) { campoBusca.addEventListener('input', aplicarFiltros); }
  if (filtroCargo) { filtroCargo.addEventListener('change', aplicarFiltros); }

  /* ---------- Toast ---------- */
  var toast = $('funcToast');
  var fecharToast = $('funcFecharToast');
  if (fecharToast) { fecharToast.addEventListener('click', function () { toast.remove(); }); }
  if (toast) {
    window.setTimeout(function () {
      toast.style.opacity = '0';
      window.setTimeout(function () { toast.remove(); }, 250);
    }, 5000);
  }

  /* ---------- Reabrir o modal após erro de validação ---------- */
  var script = document.querySelector('script[src$="js/funcionarios.js"]');
  if (script && script.dataset.abrirModal === '1') {
    var metodoAntigo = $('funcMethod').value;
    if (metodoAntigo === 'PUT') {
      var dadosAntigos = {
        codigo: $('funcCodigo').value,
        nome: $('funcNome').value,
        cpf: $('funcCpf').value,
        cargo: $('funcCargoForm').value,
      };
      configurarModoEdicao(dadosAntigos.codigo, dadosAntigos.nome, dadosAntigos.cpf, dadosAntigos.cargo);
    }
    abrir(modal);
  }
})();
