<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hemoglovida | Cadastro de Funcionário</title>
  <!-- CSS base (cores, reset, header, footer) — sempre importar ANTES do CSS da página -->
  <link rel="stylesheet" href="{{ asset('css/hospital/base.css') }}">
  <!-- CSS específico desta tela (estilos do card, campos, etc.) -->
  <link rel="stylesheet" href="{{ asset('css/hospital/cadastro-funcionario.css') }}">
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
</head>
<body>

  <!-- ====================================================
       CABEÇALHO
       ====================================================
       Mesmo header simplificado da tela de Login: só logo
       e nome do sistema, sem menu de navegação.
       ==================================================== -->
  <header>
    <div class="header-left">
      <div class="logo">
        <img src="{{ asset('assets/logo.png') }}" alt="Logo Hemoglovida" class="logo-icon">
        Hemoglovida
      </div>
    </div>
  </header>

  <!-- ====================================================
       CONTEÚDO PRINCIPAL
       ==================================================== -->
  <main class="page">

    <!-- Bloco de identidade (logo grande + nome) -->
    <section class="hero">
      <div class="hero__row">
        <div class="hero__badge">
          <img class="hero__logo" src="{{ asset('assets/logo.png') }}" alt="Logo Hemoglovida">
        </div>
        <h1 class="hero__name">Hemoglovida</h1>
      </div>
      <h2 class="hero__subtitle">Cadastro de Funcionário</h2>
    </section>

    <!-- ====================================================
         CARTÃO DO FORMULÁRIO
         ====================================================
         Contém todos os campos para cadastrar um funcionário
         deste hospital. O vínculo vem da sessão autenticada.
         ==================================================== -->
    <section class="card">
      <h3 class="card__title">Preencha os dados do funcionário</h3>

      <form id="cadastroFuncionarioForm" method="POST" action="{{ route('hospital.funcionario.store') }}" novalidate>
        @csrf

        <!-- CAMPO 1: Nome do Funcionário -->
        <div class="field">
          <label class="field__label" for="nome_funcionario">Nome do Funcionário</label>
          <input
            class="field__input"
            type="text"
            id="nome_funcionario"
            name="nome_funcionario"
            placeholder="Digite o nome completo"
            value="{{ old('nome_funcionario') }}"
            required>
          @error('nome_funcionario')
            <small class="field__error">{{ $message }}</small>
          @enderror
        </div>

        <!-- CAMPO 2: CPF (login do funcionário) -->
        <div class="field">
          <label class="field__label" for="cpf_funcionario">CPF (login)</label>
          <input
            class="field__input"
            type="text"
            id="cpf_funcionario"
            name="cpf_funcionario"
            placeholder="000.000.000-00"
            inputmode="numeric"
            autocomplete="off"
            value="{{ old('cpf_funcionario') }}"
            required>
          @error('cpf_funcionario')
            <small class="field__error">{{ $message }}</small>
          @enderror
          <small class="field__hint">O CPF será usado para entrar no sistema.</small>
        </div>

        <!-- CAMPO 3: Senha -->
        <!-- Usa o mesmo padrão da tela de Login: input dentro de .field__wrapper
             com botão de mostrar/ocultar senha posicionado à direita. -->
        <div class="field">
          <label class="field__label" for="senha_funcionario">Senha</label>
          <div class="field__wrapper">
            <input
              class="field__input field__input--password"
              type="password"
              id="senha_funcionario"
              name="senha_funcionario"
              placeholder="••••••••"
              autocomplete="new-password"
              required>
            <button type="button" class="field__toggle" id="toggleSenha" aria-label="Mostrar senha">
              <i class="ph ph-eye" aria-hidden="true"></i>
            </button>
          </div>
          @error('senha_funcionario')
            <small class="field__error">{{ $message }}</small>
          @enderror
        </div>

        <!-- CAMPO 4: Cargo -->
        <!-- O vínculo com o hospital vem da sessão autenticada, nunca do formulário. -->
        <div class="field">
          <label class="field__label" for="tipo_funcionario">Cargo</label>
          <select class="field__select" id="tipo_funcionario" name="tipo_funcionario" required>
            <option value="" disabled {{ old('tipo_funcionario') ? '' : 'selected' }}>Selecione o cargo…</option>
            @foreach (\App\Enums\CargoFuncionario::cases() as $cargo)
              <option value="{{ $cargo->value }}" @selected(old('tipo_funcionario') === $cargo->value)>{{ $cargo->label() }}</option>
            @endforeach
          </select>
          @error('tipo_funcionario')
            <small class="field__error">{{ $message }}</small>
          @enderror
          <small class="field__hint">O vínculo com este hospital é definido pela sua sessão.</small>
        </div>

        <button type="submit" class="btn">Cadastrar</button>
      </form>

      <!-- Mensagem de feedback (erro ou sucesso) -->
      @if ($errors->any())
        <p class="msg msg--error" id="msg" role="alert">{{ $errors->first() }}</p>
      @elseif (session('status'))
        <p class="msg msg--success" id="msg" role="alert">{{ session('status') }}</p>
      @else
        <p class="msg" id="msg" hidden></p>
      @endif
    </section>

  </main>

  <script src="{{ asset('js/hospital/base.js') }}"></script>
  <script src="{{ asset('js/hospital/cadastro-funcionario.js') }}"></script>

 

</body>
</html>
