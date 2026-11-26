<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hemoglovida — Gestão de Funcionários</title>
  <link rel="stylesheet" href="{{ asset('css/hemocentro/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/funcionarios.css') }}">
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
</head>
<body>
@php
  $rotaBase = 'hemocentro.funcionarios';
@endphp

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
      <a href="{{ route('hemocentro.pedidos') }}">Pedidos</a>
      <a href="{{ route('hemocentro.alertas') }}">Alertas</a>
      @can('gerenciar-funcionarios', 'hemocentro')
        <a class="active" href="{{ route($rotaBase) }}">Funcionários</a>
      @endcan
    </nav>
  </div>

  <div class="user">
    <span>Bem-vindo, {{ $nomeExibido() }}</span>
    <a href="{{ route('hemocentro.usuario') }}">
      <div class="avatar">
        <img src="{{ $fotoPerfil() }}" alt="Foto de perfil">
      </div>
    </a>
  </div>
</header>

<main class="func-page">
  <div class="func-page__topo">
    <div>
      <h1 class="func-page__titulo">Gestão de Funcionários</h1>
      <p class="func-page__subtitulo">Gerencie o acesso dos profissionais vinculados a esta unidade</p>
    </div>
    <button type="button" class="func-btn func-btn--primary" id="funcNovoBtn">
      <i class="ph ph-plus" aria-hidden="true"></i> Novo Funcionário
    </button>
  </div>

  <section class="func-kpis" aria-label="Resumo dos funcionários">
    <div class="func-kpi">
      <div class="func-kpi__icone"><i class="ph ph-users" aria-hidden="true"></i></div>
      <div>
        <span class="func-kpi__valor">{{ $totalFuncionarios }}</span>
        <span class="func-kpi__rotulo">Total de Funcionários</span>
      </div>
    </div>
    <div class="func-kpi">
      <div class="func-kpi__icone func-kpi__icone--vinho"><i class="ph ph-shield-check" aria-hidden="true"></i></div>
      <div>
        <span class="func-kpi__valor">{{ $totalResponsaveis }}</span>
        <span class="func-kpi__rotulo">Responsáveis</span>
      </div>
    </div>
    <div class="func-kpi">
      <div class="func-kpi__icone func-kpi__icone--neutro"><i class="ph ph-briefcase" aria-hidden="true"></i></div>
      <div>
        <span class="func-kpi__valor">{{ $totalAdministrativos }}</span>
        <span class="func-kpi__rotulo">Administrativos</span>
      </div>
    </div>
  </section>

  <section class="func-card">
    <div class="func-toolbar">
      <label class="func-busca">
        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="funcBusca" placeholder="Buscar por nome ou CPF..." aria-label="Buscar funcionários">
      </label>
      <select class="func-select" id="funcCargo" aria-label="Filtrar por cargo">
        <option value="">Todos os cargos</option>
        <option value="Responsavel">Responsáveis</option>
        <option value="Administrativo">Administrativos</option>
      </select>
    </div>

    <div class="func-tabela-wrap">
      <table class="func-tabela" id="funcTabela">
        <thead>
          <tr>
            <th>ID</th>
            <th>Funcionário</th>
            <th>CPF</th>
            <th>Instituição</th>
            <th>Cargo / Nível de Acesso</th>
            <th class="func-col-acoes">Ações</th>
          </tr>
        </thead>
        <tbody id="funcCorpo">
          @forelse ($funcionarios as $funcionario)
            @php
              $partesNome = preg_split('/\s+/', trim($funcionario->nome_fun));
              $iniciais = mb_strtoupper(mb_substr($partesNome[0] ?? '', 0, 1).mb_substr($partesNome[1] ?? '', 0, 1));
              $cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $funcionario->cpf_fun ?? '');
              $eProprioUsuario = $codigoFuncionarioLogado !== null && (int) $codigoFuncionarioLogado === (int) $funcionario->getKey();
            @endphp
            <tr
              data-nome="{{ $funcionario->nome_fun }}"
              data-cpf="{{ $cpfFormatado }}"
              data-cargo="{{ $funcionario->tipo_fun?->value }}"
              data-codigo="{{ $funcionario->getKey() }}"
              data-delete="{{ route($rotaBase.'.destroy', $funcionario) }}"
            >
              <td class="func-id">{{ $funcionario->cod_reg_fun }}</td>
              <td>
                <div class="func-pessoa">
                  <div class="func-avatar">{{ $iniciais }}</div>
                  <div>
                    <span class="func-pessoa__nome">{{ $funcionario->nome_fun }}</span>
                  </div>
                </div>
              </td>
              <td class="func-cpf">{{ $funcionario->cpf_mascarado }}</td>
              <td>
                <span class="func-instituicao__nome">{{ $funcionario->hemocentro?->rotulo ?? \App\Models\Hemocentro::rotuloParaCnes($funcionario->cnes_hem) }}</span>
                <span class="func-instituicao__codigo">CNES {{ $funcionario->cnes_hem ?: '—' }}</span>
              </td>
              <td>
                @if ($funcionario->tipo_fun === \App\Enums\CargoFuncionario::Responsavel)
                  <span class="func-badge func-badge--responsavel"><i class="ph ph-shield-check" aria-hidden="true"></i> Responsável</span>
                @else
                  <span class="func-badge func-badge--administrativo"><i class="ph ph-user" aria-hidden="true"></i> Administrativo</span>
                @endif
              </td>
              <td class="func-col-acoes">
                <div class="func-acoes">
                  <button type="button" class="func-acao func-editar" title="Editar" aria-label="Editar {{ $funcionario->nome_fun }}">
                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                  </button>
                  <button type="button" class="func-acao func-acao--excluir func-excluir" title="{{ $eProprioUsuario ? 'Você não pode excluir sua própria conta' : 'Excluir' }}" aria-label="Excluir {{ $funcionario->nome_fun }}" @disabled($eProprioUsuario)>
                    <i class="ph ph-trash" aria-hidden="true"></i>
                  </button>
                </div>
              </td>
            </tr>
          @empty
          @endforelse
        </tbody>
      </table>

      <div class="func-empty" id="funcEmptyState" @if ($funcionarios->isNotEmpty()) hidden @endif>
        <i class="ph ph-user-slash" aria-hidden="true"></i>
        <strong>Nenhum funcionário encontrado</strong>
        <span>Verifique a busca ou cadastre um novo funcionário para esta unidade.</span>
      </div>
    </div>
  </section>
