<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Hemoglovida | Triagem de Bolsas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <link rel="stylesheet" href="{{ asset('css/hemocentro/triagem.css') }}">
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

  <div class="search-bar-row">
    <div class="search-box">
      <i class="ph ph-magnifying-glass"></i>
      <input type="text" placeholder="Buscar por código ou tipo sanguíneo...">
    </div>
    <button class="btn-filter"><i class="ph ph-funnel"></i> Filtrar</button>
  </div>

  @if (session('status'))
    <p role="status">{{ session('status') }}</p>
  @endif
  @if ($errors->any())
    <p role="alert">{{ $errors->first() }}</p>
  @endif

  <div class="triage-list">
    @forelse ($bags as $bag)
      <div class="triage-row">
        <div class="triage-id">
          <div class="triage-avatar"><i class="ph-fill ph-drop"></i></div>
          <div>
            <div class="triage-type">{{ strtoupper(trim($bag->tipo_bolsa)) }}</div>
            <div class="triage-code">{{ $bag->isbt }}</div>
          </div>
        </div>
        <div class="triage-status">
          @if ($bag->laudo_path)
            <span class="badge-pill badge-attached"><i class="ph ph-check"></i> Exame anexado</span>
          @else
            <span class="badge-pill badge-waiting">Aguardando exame</span>
          @endif
        </div>
        <div class="triage-doc">
          @if ($bag->laudo_path)
            <a class="doc-btn" href="{{ route('hemocentro.triagem.laudo', $bag->isbt) }}" target="_blank" rel="noopener"><i class="ph ph-eye"></i> Ver Documento</a>
          @else
            <form method="post" action="{{ route('hemocentro.triagem.anexar', $bag->isbt) }}" enctype="multipart/form-data">
              @csrf
              <label class="doc-btn" title="PDF, PNG ou JPG de até 10 MB">
                <i class="ph ph-file-arrow-up"></i> Anexar Laudo
                <input type="file" name="laudo" accept=".pdf,.png,.jpg,.jpeg" required>
              </label>
              <noscript><button type="submit">Enviar laudo</button></noscript>
            </form>
          @endif
        </div>
        <form class="triage-action" method="post" action="{{ route('hemocentro.triagem.concluir', $bag->isbt) }}">
          @csrf
          <button type="submit" name="status" value="disponivel" class="release-btn {{ $bag->laudo_path ? 'is-enabled' : 'is-disabled' }}" @disabled(!$bag->laudo_path)><i class="ph ph-check-circle"></i> Liberar para Estoque</button>
          <button type="submit" name="status" value="recusada" class="release-btn {{ $bag->laudo_path ? 'is-enabled' : 'is-disabled' }}" @disabled(!$bag->laudo_path)><i class="ph ph-x-circle"></i> Recusar Bolsa</button>
        </form>
      </div>
    @empty
      <p class="triage-empty">Nenhuma bolsa em análise.</p>
    @endforelse
    @if ($bags->isNotEmpty())
      <p class="triage-empty" id="filter-empty" hidden>Nenhuma bolsa encontrada para este filtro.</p>
    @endif
  </div>
</main>

<script src="{{ asset('js/hemocentro/triagem.js') }}"></script>
</body>
</html>
