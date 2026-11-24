<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Hemoglovida | Configurar Agenda de Doações</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hemocentro/agendamento.css') }}">
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
      <a href="Hem_Atendimento.blade.php">Atendimento</a>
      <a href="Hem_Estoque.blade.php">Estoque</a>
      <a href="Hem_Agendamento.blade.php" class="active">Agendamentos</a>
      <a href="Hem_Pedidos.blade.php">Pedidos</a>
      <a href="Hem_Alertas.blade.php">Alertas</a>
    </nav>
  </div>

    <div class="user">
     <span>Bem-vindo{{ isset(auth()->user()->nome_fun) ? ', '.auth()->user()->nome_fun : '' }}</span>
      <a href="Hem_Usuario.blade.php">
      <div class="avatar">
        <img src="{{ asset('assets/WalterWhite.png') }}" alt="Foto do usuário">
      </div>
      </a>
    </div>
</header>

<main class="dashboard" data-calendar-url="{{ route('hemocentro.agendamento') }}" data-save-url="{{ route('hemocentro.agendamento.update') }}">

  <div class="page-header">
    <div>
      <h1>Configurar Agenda de Doações</h1>
      <p class="page-desc">Defina os dias e horários em que seu hemocentro estará disponível para agendamentos</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Alterações</button>
    </div>
  </div>

  <section class="agenda-grid">

    <div class="panel">
      <div class="toolbar">
        <button class="btn btn-primary"><i class="ph ph-prohibit"></i> Bloquear dia</button>
        <button class="btn btn-primary"><i class="ph ph-check-circle"></i> Liberar dia</button>
        <button class="btn btn-primary"><i class="ph ph-arrows-clockwise"></i> Aplicar padrão semanal</button>
      </div>

      <div class="calendar-header">
        <h2></h2>
        <div class="calendar-nav">
          <button aria-label="Mês anterior"><i class="ph ph-caret-left"></i></button>
          <button aria-label="Próximo mês"><i class="ph ph-caret-right"></i></button>
        </div>
      </div>

      <div class="calendar-weekdays">
        <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sab</span>
      </div>

      <div class="calendar-grid"></div>

      <div class="calendar-legend">
        <span class="legend-item"><span class="legend-swatch"></span> Disponível</span>
        <span class="legend-item"><span class="legend-swatch is-indisponivel"></span> Indisponível</span>
        <span class="legend-item"><span class="legend-swatch is-selecionado"></span> selecionado</span>
      </div>
    </div>

    <div class="panel day-config">
      <div class="day-config-head">
        <h2></h2>
        <p></p>
      </div>

      <div class="day-config-body">
        <div class="toggle-box">
          Hemocentro aberto neste dia
          <label class="switch">
            <input type="checkbox" checked>
            <span class="track"></span>
          </label>
        </div>

        <div class="day-config-subhead">
          <span class="label">Horários e<br>Vagas</span>
          <span class="total"></span>
        </div>

        <div class="horario-list"></div>

        <button class="btn-dashed"><i class="ph ph-plus"></i> Adicionar novo horário</button>
      </div>
    </div>
  </section>

  <section class="panel" style="margin-top:24px;">
    <div class="panel-header">
      <div>
        <h2>Configuração Padrão Semanal</h2>
        <p class="page-desc" style="margin-top:4px;">Aplica-se automaticamente a todos os dias, a menos que haja bloqueio manual</p>
      </div>
    </div>

    <table class="weekly-table">
      <thead>
        <tr>
          <th>Dia da Semana</th>
          <th>Status</th>
          <th>Início</th>
          <th>Fim</th>
          <th>Capacidade (Vagas)</th>
        </tr>
      </thead>
      <tbody>
        @foreach (['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'] as $day)
          <tr @class(['is-saturday' => $day === 'Sábado'])>
            <td class="day-name">{{ $day }}</td>
            <td><button type="button" class="status-check"></button></td>
            <td><input class="time-input" type="time" aria-label="Início, {{ $day }}"></td>
            <td><input class="time-input" type="time" aria-label="Fim, {{ $day }}"></td>
            <td><input class="capacity-input" type="text" inputmode="numeric" aria-label="Capacidade, {{ $day }}"></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>

</main>

  <script>const agenda = {{ Js::from($agenda) }};</script>
  <script src="{{ asset('js/hemocentro/agendamento.js') }}"></script>
</body>
</html>
