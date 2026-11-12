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

  <div class="triage-list">

    <div class="triage-row">
      <div class="triage-id">
        <div class="triage-avatar"><i class="ph-fill ph-drop"></i></div>
        <div>
          <div class="triage-type">O+</div>
          <div class="triage-code">#BV-98231-X</div>
        </div>
      </div>
      <div class="triage-status">
        <span class="badge-pill badge-waiting">Aguardando exame</span>
      </div>
      <div class="triage-doc">
        <button class="doc-btn"><i class="ph ph-file-arrow-up"></i> Anexar Laudo</button>
      </div>
      <div class="triage-action">
        <button class="release-btn is-disabled" disabled><i class="ph ph-check-circle"></i> Liberar para Estoque</button>
      </div>
    </div>

    <div class="triage-row">
      <div class="triage-id">
        <div class="triage-avatar"><i class="ph-fill ph-drop"></i></div>
        <div>
          <div class="triage-type">AB-</div>
          <div class="triage-code">#BV-98245-Y</div>
        </div>
      </div>
      <div class="triage-status">
        <span class="badge-pill badge-attached"><i class="ph ph-check"></i> Exame anexado</span>
      </div>
      <div class="triage-doc">
        <button class="doc-btn"><i class="ph ph-eye"></i> Ver Documento</button>
      </div>
      <div class="triage-action">
        <button class="release-btn is-enabled"><i class="ph ph-check-circle"></i> Liberar para Estoque</button>
      </div>
    </div>

    <div class="triage-row">
      <div class="triage-id">
        <div class="triage-avatar"><i class="ph-fill ph-drop"></i></div>
        <div>
          <div class="triage-type">A+</div>
          <div class="triage-code">#BV-98250-A</div>
        </div>
      </div>
      <div class="triage-status">
        <span class="badge-pill badge-waiting">Aguardando exame</span>
      </div>
      <div class="triage-doc">
        <button class="doc-btn"><i class="ph ph-file-arrow-up"></i> Anexar Laudo</button>
      </div>
      <div class="triage-action">
        <button class="release-btn is-disabled" disabled><i class="ph ph-check-circle"></i> Liberar para Estoque</button>
      </div>
    </div>

    <div class="triage-row">
      <div class="triage-id">
        <div class="triage-avatar"><i class="ph-fill ph-drop"></i></div>
        <div>
          <div class="triage-type">B+</div>
          <div class="triage-code">#BV-98261-B</div>
        </div>
      </div>
      <div class="triage-status">
        <span class="badge-pill badge-attached"><i class="ph ph-check"></i> Exame anexado</span>
      </div>
      <div class="triage-doc">
        <button class="doc-btn"><i class="ph ph-eye"></i> Ver Documento</button>
      </div>
      <div class="triage-action">
        <button class="release-btn is-enabled"><i class="ph ph-check-circle"></i> Liberar para Estoque</button>
      </div>
    </div>

    <div class="triage-row">
      <div class="triage-id">
        <div class="triage-avatar"><i class="ph-fill ph-drop"></i></div>
        <div>
          <div class="triage-type">O-</div>
          <div class="triage-code">#BV-98272-O</div>
        </div>
      </div>
      <div class="triage-status">
        <span class="badge-pill badge-waiting">Aguardando exame</span>
      </div>
      <div class="triage-doc">
        <button class="doc-btn"><i class="ph ph-file-arrow-up"></i> Anexar Laudo</button>
      </div>
      <div class="triage-action">
        <button class="release-btn is-disabled" disabled><i class="ph ph-check-circle"></i> Liberar para Estoque</button>
      </div>
    </div>

  </div>

</main>

<script src="{{ asset('css/hemocentro/triagem.css') }}"></script>
</body>
</html>
