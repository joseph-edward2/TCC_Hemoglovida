@php
  $isAlert = $categoria === 'alerta';
  $event = $editing?->categoria === $categoria ? $editing : null;
  $restoring = old('formulario') === $categoria;
  $value = static function (string $field, ?string $default = null) use ($restoring): ?string {
      if (! $restoring) {
          return $default;
      }
      $input = old($field);
      return is_string($input) ? $input : null;
  };
  $selectedTypes = $restoring ? (array) old('tipos', []) : ($event->tipos ?? []);
@endphp
<div id="form-{{ $categoria }}" class="card {{ $isAlert ? 'card--form' : 'card--campaign-form' }}">
  <div class="card-header">
    <h2 class="card-title">
      {{ $isAlert ? ($event ? 'Editar Alerta' : 'Criar Novo Alerta') : ($event ? 'Editar Campanha' : 'Criar Nova Campanha') }}
    </h2>
    @if ($event)
      <a class="link-edit" href="{{ route('hemocentro.alertas') }}#form-{{ $categoria }}">Cancelar edição</a>
    @endif
  </div>
  <form method="POST" @if (! $isAlert) enctype="multipart/form-data" @endif action="{{ $event ? route('hemocentro.eventos.update', [$categoria, $event->id_evento]) : route('hemocentro.eventos.store', $categoria) }}">
    @csrf
    @if ($event)
      @method('PUT')
    @endif
    <input type="hidden" name="formulario" value="{{ $categoria }}">
    <div @class(['campaign-columns' => ! $isAlert])>
      <div>
        <div class="form-group">
          <label class="form-label" for="{{ $categoria }}-titulo">{{ $isAlert ? 'Título do Alerta' : 'Título da Campanha' }}</label>
          <input class="form-input" id="{{ $categoria }}-titulo" name="titulo" maxlength="255" value="{{ $value('titulo', $event?->titulo) }}" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="{{ $categoria }}-mensagem">{{ $isAlert ? 'Mensagem do Alerta' : 'Mensagem da Campanha' }}</label>
          <textarea class="form-textarea" id="{{ $categoria }}-mensagem" name="mensagem" rows="4" maxlength="10000">{{ $value('mensagem', $event?->desc_evento) }}</textarea>
        </div>
        <div class="form-group">
          <span class="form-label" id="{{ $categoria }}-tipos">Tipos Sanguíneos Alvo</span>
          <div class="chips" role="group" aria-labelledby="{{ $categoria }}-tipos">
            @foreach ($bloodTypes as $bloodType)
              <label class="chip">
                <input type="checkbox" name="tipos[]" value="{{ $bloodType }}" @checked(in_array($bloodType, $selectedTypes, true))>
                {{ $bloodType }}
              </label>
            @endforeach
          </div>
        </div>
        @if (! $isAlert)
          <label class="btn-outline campaign-image-picker">
            Alterar imagem
            <input type="file" name="imagem" accept="image/jpeg,image/png,image/webp" aria-describedby="imagem-arquivo">
          </label>
          <span id="imagem-arquivo" class="card-subtitle" aria-live="polite">JPG, PNG ou WebP · Até 2 MB</span>
        @endif
      </div>
      <div>
        <div class="form-group">
          <label class="form-label" for="{{ $categoria }}-urgencia">Nível de Urgência</label>
          <select class="form-select" id="{{ $categoria }}-urgencia" name="urgencia" required>
            @foreach ($urgencies as $urgency)
              <option @selected($urgency === $value('urgencia', $event->urgencia ?? 'Baixa'))>{{ $urgency }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="{{ $categoria }}-publico">Público-Alvo</label>
          <select class="form-select" id="{{ $categoria }}-publico" name="publico" required>
            @foreach ($audiences[$categoria] as $audience)
              <option @selected($audience === $value('publico', $event->publico_alvo ?? 'Todos os doadores'))>{{ $audience }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="{{ $categoria }}-local">Local</label>
          <input class="form-input" id="{{ $categoria }}-local" name="local" maxlength="255" value="{{ $value('local', $event->local_evento ?? $defaultLocation) }}" required>
        </div>
        <div class="form-row">
          <div class="form-group form-group--half">
            <label class="form-label" for="{{ $categoria }}-inicio">Início</label>
            <input type="date" class="form-input" id="{{ $categoria }}-inicio" name="inicio" value="{{ $value('inicio', $event?->inicio->toDateString() ?? $defaultDate) }}" required>
          </div>
          <div class="form-group form-group--half">
            <label class="form-label" for="{{ $categoria }}-expiracao">Expiração</label>
            <input type="date" class="form-input" id="{{ $categoria }}-expiracao" name="expiracao" value="{{ $value('expiracao', $event?->expiracao_evento) }}">
          </div>
        </div>
      </div>
    </div>
    <div @class(['form-actions-row' => ! $isAlert])>
      @if ($event)
        <button class="btn-primary" type="submit" name="status" value="{{ $event->status_evento }}">Salvar alterações</button>
      @endif
      @if (! $event || $event->status_evento !== 'publicado')
        <button @class(['btn-primary', 'btn--full' => $isAlert]) type="submit" name="status" value="publicado">{{ $isAlert ? 'Publicar Alerta' : 'Publicar Campanha' }}</button>
      @endif
      @if (! $event || $event->status_evento !== 'rascunho')
        <button @class(['btn-outline', 'btn--full btn--mt' => $isAlert]) type="submit" name="status" value="rascunho">Salvar como rascunho</button>
      @endif
    </div>
  </form>
</div>
