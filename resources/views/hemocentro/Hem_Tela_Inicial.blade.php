<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Hemoglovida | Visão Geral</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script src="../../js/hemocentro/telainicial.js"></script>
  <link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
</head>
<body>

<header>
  <div class="header-left">
    <div class="logo">
      <img src="{{ asset('assets/logo.png') }}" alt="Logo Hemoglovida" class="logo-icon">
      Hemoglovida
    </div>

    <nav>
      <a href="Hem_Tela_Inicial.blade.php" class="active">Visão Geral</a>
      <a href="Hem_Atendimento.blade.php">Atendimento</a>
      <a href="Hem_Estoque.blade.php">Estoque</a>
      <a href="Hem_Agendamento.blade.php">Agendamentos</a>
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

<main class="dashboard">

  <div class="page-header">
    <div>
      <h1>Visão Geral</h1>
      <p class="page-meta">Dados consultados em <time datetime="{{ $consultedAt->toIso8601String() }}">{{ $consultedAt->format('d/m/Y H:i') }}</time></p>
    </div>
  </div>

  <section class="stats-grid">
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon"><i class="ph ph-drop"></i></div>
      </div>
      <div class="stat-label">Total em Estoque</div>
      <div class="stat-value">{{ $stockTotal }} <span>bolsas</span></div>
    </div>

    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon"><i class="ph ph-calendar-check"></i></div>
      </div>
      <div class="stat-label">Agendamentos de Hoje</div>
      <div class="stat-value">{{ $appointmentsToday }} <span>agendamentos</span></div>
    </div>

    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon"><i class="ph ph-calendar"></i></div>
      </div>
      <div class="stat-label">Próximos Eventos</div>
      <div class="stat-value">{{ $upcomingEvents }} <span>eventos</span></div>
    </div>

    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon"><i class="ph ph-clipboard-text"></i></div>
      </div>
      <div class="stat-label">Pedidos Pendentes</div>
      <div class="stat-value">{{ $pendingRequests }} <span>solicitações</span></div>
    </div>
  </section>

  <div class="section-header">
    <h2>Estoque por Tipo Sanguíneo</h2>
    <a href="Hem_Estoque.blade.php" class="section-link" style="padding-right: 10px;">Ver tudo</a>
  </div>

  <section class="blood-grid">
    @foreach (['O-' => 15, 'A-' => 10, 'O+' => 85, 'A+' => 72, 'B-' => 55, 'B+' => 64, 'AB-' => 45, 'AB+' => 90] as $bloodType => $illustrativeLevel)
      <div class="blood-card">
        <div class="blood-card-top">
          <span class="blood-type">{{ $bloodType }}</span>
        </div>
        <div class="blood-count">{{ $stockByType->get($bloodType, 0) }}</div>
        <span class="blood-unit">BOLSAS</span>
        <div class="progress-track" role="progressbar" aria-label="Nível ilustrativo do estoque {{ $bloodType }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $illustrativeLevel }}">
          <div class="progress-fill" style="width:{{ $illustrativeLevel }}%"></div>
        </div>
        <span class="blood-level">Nível ilustrativo {{ $illustrativeLevel }}%</span>
      </div>
    @endforeach
  </section>

  <section class="panels-grid" style="margin-top:36px;">

    <div class="panel">
      <div class="panel-header">
        <h2>Eventos</h2>
        <a href="Hem_Alertas.blade.php" class="section-link">Ver tudo</a>
      </div>
      <div class="alert-list">
        @forelse ($events as $event)
          @php
            $startsAt = \Carbon\CarbonImmutable::parse($event->data_hora_evento, 'UTC')->setTimezone($consultedAt->getTimezone());
            $isUpcoming = $startsAt >= $consultedAt;
          @endphp
          <div @class(['alert-item', 'is-active' => $isUpcoming])>
            <div class="alert-icon"><i class="ph ph-calendar"></i></div>
            <div class="alert-body">
              <div class="alert-title">{{ $event->tipo_evento }}</div>
              <div class="alert-meta">
                <time datetime="{{ $startsAt->toIso8601String() }}">{{ $startsAt->format('d/m/Y H:i') }}</time>
                &bull; {{ $event->local_evento }}
              </div>
              @if ($event->desc_evento)
                <div class="alert-meta">{{ $event->desc_evento }}</div>
              @endif
            </div>
            <span class="alert-status {{ $isUpcoming ? 'is-ativo' : 'is-encerrado' }}">{{ $isUpcoming ? 'Programado' : 'Data passada' }}</span>
          </div>
        @empty
          <p class="alert-meta">Nenhum evento cadastrado.</p>
        @endforelse
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h2>Pedidos Recentes de Hospitais</h2>
        <a href="Hem_Pedidos.blade.php" class="section-link">Gerenciar Pedidos</a>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th>Hospital</th>
            <th>Descrição</th>
            <th>Qtd</th>
            <th>Data do pedido</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recentRequests as $order)
            @php($requestedAt = \Carbon\CarbonImmutable::parse($order->data_hora_ped, 'UTC')->setTimezone($consultedAt->getTimezone()))
            <tr>
              <td class="hospital-name">{{ $order->nome_hosp }}</td>
              <td>{{ $order->desc_ped }}</td>
              <td>{{ $order->quant_ped }}</td>
              <td><time datetime="{{ $requestedAt->toIso8601String() }}">{{ $requestedAt->format('d/m/Y H:i') }}</time></td>
              <td>
                <span @class(['pill', 'pill-pendente' => $order->status_ped === 'pendente', 'pill-enviado' => $order->status_ped === 'enviado'])>
                  {{ ucfirst($order->status_ped) }}
                </span>
              </td>
            </tr>
          @empty
            <tr><td colspan="5">Nenhum pedido cadastrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="chart-panel donations-chart">
    <div class="chart-header">
      <div>
        <h2>Doações nos últimos 7 dias</h2>
        <p>Número de doações por dia</p>
      </div>
    </div>

    @php($scaleMaximum = 60)
    <div class="bar-chart">
      <div class="bar-axis" aria-hidden="true">
        @foreach ([$scaleMaximum, 50, 40, 30, 20, 10, 1] as $tick)
          <div class="bar-tick" style="bottom:{{ $tick * 100 / $scaleMaximum }}%"><span>{{ $tick }}</span></div>
        @endforeach
      </div>
      <ol class="bar-columns" aria-label="Doações por dia">
        @foreach ($dailyDonations as $donations)
          <li>
            <div class="bar-slot" aria-hidden="true">
              <div class="bar" style="height:{{ min($donations['count'], $scaleMaximum) * 100 / $scaleMaximum }}%"></div>
            </div>
            <div class="bar-day">
              <time datetime="{{ $donations['day']->toDateString() }}" title="{{ $donations['day']->isoFormat('dddd, DD/MM') }}">{{ $donations['day']->isoFormat('ddd') }}</time>
              <strong>{{ $donations['count'] }}</strong>
            </div>
          </li>
        @endforeach
      </ol>
    </div>
  </section>


</main>
</body>
</html>