</main>

<div class="func-overlay" id="funcModal" hidden>
  <div class="func-modal" role="dialog" aria-modal="true" aria-labelledby="funcModalTitulo">
    <div class="func-modal__header">
      <h2 id="funcModalTitulo">Cadastrar Novo Funcionário</h2>
      <button type="button" class="func-modal__close" id="funcFechar" aria-label="Fechar"><i class="ph ph-x"></i></button>
    </div>

    <form id="funcForm" method="POST" action="{{ route($rotaBase.'.store') }}" novalidate
      data-store="{{ route($rotaBase.'.store') }}"
      data-update-template="{{ route($rotaBase.'.update', ['funcionario' => '__CODIGO__']) }}">
      @csrf
      <input type="hidden" name="_method" id="funcMethod" value="{{ old('_method', 'POST') }}">
      <input type="hidden" name="funcionario_codigo" id="funcCodigo" value="{{ old('funcionario_codigo') }}">

      <div class="func-modal__body">
        <div class="func-field">
          <label for="funcNome">Nome Completo</label>
          <input type="text" id="funcNome" name="nome_funcionario" value="{{ old('nome_funcionario') }}" maxlength="255" required autocomplete="name">
          @error('nome_funcionario') <small class="func-field__error">{{ $message }}</small> @enderror
        </div>

        <div class="func-field">
          <label for="funcCpf">CPF</label>
          <input type="text" id="funcCpf" name="cpf_funcionario" value="{{ old('cpf_funcionario') }}" placeholder="000.000.000-00" inputmode="numeric" autocomplete="off" maxlength="14" required>
          <small class="func-field__hint" id="funcCpfHint">O CPF será usado para entrar no sistema.</small>
          @error('cpf_funcionario') <small class="func-field__error">{{ $message }}</small> @enderror
        </div>

        <div class="func-field">
          <label for="funcCargoForm">Cargo</label>
          <select id="funcCargoForm" name="tipo_funcionario" required>
            <option value="" disabled @selected(! old('tipo_funcionario'))>Selecione o cargo</option>
            @foreach ($cargos as $cargo)
              <option value="{{ $cargo->value }}" @selected(old('tipo_funcionario') === $cargo->value)>{{ $cargo->label() }}</option>
            @endforeach
          </select>
          <small class="func-field__hint">Responsáveis possuem permissão para gerenciar a equipe e acessar configurações institucionais.</small>
          @error('tipo_funcionario') <small class="func-field__error">{{ $message }}</small> @enderror
        </div>

        <div class="func-field">
          <label for="funcSenha">Senha</label>
          <div class="func-field__senha">
            <input type="password" id="funcSenha" name="senha_funcionario" autocomplete="new-password" minlength="8">
            <button type="button" class="func-field__toggle" id="funcToggleSenha" aria-label="Mostrar senha"><i class="ph ph-eye"></i></button>
          </div>
          <small class="func-field__hint" id="funcSenhaHint" hidden>Deixe em branco caso não queira alterar a senha.</small>
          @error('senha_funcionario') <small class="func-field__error">{{ $message }}</small> @enderror
        </div>

        <div class="func-field" id="funcConfirmarCampo">
          <label for="funcConfirmarSenha">Confirmar Senha</label>
          <input type="password" id="funcConfirmarSenha" name="senha_funcionario_confirmation" autocomplete="new-password" minlength="8">
        </div>
      </div>

      <div class="func-modal__footer">
        <button type="button" class="func-btn func-btn--outline" id="funcCancelar">Cancelar</button>
        <button type="submit" class="func-btn func-btn--primary">Salvar Funcionário</button>
      </div>
    </form>
  </div>
