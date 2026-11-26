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
        @isset($totais->recebidos_hoje)
          <span class="ped-stat-label">Recebidos Hoje</span>
          <span class="ped-stat-value" id="statReceivedToday">{{ $totais->recebidos_hoje }}</span>
        @else
          <span class="ped-stat-label">Enviados Hoje</span>
          <span class="ped-stat-value" id="statSentToday">{{ $totais->enviados_hoje }}</span>
        @endisset
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
