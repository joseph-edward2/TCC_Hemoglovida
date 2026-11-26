<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Pedidos — Hospital | Hemoglovida</title>

  <link rel="stylesheet" href="{{ asset('css/hospital/base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hospital/pedidos.css') }}">
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
        <a href="{{ route('hospital.estoque') }}">Estoque</a>
        <a href="{{ route('hospital.pedidos') }}" class="active">Pedidos</a>
      @can('gerenciar-funcionarios', 'hospital')
        <a href="{{ route('hospital.funcionarios') }}">Funcionários</a>
      @endcan
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
      <h1>Requisições de Sangue</h1>
      <button class="btn" id="btnNovaRequisicao" type="button">+ Nova Requisição</button>
    </div>

    <div class="summary-grid" id="resumoPedidos" data-url="{{ route('hospital.pedidos.resumo') }}"></div>

    <div class="pedidos-layout">
      <div class="request-card" id="formNovaRequisicao">
        <h2 class="request-card__title">Solicitar Bolsas</h2>

        <form id="formRequisicao" data-url="{{ route('hospital.pedidos.store') }}">
          <div class="form-section">
            <label class="form-section__label">Tipo Sanguíneo Requerido</label>
            <div class="blood-select" id="tipoSanguineo">
              <button type="button" class="blood-select__btn">O-</button>
              <button type="button" class="blood-select__btn">O+</button>
              <button type="button" class="blood-select__btn">A-</button>
              <button type="button" class="blood-select__btn">A+</button>
              <button type="button" class="blood-select__btn">B-</button>
              <button type="button" class="blood-select__btn">B+</button>
              <button type="button" class="blood-select__btn">AB-</button>
              <button type="button" class="blood-select__btn">AB+</button>
            </div>
            <input type="hidden" name="tipo_sanguineo" id="tipoSanguineoValor">
          </div>

          <div class="form-row">
            <div class="form-section">
              <label class="form-section__label">Quantidade de bolsas</label>
              <div class="qty-stepper">
                <button type="button" class="qty-stepper__btn" id="qtyMenos">−</button>
                <input type="number" class="qty-stepper__input" id="qtyInput" name="quantidade" value="2" min="1" max="50">
                <button type="button" class="qty-stepper__btn" id="qtyMais">+</button>
              </div>
            </div>

            <div class="form-section">
              <label class="form-section__label">Urgência</label>
              <select class="field__select" name="urgencia">
                <option value="rotina">Rotina</option>
                <option value="urgente" selected>Urgente</option>
                <option value="critica">Crítica</option>
              </select>
            </div>
          </div>

          <div class="form-section">
            <label class="form-section__label">Paciente / Setor</label>
            <input type="text" class="field__input" name="paciente_setor" placeholder="Ex: João Silva - UTI">
          </div>

          <div class="form-section">
            <label class="form-section__label">Hemocentro de Destino</label>
            <div class="hemocentro-busca" id="hemocentroBusca">
              <input type="search" class="field__input busca-hemocentro" id="buscaHemocentro" placeholder="Buscar por nome, CNES ou contato" aria-label="Buscar hemocentro de destino" aria-controls="listaHemocentros" aria-expanded="false" autocomplete="off" role="combobox">
              <div class="lista-hemocentros" id="listaHemocentros" data-url="{{ route('hospital.pedidos.hemocentros') }}" role="listbox" hidden></div>
            </div>
          </div>

          <div class="form-section">
            <label class="form-section__label">Documento de Requisição (Assinado)</label>
            <div class="upload-box">
              <div class="upload-box__icon"><i class="ph ph-file-pdf" aria-hidden="true"></i></div>
              <div class="upload-box__info">
                <div class="upload-box__name" id="nomeArquivo">Nenhum arquivo selecionado</div>
                <div class="upload-box__meta" id="metaArquivo">Envie o PDF assinado da requisição</div>
              </div>
              <button type="button" class="btn-outline-sm" id="btnTrocarArquivo">Escolher arquivo</button>
              <input type="file" name="requisicao" id="inputArquivo" accept=".pdf,application/pdf" hidden>
            </div>
          </div>

          <button type="submit" class="btn" id="btnEnviarRequisicao">Enviar Requisição</button>
        </form>

        <p class="msg" id="msg" hidden></p>
      </div>

      <div>
        <div class="monitor-card">
          <div class="monitor-card__head">
            <h2>Monitoramento dos Pedidos</h2>
            <div class="monitor-card__actions">
              <span class="atualizado-em" id="atualizadoEm">—</span>
              <button type="button" class="btn-outline-sm" id="btnAtualizar">Atualizar</button>
            </div>
          </div>

          <div class="table-scroll">
            <table class="requests-table">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Qtd</th>
                  <th>Urgência</th>
                  <th>Status / Etapas</th>
                  <th>Doc</th>
                </tr>
              </thead>
              <tbody id="listaPedidos"
                data-url="{{ route('hospital.pedidos.listar') }}"
                data-bolsas-url="{{ route('hospital.pedidos.bolsas-em-transito', ['pedido' => '__ID__']) }}"
                data-confirmar-url="{{ route('hospital.pedidos.confirmar-chegada', ['pedido' => '__ID__']) }}"></tbody>
            </table>
          </div>

          <div class="paginacao" id="paginacaoMonitor"></div>
          <p class="msg" id="msgMonitor" hidden></p>
        </div>

        <div class="info-banner">
          <div class="info-banner__icon">i</div>
          <div class="info-banner__text">
            <h3>Como funciona o acompanhamento</h3>
            <p>
              Cada pedido passa por até 5 etapas:
              @foreach (\App\Enums\StatusPedido::cases() as $status)
                {{ $status->label() }}{{ $loop->last ? '.' : ' | ' }}
              @endforeach
              Acompanhe o andamento na tabela acima.
            </p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="overlay" id="overlayChegada" hidden>
    <div class="modal modal--chegada" role="dialog" aria-modal="true" aria-labelledby="tituloChegada">
      <div class="modal__header">
        <h2 id="tituloChegada">Confirmar chegada das bolsas</h2>
        <button type="button" class="modal__close" id="btnFecharChegada" aria-label="Fechar">&times;</button>
      </div>
      <div class="modal__body">
        <p class="modal__hint" id="resumoChegada"></p>
        <ul class="modal__bolsas" id="listaBolsasChegada"></ul>
        <label class="modal__confirmacao">
          <input type="checkbox" id="conferiBolsas">
          Conferi as bolsas recebidas
        </label>
        <p class="msg" id="msgChegada" hidden></p>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn" id="btnConfirmarRecebimento" disabled>Confirmar recebimento</button>
      </div>
    </div>
  </div>

  <script src="{{ asset('js/hospital/base.js') }}"></script>
  <script src="{{ asset('js/hospital/pedidos.js') }}"></script>
</body>
</html>
