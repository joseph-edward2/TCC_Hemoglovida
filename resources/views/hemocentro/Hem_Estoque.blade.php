<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Hemoglovida | Gestão de Estoque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/hemocentro/estoque.css') }}">
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
      <a href="Hem_Estoque.blade.php" class="active">Estoque</a>
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
      <h1>Gestão de Estoque</h1>
    </div>
    <div class="page-actions">
      <button class="btn btn-primary"><i class="ph ph-plus-square"></i> Registrar Entrada</button>
    </div>
  </div>

  @if ($criticalBloodTypes !== [])
    <div class="alert-banner">
      <div class="alert-banner-left">
        <div class="alert-banner-icon"><i class="ph ph-warning"></i></div>
        <div>
          <div class="alert-banner-title">Estoque crítico</div>
          <div class="alert-banner-text">O estoque de {{ implode(', ', $criticalBloodTypes) }} está abaixo de {{ $criticalStockPercentage }}% da meta.</div>
        </div>
      </div>
      <a class="btn btn-primary" href="{{ route('hemocentro.alertas') }}">Emitir alerta</a>
    </div>
  @endif

  <section class="stock-grid">
    @foreach ($stockCards as $bloodType => $card)
      <div class="stock-card">
        <div class="stock-card-head">
          <span class="blood-type">{{ $bloodType }}</span>
          @if ($card['isCritical'])
            <span class="badge-sq">Crítico</span>
          @endif
        </div>
        <div class="stock-count"><span class="count-value">{{ $card['quantity'] }}</span><span class="count-unit">{{ $card['quantity'] === 1 ? 'bolsa' : 'bolsas' }}</span></div>
        <div class="progress-track"><div class="progress-fill" style="width:{{ min(100, max(0, $card['percentage'])) }}%"></div></div>
        <div class="stock-meta">
          <span class="meta-left">Meta: {{ $card['target'] }}<br>bolsas</span>
          <span class="meta-right">{{ $card['percentage'] }}% do<br>ideal</span>
        </div>
        @if ($card['expiring'] > 0)
          <div class="stock-footer is-warning"><i class="ph ph-calendar-x"></i> {{ $card['expiring'] }} {{ $card['expiring'] === 1 ? 'bolsa' : 'bolsas' }} com validade em até 7 dias</div>
        @else
          <div class="stock-footer"><i class="ph ph-check-circle"></i> Nenhuma expiração próxima</div>
        @endif
      </div>
    @endforeach
  </section>

  <section class="stock-bottom-grid">

    <div class="stock-side">
      <div class="panel stock-movement">
        <h2>Movimentação</h2>
        <p>Bolsas coletadas nos últimos 30 dias</p>

        <div class="bar-chart" aria-hidden="true">
          @foreach ($collections['periods'] as $period)
            <div class="bar-col"><div class="bar" style="height:{{ round(max(0, $period['quantity']) * 100 / $collections['max'], 2) }}%"></div></div>
          @endforeach
        </div>
        <div class="bar-labels">
          @foreach ($collections['periods'] as $period)
            <span>{{ $period['start']->format('d/m') }}<br>–{{ $period['end']->format('d/m') }}<br><strong>{{ $period['quantity'] }}</strong></span>
          @endforeach
        </div>

        <div class="totals-row">
          <div>
            <span class="total-label">Total de bolsas coletadas</span>
            <span class="total-value">{{ $collections['total'] }}</span>
          </div>
        </div>
      </div>

      <a href="{{ route('hemocentro.triagem') }}" class="btn btn-primary btn-block"><i class="ph ph-flask"></i> Triagem de Bolsas</a>
    </div>

    <div class="panel" id="inventario">
      <div class="panel-header">
        <h2>Inventário Detalhado</h2>
        <div class="panel-actions">
          <button class="icon-btn"><i class="ph ph-download-simple"></i></button>
          <button class="icon-btn"><i class="ph ph-printer"></i></button>
        </div>
      </div>

      <div class="inventory-table">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">ID Bolsa</th>
              <th scope="col">Tipo</th>
              <th scope="col">Qtd.</th>
              <th scope="col">Coleta</th>
              <th scope="col">Validade</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($inventory as $bag)
              <tr>
                <td class="hospital-name">{{ $bag['isbt'] }}</td>
                <td><span class="type-pill"><i class="ph ph-drop"></i> {{ $bag['bloodType'] }}</span></td>
                <td>{{ $bag['quantity'] }}</td>
                <td>{{ $bag['collectedOn'] ?? '—' }}</td>
                <td @class(['date-alert' => $bag['expiryAlert']])>{{ $bag['expiresOn'] ?? '—' }}</td>
                <td>
                  <span @class(['status-dot', 'is-alert' => $bag['reserved']]) aria-hidden="true"></span><span @class(['status-text', 'is-alert' => $bag['reserved']])>{{ $bag['status'] }}</span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6">{{ $inventory->total() === 0 ? 'Nenhuma bolsa cadastrada.' : 'Nenhuma bolsa nesta página.' }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($inventory->total() > 0)
        <div class="table-footer">
          @if ($inventory->isNotEmpty())
            <span>Mostrando {{ $inventory->firstItem() }}–{{ $inventory->lastItem() }} de {{ $inventory->total() }} {{ $inventory->total() === 1 ? 'registro' : 'registros' }}</span>
          @endif
          @if ($inventory->hasPages())
            <div class="pagination" role="navigation" aria-label="Paginação do inventário">
              @if ($inventory->previousPageUrl())
                <a class="btn btn-outline" href="{{ $inventory->previousPageUrl() }}" rel="prev">Anterior</a>
              @else
                <button class="btn btn-outline" type="button" disabled>Anterior</button>
              @endif
              @if ($inventory->nextPageUrl())
                <a class="btn btn-outline" href="{{ $inventory->nextPageUrl() }}" rel="next">Próximo</a>
              @else
                <button class="btn btn-outline" type="button" disabled>Próximo</button>
              @endif
            </div>
          @endif
        </div>
      @endif
    </div>
  </section>

</main>

 <script src="{{ asset('js/hemocentro/estoque.js') }}"></script>

</body>
</html>
