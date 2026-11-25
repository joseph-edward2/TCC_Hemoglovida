<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hemoglovida | Pedidos de Hospitais</title>

<script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hemocentro/pedidos.css') }}">
</head>
<body>

<header>
  <div class="header-left">
    <div class="logo">
      <img src="{{ asset('assets/logo.png') }}" alt="Logo Hemoglovida" class="logo-icon">
      Hemoglovida
    </div>

    <nav>
      <a href="{{ route('hemocentro.inicio') }}">Visão Geral</a>
      <a href="{{ route('hemocentro.atendimento') }}">Atendimento</a>
      <a href="{{ route('hemocentro.estoque') }}">Estoque</a>
      <a href="{{ route('hemocentro.agendamento') }}">Agendamentos</a>
      <a href="{{ route('hemocentro.pedidos') }}" class="active" aria-current="page">Pedidos</a>
      <a href="{{ route('hemocentro.alertas') }}">Alertas</a>
    </nav>
  </div>

  <div class="user">
    <span>Bem-vindo, {{ auth()->user()->nome_fun ?? 'Hemocentro '.auth()->user()->cnes_hem }}</span>
    <a href="{{ route('hemocentro.usuario') }}" class="avatar" aria-label="Minha conta"><i class="ph ph-user"></i></a>
  </div>
</header>