</div>

<div class="func-overlay" id="funcModalExcluir" hidden>
  <div class="func-modal" role="alertdialog" aria-modal="true" aria-labelledby="funcExcluirTitulo">
    <div class="func-modal__header">
      <h2 id="funcExcluirTitulo">Excluir Funcionário</h2>
      <button type="button" class="func-modal__close" id="funcFecharExcluir" aria-label="Fechar"><i class="ph ph-x"></i></button>
    </div>

    <form id="funcFormExcluir" method="POST">
      @csrf
      <input type="hidden" name="_method" value="DELETE">

      <div class="func-modal__body">
        <div class="func-alerta-exclusao">
          <i class="ph ph-warning-circle" aria-hidden="true"></i>
          <p>Tem certeza que deseja remover o acesso de <strong id="funcExcluirNome"></strong>? Esta ação é permanente e revogará o login do usuário no sistema.</p>
        </div>
      </div>

      <div class="func-modal__footer">
        <button type="button" class="func-btn func-btn--outline" id="funcCancelarExcluir">Cancelar</button>
        <button type="submit" class="func-btn func-btn--danger">Sim, Excluir</button>
      </div>
    </form>
  </div>
</div>

@if (session('status') || $errors->any())
  <div class="func-toast {{ $errors->any() ? 'func-toast--erro' : '' }}" id="funcToast" role="status">
    <i class="ph {{ $errors->any() ? 'ph-warning-circle' : 'ph-check-circle' }}" aria-hidden="true"></i>
    <span>{{ session('status') ?? $errors->first() }}</span>
    <button type="button" id="funcFecharToast" aria-label="Fechar aviso"><i class="ph ph-x"></i></button>
  </div>
@endif

<script src="{{ asset('js/funcionarios.js') }}" data-abrir-modal="{{ $errors->any() ? '1' : '0' }}"></script>
</body>
</html>
