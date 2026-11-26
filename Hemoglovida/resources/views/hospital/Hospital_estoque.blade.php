<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Estoque — Hospital | Hemoglovida</title>

  <link rel="stylesheet" href="{{ asset('css/hospital/base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hospital/estoque.css') }}">
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
</head>
<body>

  <header>
    <div class="header-left">
      <div class="logo">
        <img src="https://i.ibb.co/SwCGm0fj/Hemoglovida-Logo.png" alt="Logo Hemoglovida" class="logo-icon">
        Hemoglovida
      </div>

      <nav>
        <a href="{{ route('hospital.estoque') }}" class="active">Estoque</a>
        <a href="{{ route('hospital.pedidos') }}">Pedidos</a>
      </nav>
    </div>

    <div class="user">
      Bem-vindo, {{ $nomeExibido() }}
      <a href="{{ route('hospital.usuario') }}">
        <div class="avatar">
          <img src="{{ $fotoPerfil() }}" alt="Foto de perfil">
        </div>
      </a>
    </div>
  </header>

  <main class="page-content">

    <div class="page-top">
      <h1 id="estoqueTitulo">Estoque do Hospital — {{ $hospitalSessao()?->nome_hosp ?? 'Hospital' }}</h1>
      <div class="updated-tag">
        <span class="updated-dot"></span>
        <span id="estoqueAtualizado">Carregando dados...</span>
      </div>
    </div>

    <div class="alert-banner" id="estoqueAlerta" hidden>
      <div class="alert-banner__content">
        <div class="alert-banner__icon">!</div>
        <div class="alert-banner__text">
          <h2>Estoque Crítico Detectado</h2>
          <p id="estoqueAlertaTexto"></p>
        </div>
      </div>
      <a class="btn" id="btnRequisicao" data-url="{{ route('hospital.pedidos') }}" href="{{ route('hospital.pedidos') }}">Fazer Requisição Agora</a>
    </div>

    <div class="estoque-mensagem" id="estoqueMensagem" role="status" hidden></div>

    <div class="stock-grid" id="estoqueCards" data-url="{{ route('hospital.estoque.resumo') }}"></div>

    <div class="chart-card">
      <div class="chart-card__head">
        <h2>Consumo de Estoque</h2>
        <div class="chart-legend">
          <span class="chart-legend__item">
            <span class="chart-legend__dot chart-legend__dot--saidas"></span>
            Saídas
          </span>
          <span class="chart-legend__item">
            <span class="chart-legend__dot chart-legend__dot--entradas"></span>
            Entradas
          </span>
          <span class="chart-period" id="estoquePeriodo">Últimos 28 dias</span>
        </div>
      </div>

      <div class="chart-scale" id="estoqueEscala" aria-hidden="true"></div>
      <div class="bar-chart" id="estoqueGrafico" data-url="{{ route('hospital.estoque.consumo') }}" aria-label="Consumo semanal"></div>
    </div>

    <div class="table-card">
      <div class="table-card__head">
        <h2>Inventário Detalhado</h2>
        <div class="table-card__actions">
          <button class="btn-outline-sm" id="btnImprimir" type="button">Imprimir</button>
          <button class="btn-outline-sm" data-abrir-inventario type="button">Editar Inventário</button>
        </div>
      </div>

      <div class="table-scroll">
        <table class="inventory-table">
          <thead>
            <tr>
              <th>ID Bolsa/Lote</th>
              <th>Tipo Sanguíneo</th>
              <th>Origem</th>
              <th>Recebimento</th>
              <th>Validade</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="estoqueTabela" data-url="{{ route('hospital.estoque.bolsas') }}"></tbody>
        </table>
      </div>

      <div class="table-footer">
        <span class="table-footer__info" id="estoqueInfo">Carregando bolsas...</span>
        <div class="paginacao" id="estoquePaginacao"></div>
      </div>
    </div>

  </main>

  <div class="overlay" id="modalInventarioOverlay">
    <div class="modal">
      <div class="modal__header">
        <h2 id="modalTitulo">Inventário — {{ $hospitalSessao()?->nome_hosp ?? 'Hospital' }}</h2>
        <button class="modal__close" id="modalInventarioClose" type="button" aria-label="Fechar">✕</button>
      </div>

      <div class="modal__body">
        <div class="modal__section">
          <div class="modal__section-title">Controle Interno</div>
          <div class="modal__grid">
            <div class="modal__field">
              <label class="modal__field-label" for="selectBolsa">ID Bolsa/Lote</label>
              <select class="field__select" id="selectBolsa"></select>
            </div>
            <div class="modal__field">
              <label class="modal__field-label" for="pacienteDestino">Paciente de destino</label>
              <input class="field__input" type="text" id="pacienteDestino" placeholder="Ainda não definido" maxlength="150">
            </div>
            <div class="modal__field">
              <label class="modal__field-label" for="statusBolsa">Status da Bolsa</label>
              <select class="field__select" id="statusBolsa"></select>
            </div>
          </div>
        </div>

        <div class="modal__section">
          <div class="modal__section-title">Informações da Bolsa</div>
          <p class="modal__hint">
            A validade das informações é de responsabilidade do hemocentro.
            Qualquer inconformidade deve ser alertada a um administrador.
          </p>
          <div class="modal__grid">
            <div class="modal__field">
              <label class="modal__field-label" for="infoDataEnvio">Data de Envio</label>
              <input class="field__input" type="text" id="infoDataEnvio" readonly>
            </div>
            <div class="modal__field">
              <label class="modal__field-label" for="infoAboRh">ABO/Rh (Tipo Sanguíneo)</label>
              <input class="field__input" type="text" id="infoAboRh" readonly>
            </div>
            <div class="modal__field">
              <label class="modal__field-label" for="infoDataValidade">Data de Validade</label>
              <input class="field__input" type="text" id="infoDataValidade" readonly>
            </div>
            <div class="modal__field">
              <label class="modal__field-label" for="infoOrigem">Origem</label>
              <input class="field__input" type="text" id="infoOrigem" readonly>
            </div>
            <div class="modal__field">
              <label class="modal__field-label" for="infoIDDoacao">Número de identificação da doação (ISBT 128)</label>
              <input class="field__input" type="text" id="infoIDDoacao" readonly>
            </div>
          </div>
        </div>
      </div>

      <div class="modal__footer">
        <button class="btn" id="btnConfirmarInventario" type="button">Confirmar</button>
      </div>
    </div>
  </div>

  <script src="{{ asset('js/hospital/base.js') }}"></script>
  <script src="{{ asset('js/hospital/estoque.js') }}"></script>
</body>
</html>