<main class="dashboard">

  <div class="page-header">
    <div>
      <h1>Pedidos de Hospitais</h1>
      <p class="page-desc">Gerencie e priorize o envio de bolsas de sangue para unidades de saúde.</p>
    </div>

    <form class="pedidos-filters" id="filtrosPedidos" method="GET" action="{{ route('hemocentro.pedidos') }}">
      <div class="filter-select-wrap">
        <select class="filter-select" id="filterStatus" name="status" aria-label="Status do pedido">
          <option value="">Status: Todos</option>
          @foreach ($status as $valor => $rotulo)
            <option value="{{ $valor }}" @selected(($filtros['status'] ?? null) === $valor)>{{ $rotulo }}</option>
          @endforeach
        </select>
        <i class="ph ph-caret-down"></i>
      </div>
      <div class="filter-select-row">
        <div class="filter-select-wrap">
          <select class="filter-select" id="filterBlood" name="tipo_sanguineo" aria-label="Tipo sanguíneo">
            <option value="">Tipo Sanguíneo</option>
            @foreach ($tiposSanguineos as $tipo)
              <option value="{{ $tipo }}" @selected(($filtros['tipo_sanguineo'] ?? null) === $tipo)>{{ $tipo }}</option>
            @endforeach
          </select>
          <i class="ph ph-caret-down"></i>
        </div>
        <div class="filter-select-wrap">
          <select class="filter-select" id="filterUrgency" name="urgencia" aria-label="Urgência">
            <option value="">Urgência: Todas</option>
            @foreach ($urgencias as $valor => $rotulo)
              <option value="{{ $valor }}" @selected(($filtros['urgencia'] ?? null) === $valor)>{{ $rotulo }}</option>
            @endforeach
          </select>
          <i class="ph ph-caret-down"></i>
        </div>
      </div>
      <noscript><button class="btn btn-outline" type="submit">Filtrar pedidos</button></noscript>
    </form>
  </div>

  @if ($errors->any())
    <p role="alert">{{ $errors->first() }}</p>
  @endif

  <section class="ped-stats">
    <div class="ped-stat-card">
      <div class="ped-stat-info">
        <span class="ped-stat-label">Pedidos<br>Pendentes</span>
        <span class="ped-stat-value" id="statPending">{{ $totais->pendentes }}</span>
      </div>
      <i class="ph ph-calendar-check ped-stat-icon"></i>
    </div>

    <div class="ped-stat-card">
      <div class="ped-stat-info">
        <span class="ped-stat-label">Em Separação</span>
        <span class="ped-stat-value" id="statSeparating">{{ $totais->em_separacao }}</span>
      </div>
      <i class="ph ph-clipboard-text ped-stat-icon"></i>
    </div>

    <div class="ped-stat-card is-success">
      <div class="ped-stat-info">
        <span class="ped-stat-label">Enviados Hoje</span>
        <span class="ped-stat-value" id="statSentToday">{{ $totais->enviados_hoje }}</span>
      </div>
      <i class="ph ph-truck ped-stat-icon"></i>
    </div>

    <div class="ped-stat-card is-critical">
      <div class="ped-stat-info">
        <span class="ped-stat-label">Pedidos Críticos</span>
        <span class="ped-stat-value" id="statCritical">{{ $totais->criticos }}</span>
      </div>
      <i class="ph ph-warning ped-stat-icon"></i>
    </div>
  </section>

  <h2 class="section-title"><i class="ph ph-warning"></i> Pedidos Críticos</h2>
  <div class="critical-list" id="criticalList">
    @forelse ($criticos as $pedido)
      <article class="critical-card" id="pedido-{{ $pedido->id_ped }}">
        <div class="critical-head">
          <h3>{{ $pedido->nome_hosp }}</h3>
          @if ($pedido->tipo_sanguineo_ped)
            <span class="badge blood">{{ $pedido->tipo_sanguineo_ped }}</span>
          @endif
          <span class="badge critical">{{ $pedido->urgencia }}</span>
        </div>
        <div class="critical-meta">
          <div class="meta-item"><div class="m-label">Qtd. solicitada</div><div class="m-value">{{ $pedido->quantidade }}</div></div>
          <div class="meta-item"><div class="m-label">Paciente / setor</div><div class="m-value">{{ $pedido->paciente_setor_ped ?? 'Não informado' }}</div></div>
          <div class="meta-item"><div class="m-label">Solicitado em</div><div class="m-value">{{ $pedido->solicitadoEm }}</div></div>
          <div class="meta-item"><div class="m-label">Protocolo</div><div class="m-value">#{{ $pedido->id_ped }}</div></div>
          @include('hemocentro.acoes-pedido', ['classeAcoes' => 'critical-actions'])
        </div>
        <div class="stepper" aria-label="Etapas do pedido">
          @php($etapaAtual = array_search($pedido->status_ped, array_keys($etapas), true))
          @foreach ($etapas as $valor => $rotulo)
            <div @class(['step', 'done' => $loop->index < $etapaAtual, 'current' => $valor === $pedido->status_ped]) @if ($valor === $pedido->status_ped) aria-current="step" @endif>
              <div class="dot"><i class="ph {{ $loop->index < $etapaAtual ? 'ph-check' : ['ph-check', 'ph-clipboard-text', 'ph-truck', 'ph-check-circle'][$loop->index] }}"></i></div>
              <div class="label">{{ $rotulo }}</div>
            </div>
          @endforeach
        </div>
      </article>
    @empty
      <p>Nenhum pedido crítico encontrado.</p>
    @endforelse
  </div>
  @if ($criticos->hasMorePages())
    <div class="load-more-wrap"><a class="load-more" data-lista="criticalList" href="{{ $criticos->nextPageUrl() }}">Carregar mais pedidos críticos <i class="ph ph-caret-down"></i></a></div>
  @endif

  <h2 class="section-title">Pedidos Recentes</h2>
  <div class="recent-list" id="recentList">
    @forelse ($recentes as $pedido)
      <article class="recent-card" id="pedido-{{ $pedido->id_ped }}">
        <div class="blood-tag" aria-label="Tipo sanguíneo">{{ $pedido->tipo_sanguineo_ped ?? '—' }}</div>
        <div class="recent-info">
          <div class="r-title">
            <h4>{{ $pedido->nome_hosp }}</h4>
            @if (in_array($pedido->urgencia_ped, ['urgente', 'critica'], true))
              <span @class(['badge', 'critical' => $pedido->urgencia_ped === 'critica', 'urgent' => $pedido->urgencia_ped === 'urgente'])>{{ $pedido->urgencia }}</span>
            @endif
          </div>
          <div class="r-sub">
            Pedido #{{ $pedido->id_ped }} • {{ $pedido->quantidade }} •
            {{ $pedido->paciente_setor_ped ? 'Paciente / setor: '.$pedido->paciente_setor_ped : 'Urgência: '.$pedido->urgencia }}
          </div>
          <div class="recent-status">
            <span class="status-pill" data-status="{{ $pedido->status_ped }}"><span class="sdot"></span>{{ $pedido->situacao }}{{ $pedido->status_ped === 'pendente' ? ' (aguardando confirmação)' : '' }}</span>
            @if ($pedido->status_ped === 'em_separacao')
              <span><i class="ph ph-user"></i> Técnico: {{ $pedido->nome_fun ?? 'Não informado' }}</span>
            @else
              <span><i class="ph ph-clock"></i> Solicitado em {{ $pedido->solicitadoEm }}</span>
            @endif
          </div>
        </div>
        @include('hemocentro.acoes-pedido', ['classeAcoes' => 'recent-actions'])
      </article>
    @empty
      <p>Nenhum pedido recente encontrado.</p>
    @endforelse
  </div>
  @if ($recentes->hasMorePages())
    <div class="load-more-wrap"><a class="load-more" data-lista="recentList" href="{{ $recentes->nextPageUrl() }}">Carregar mais pedidos <i class="ph ph-caret-down"></i></a></div>
  @endif
</main>

@if (session('status'))
  <div class="toast show" id="toast" role="status">{{ session('status') }}</div>
@endif

<script src="{{ asset('js/hemocentro/pedidos.js') }}"></script>
</body>
</html>
