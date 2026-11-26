<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedidos — Hospital | Hemoglovida</title>


  <link rel="stylesheet" href="{{ asset('css/hospital/base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/resumo-pedidos.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hospital/pedidos.css') }}">
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
</head>
<body>

  <!-- ======================================================
       HEADER (classes vindas do base.css)
       ====================================================== -->
  <header>
    <!--Título e logo-->
    <div class="header-left">
      <div class="logo">
        <img src="{{ asset('assets/logo.png') }}" alt="Logo Hemoglovida" class="logo-icon">
        Hemoglovida
      </div>

      <!--Links da navbar-->
      <nav>
        <a href="Hospital_estoque.blade.php">Estoque</a>
        <a href="Hospital_pedidos.blade.php" class="active">Pedidos</a>
      </nav>
    </div>

    <!--Ícone de usuário-->
    <div class="user">
      Bem-vindo, Renato Gonçalves
      <a href="Hospital_usuario.blade.php">
      <div class="avatar">
        <img src="{{ asset('assets/avatar.jpg') }}" alt="Foto de perfil">
      </div>
      </a>
    </div>
  </header>


  <main class="page-content">

    <!-- ====================================================
         TÍTULO DA PÁGINA
         ==================================================== -->
    <div class="page-top">
      <h1>Requisições de Sangue</h1>
    </div>

    <!-- ====================================================
         FAIXA DE RESUMO
         ==================================================== -->
    @include('resumo-pedidos')

    <!-- ====================================================
         LAYOUT EM DUAS COLUNAS: formulário + tabela
         ==================================================== -->
    <div class="pedidos-layout">

      <!-- ---------- COLUNA ESQUERDA: FORMULÁRIO ---------- -->
      <div class="request-card" id="formNovaRequisicao">
        <h2 class="request-card__title">Solicitar Bolsas</h2>

        @if (session('status'))
          <p class="msg msg--success" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
          <ul class="msg msg--error" role="alert">
            @foreach ($errors->all() as $erro)
              <li>{{ $erro }}</li>
            @endforeach
          </ul>
        @endif

        <form id="formRequisicao" method="POST" action="{{ route('hospital.pedidos.store') }}" enctype="multipart/form-data">
          @csrf

          <!-- Tipo sanguíneo -->
          <div class="form-section">
            <span class="form-section__label" id="labelTipoSanguineo">Tipo Sanguíneo Requerido</span>
            <div class="blood-select" role="radiogroup" aria-labelledby="labelTipoSanguineo">
              @foreach ($tiposSanguineos as $tipo)
                <label class="blood-select__btn">
                  <input type="radio" name="tipo_sanguineo" value="{{ $tipo }}" @checked(old('tipo_sanguineo') === $tipo) required>
                  {{ $tipo }}
                </label>
              @endforeach
            </div>
          </div>

          <div class="form-row">
            <!-- Quantidade de bolsas -->
            <div class="form-section">
              <label class="form-section__label">Quantidade de bolsas</label>
              <div class="qty-stepper">
                <button type="button" class="qty-stepper__btn" id="qtyMenos">−</button>
                <input type="number" class="qty-stepper__input" id="qtyInput" name="quantidade" value="{{ is_scalar(old('quantidade', 2)) ? old('quantidade', 2) : '' }}" min="1" max="2147483647" required>
                <button type="button" class="qty-stepper__btn" id="qtyMais">+</button>
              </div>
            </div>

            <!-- Urgência -->
            <div class="form-section">
              <label class="form-section__label">Urgência</label>
              <select class="field__select" name="urgencia">
                <option value="rotina" @selected(old('urgencia') === 'rotina')>Rotina</option>
                <option value="urgente" @selected(old('urgencia') === 'urgente')>Urgente</option>
                <option value="critica" @selected(old('urgencia', 'critica') === 'critica')>Crítica</option>
              </select>
            </div>
          </div>

          <!-- Paciente / Setor -->
          <div class="form-section">
            <label class="form-section__label">Paciente / Setor</label>
            <input type="text" class="field__input" name="paciente_setor" maxlength="255" value="{{ is_string(old('paciente_setor')) ? old('paciente_setor') : '' }}" placeholder="Ex: João Silva - UTI Cardiovascular">
          </div>

          <!-- Hemocentro de destino -->
          <div class="form-section">
            <label class="form-section__label">Hemocentro de Destino</label>

            @forelse ($hemocentros as $hemocentro)
              <label class="hemo-option">
                <input type="radio" name="hemocentro" value="{{ $hemocentro->cnes_hem }}" class="hemo-option__radio"
                  @checked(old('hemocentro', $loop->first ? $hemocentro->cnes_hem : null) === $hemocentro->cnes_hem) required>
                <span>
                  <span class="hemo-option__name">CNES {{ $hemocentro->cnes_hem }}</span>
                  <span class="hemo-option__status">{{ $hemocentro->endereco_hem }}</span>
                </span>
              </label>
            @empty
              <p>Nenhum hemocentro cadastrado para receber requisições.</p>
            @endforelse
          </div>

          <!-- Documento assinado -->
          <div class="form-section">
            <label class="form-section__label">Documento de Requisição (Assinado)</label>
            <div class="upload-box">
              <div class="upload-box__icon"><i class="ph ph-file-pdf" aria-hidden="true"></i></div>
              <div class="upload-box__info">
                <div class="upload-box__name" id="nomeArquivo">Nenhum arquivo selecionado</div>
                <div class="upload-box__meta" id="metaArquivo">PDF assinado, até 10 MB (opcional)</div>
              </div>
              <button type="button" class="btn-outline-sm" id="btnTrocarArquivo">Escolher arquivo</button>
              <input type="file" name="documento" id="inputArquivo" accept=".pdf,application/pdf" hidden>
            </div>
          </div>

          <button type="submit" class="btn" @disabled($hemocentros->isEmpty())>Enviar Requisição</button>
        </form>
      </div>

      <!-- ---------- COLUNA DIREITA: TABELA + BANNER ---------- -->
      <div>
        <div class="monitor-card" id="acompanhamento">
          <div class="monitor-card__head">
            <h2>Acompanhamento das requisições</h2>
          </div>

          <div class="table-scroll">
            <table class="requests-table">
              <thead>
                <tr>
                  <th>CNES do hemocentro</th>
                  <th>Tipo</th>
                  <th>Qtd</th>
                  <th>Urgência</th>
                  <th>Status / Timeline</th>
                  <th>Doc</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($pedidos as $pedido)
                  <tr>
                    <td>{{ $pedido->cnes_hem }}</td>
                    <td><span class="blood-tag">{{ $pedido->tipo_sanguineo_ped ?? '—' }}</span></td>
                    <td>{{ $pedido->quant_ped }} unid.</td>
                    <td>
                      <span @class(['urgencia-tag', 'urgencia-tag--'.$pedido->urgencia_ped => isset($urgencias[$pedido->urgencia_ped])])>
                        {{ $urgencias[$pedido->urgencia_ped] ?? $pedido->urgencia_ped ?? 'Não informada' }}
                      </span>
                    </td>
                    <td>
                      @isset($etapas[$pedido->status_ped])
                        <div class="timeline" aria-hidden="true">
                          @foreach ($etapas as $status => $rotulo)
                            <span @class(['timeline__dot', 'timeline__dot--current' => $status === $pedido->status_ped]) title="{{ $rotulo }}"></span>
                            @unless ($loop->last)
                              <span class="timeline__line"></span>
                            @endunless
                          @endforeach
                        </div>
                      @endisset
                      <span class="timeline__label">{{ $pedido->situacao }}</span>
                    </td>
                    <td>
                      @if ($pedido->requisicao)
                        <a class="btn-outline-sm" href="{{ $pedido->requisicao }}" target="_blank" rel="noreferrer">Ver documento</a>
                      @else
                        Sem documento
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td class="requests-table__empty" colspan="6">{{ $pedidos->total() === 0 ? 'Nenhuma requisição registrada.' : 'Nenhuma requisição nesta página.' }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($pedidos->total() > 0)
            <div class="monitor-card__footer">
              @if ($pedidos->isNotEmpty())
                <span>Mostrando {{ $pedidos->firstItem() }}–{{ $pedidos->lastItem() }} de {{ $pedidos->total() }} {{ $pedidos->total() === 1 ? 'requisição' : 'requisições' }}</span>
              @endif
              @if ($pedidos->hasPages())
                <div class="monitor-card__pages" role="navigation" aria-label="Paginação das requisições">
                  @if ($pedidos->previousPageUrl())
                    <a class="btn-outline-sm" href="{{ $pedidos->previousPageUrl() }}" rel="prev">Anterior</a>
                  @else
                    <button class="btn-outline-sm" type="button" disabled>Anterior</button>
                  @endif
                  @if ($pedidos->nextPageUrl())
                    <a class="btn-outline-sm" href="{{ $pedidos->nextPageUrl() }}" rel="next">Próxima</a>
                  @else
                    <button class="btn-outline-sm" type="button" disabled>Próxima</button>
                  @endif
                </div>
              @endif
            </div>
          @endif
        </div>

        <!-- Banner informativo (o conteúdo original desse bloco não veio
             completo do Figma — ajuste o texto como preferir) -->
        <div class="info-banner">
          <div class="info-banner__icon">i</div>
          <div class="info-banner__text">
            <h3>Como funciona o acompanhamento</h3>
            <p>
              A linha do tempo mostra quando o hemocentro recebe, separa e envia as bolsas
              e quando a entrega é registrada. Atualize a página para consultar o status mais recente.
            </p>
          </div>
        </div>
      </div>

    </div>

  </main>


  <script src="{{ asset('js/hospital/base.js') }}"></script>
  <script src="{{ asset('js/hospital/pedidos.js') }}"></script>
</body>
</html>
