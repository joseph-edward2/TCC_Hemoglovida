<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Hemoglovida | Atendimento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hemocentro/atendimento.css') }}">

</head>
<body>

<header>
  <div class="header-left">
    <div class="logo">
      <img src="{{ asset('assets/logo.png') }}" alt="Logo Hemoglovida" class="logo-icon">
      Hemoglovida
    </div>

    <nav>
      <a href="Hem_Tela_Inicial.blade.php">Visão Geral</a>
      <a href="Hem_Atendimento.blade.php" class="active">Atendimento</a>
      <a href="Hem_Estoque.blade.php">Estoque</a>
      <a href="Hem_Agendamento.blade.php">Agendamentos</a>
      <a href="Hem_Pedidos.blade.php">Pedidos</a>
      <a href="Hem_Alertas.blade.php">Alertas</a>
    </nav>
  </div>

    <div class="user">
     <span>Bem-vindo, Dr. Walter White</span>
      <a href="Hem_Usuario.blade.php">
      <div class="avatar">
        <img src="{{ asset('assets/WalterWhite.png') }}" alt="Foto do usuário">
      </div>
      </a>
    </div>
</header>

  <main class="dashboard">

    <div class="page-header">
      <div>
        <h1>Atendimento</h1>
      </div>
    </div>

    <div class="layout">

      <div class="layout__col">

        <section class="card card--atendimento">
          <div class="card__header">
            <h2 class="card__title">Atendimento em Curso</h2>
            @if ($agendamentos->isNotEmpty())
              <span class="badge-time" id="badgeHorario"></span>
            @endif
          </div>

          @if ($agendamentos->isEmpty())
            <p>Nenhum atendimento para exibir.</p>
          @else
            <div class="paciente">
              <span class="paciente__foto" id="pacienteIniciais" aria-hidden="true"></span>
              <div class="paciente__info">
                <p class="paciente__nome" id="pacienteNome"></p>
                <div class="paciente__dados">
                  <span id="pacienteTipo"></span>
                  <span id="pacienteCpf"></span>
                </div>
              </div>
            </div>

            <div class="stepper">
              <div class="step">
                <span class="step__circle"></span>
                <span class="step__label">Agendado</span>
              </div>
              <span class="step__line"></span>
              <div class="step">
                <span class="step__circle"></span>
                <span class="step__label">Chegou</span>
              </div>
              <span class="step__line"></span>
              <div class="step">
                <span class="step__circle"></span>
                <span class="step__label">Concluído</span>
              </div>
            </div>

            <div class="card__actions">
              <button type="button" class="btn btn-primary" id="btnConfirmarChegada" disabled>Confirmar Chegada</button>
              <button type="button" class="btn btn-outline" id="btnDoacaoRealizada" disabled>Doação Realizada</button>
            </div>
            <p id="atendimentoErro" role="alert" hidden></p>
          @endif
        </section>

        <section class="card card--observacoes">
          <div class="card__header">
            <h2 class="card__title">Observações Médicas</h2>
          </div>
          <div class="alerta">
            Nenhuma observação médica disponível.
          </div>
        </section>

      </div>

      <div class="layout__col">

        <section class="card card--agenda">
          <h2 class="card__title">Agendamentos de Hoje</h2>
          <ul class="agenda" id="agenda">
            @forelse ($agendamentos as $agendamento)
              <li class="agenda__item agenda__item--clicavel" role="button" tabindex="0"
                  data-status="{{ $agendamento['status'] }}"
                  data-tipo="{{ $agendamento['tipo'] }}"
                  data-cpf="{{ $agendamento['cpf'] }}"
                  data-chegada="{{ route('hemocentro.atendimento.chegada', $agendamento['protocolo'], false) }}"
                  data-concluir="{{ route('hemocentro.atendimento.concluir', $agendamento['protocolo'], false) }}">
                <span class="agenda__iniciais" aria-hidden="true"></span>
                <span class="agenda__nome">{{ $agendamento['nome'] }}</span>
                <span class="agenda__horario">{{ $agendamento['horario'] }}</span>
                <span class="badge-status"></span>
              </li>
            @empty
              <li>Nenhum agendamento para hoje.</li>
            @endforelse
          </ul>
          <div class="paginacao" id="paginacao"></div>
        </section>

      </div>

    </div>
  </main>

  @if ($agendamentos->isNotEmpty())
    <script src="{{ asset('js/hemocentro/atendimento.js') }}"></script>
  @endif
</body>
</html>
