<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Hemoglovida | Alertas e Campanhas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hemocentro/alertas.css') }}">
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
      <a href="Hem_Agendamento.blade.php">Agendamentos</a>
      <a href="Hem_Pedidos.blade.php">Pedidos</a>
      <a href="Hem_Alertas.blade.php" class="active">Alertas</a>
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
      <h1 class="page-title">Alertas e Campanhas</h1>
    </div>
    @if (session('status'))
      <p class="highlight-box" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
      <div class="highlight-box" role="alert">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="kpi-row">
      <div class="kpi-card">
        <div class="kpi-content">
          <span class="kpi-label">Alertas Ativos</span>
          <span class="kpi-value">{{ $activeAlerts }}</span>
        </div>
        <div class="kpi-icon-circle" aria-hidden="true"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a10023" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-content">
          <span class="kpi-label">Campanhas Ativas</span>
          <span class="kpi-value">{{ $activeCampaigns }}</span>
        </div>
        <div class="kpi-icon-circle" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a10023" stroke-width="2"><path d="M3 9h6l11-5v16L9 15H3zM6 15l2 6h3l-2-6"/></svg></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-content">
          <span class="kpi-label kpi-label--brand">Doadores Alcançados</span>
          <span class="kpi-value">{{ number_format($reached, 0, ',', '.') }}</span>
        </div>
        <div class="kpi-icon-circle" aria-hidden="true"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a10023" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      </div>
      <div class="kpi-card" title="Média, por evento, da porcentagem de doadores notificados com agendamento não cancelado no mesmo evento.">
        <div class="kpi-content">
          <span class="kpi-label">Taxa Média de Resposta</span>
          <span class="kpi-value">{{ $responseRate === null ? '—' : number_format($responseRate, 1, ',', '.').'%' }}</span>
        </div>
        <div class="kpi-icon-circle" aria-hidden="true"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a10023" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
      </div>
    </div>

    <div class="two-columns">
      <div class="card card--alerts">
        <div class="card-header">
          <h2 class="card-title">Alertas</h2>
          <div class="search-box">
            <svg aria-hidden="true" class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B6B70" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="buscaAlerta" aria-label="Buscar alerta" placeholder="Buscar alerta..." class="search-input">
          </div>
        </div>
        <table class="alerts-table">
          <thead>
            <tr>
              <th>TÍTULO</th>
              <th>TIPO SANGUÍNEO</th>
              <th>URGÊNCIA</th>
              <th>STATUS</th>
              <th>AÇÕES</th>
            </tr>
          </thead>
          <tbody id="alertsBody">
            @forelse ($alerts as $alert)
              <tr data-alerta>
                <td>
                  <div class="alert-title">{{ $alert->titulo }}</div>
                  <div>{{ $alert->desc_evento }}</div>
                  <div class="alert-dates">{{ $alert->local_evento }} &bull; {{ $alert->inicio->format('d/m/Y') }}@if ($alert->expiracao) até {{ $alert->expiracao->format('d/m/Y') }}@endif</div>
                </td>
                <td><span class="badge badge--gray">{{ implode(', ', $alert->tipos) ?: 'Todos' }}</span></td>
                <td><span @class(['badge', 'badge--red' => in_array($alert->urgencia, ['Alta', 'Crítica']), 'badge--gray' => ! in_array($alert->urgencia, ['Alta', 'Crítica'])])>{{ $alert->urgencia ?? '—' }}</span></td>
                <td><span class="status {{ $alert->situacao === 'Ativo' ? 'status--active' : 'status--draft' }}">{{ $alert->situacao }}</span></td>
                <td>
                  <div class="actions">
                    <a class="action-btn" title="Editar" aria-label="Editar {{ $alert->titulo }}" href="{{ route('hemocentro.alertas', ['editar' => $alert->id_evento]) }}#form-alerta"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                    <form method="POST" action="{{ route('hemocentro.eventos.status', ['alerta', $alert->id_evento]) }}">
                      @csrf
                      @method('PATCH')
                      @if ($alert->status_evento === 'publicado')
                        <button class="action-btn" name="status" value="pausado" title="Pausar" aria-label="Pausar {{ $alert->titulo }}"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg></button>
                      @else
                        <button class="action-btn" name="status" value="publicado" title="Publicar" aria-label="Publicar {{ $alert->titulo }}"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></button>
                      @endif
                    </form>
                    <form method="POST" data-excluir action="{{ route('hemocentro.eventos.destroy', ['alerta', $alert->id_evento]) }}">
                      @csrf
                      @method('DELETE')
                      <button class="action-btn" title="Excluir" aria-label="Excluir {{ $alert->titulo }}"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5">Nenhum alerta cadastrado.</td></tr>
            @endforelse
            <tr id="buscaVazia" hidden><td colspan="5">Nenhum alerta encontrado.</td></tr>
          </tbody>
        </table>
      </div>
      @include('hemocentro.form-evento', ['categoria' => 'alerta'])
    </div>

    <section class="section-campaigns">
      <h2 class="section-title">Campanhas</h2>
      @include('hemocentro.form-evento', ['categoria' => 'campanha'])
      <div class="campaign-cards">
        @forelse ($campaigns as $campaign)
          <article class="campaign-card">
            <img src="{{ $campaign->imagem_id ? route('hemocentro.eventos.imagem', $campaign->id_evento) : asset('assets/campanhadoacao.jpeg') }}" alt="" class="campaign-card-img">
            <div class="campaign-card-body">
              <span class="campaign-tag">{{ $campaign->situacao }}</span>
              <h3 class="campaign-card-title">{{ $campaign->titulo }}</h3>
              <p class="campaign-card-desc">{{ $campaign->desc_evento }}</p>
              <p>{{ $campaign->local_evento }} &bull; {{ $campaign->inicio->format('d/m/Y') }}</p>
              <p>{{ $campaign->publico_alvo ?? 'Todos os doadores' }} &bull; {{ implode(', ', $campaign->tipos) ?: 'Todos os tipos sanguíneos' }}</p>
              @if ($campaign->urgencia)
                <p>Urgência {{ mb_strtolower($campaign->urgencia) }}</p>
              @endif
              <p class="campaign-card-desc">Doadores alcançados: {{ $responses->get($campaign->id_evento)->alcance ?? 0 }}</p>
              <div class="campaign-card-footer">
                <span class="campaign-deadline">{{ $campaign->expiracao ? 'Até '.$campaign->expiracao->format('d/m/Y') : 'Sem expiração' }}</span>
                <a class="link-edit" href="{{ route('hemocentro.alertas', ['editar' => $campaign->id_evento]) }}#form-campanha">Editar Campanha</a>
              </div>
            </div>
          </article>
        @empty
          <p>Nenhuma campanha cadastrada.</p>
        @endforelse
      </div>
    </section>

    <section class="section-campaigns">
      <h2 class="section-title">Notificações expiradas</h2>
      @forelse ($expiredEvents as $event)
        <article class="card">
          <h3 class="card-title">{{ $event->titulo }}</h3>
          <p>{{ ucfirst($event->categoria) }} &bull; Expirou em {{ $event->expiracao->format('d/m/Y') }}</p>
          <a class="link-edit" href="{{ route('hemocentro.alertas', ['editar' => $event->id_evento]) }}#form-{{ $event->categoria }}" aria-label="Editar {{ $event->titulo }}">Editar</a>
        </article>
      @empty
        <p>Nenhuma notificação expirada.</p>
      @endforelse
    </section>
  </main>

  <script src="{{ asset('js/hemocentro/alertas.js') }}"></script>
</body>
</html>
