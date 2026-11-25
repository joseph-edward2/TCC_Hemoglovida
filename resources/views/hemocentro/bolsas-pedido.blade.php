<p>Pedido #{{ $pedido->id_ped }} · {{ $pedido->tipo_sanguineo_ped ?? 'Tipo não informado' }} · {{ $pedido->quant_ped }} un. solicitadas</p>
<form method="POST" action="{{ route('hemocentro.pedidos.status', $pedido->id_ped) }}" data-reserva="{{ $pedido->quant_ped }}">
  @csrf
  @method('PATCH')
  <input type="hidden" name="status" value="em_separacao">
  <div class="bolsas-scroll" role="group" aria-label="Bolsas disponíveis" tabindex="0">
    @forelse ($bolsas as $bolsa)
      @for ($unidade = 1; $unidade <= $bolsa->quant_bolsa; $unidade++)
        <label class="bolsa-opcao">
          <input type="checkbox" name="unidades[]" value="{{ $bolsa->isbt }}">
          <span><strong>Bolsa {{ $unidade }} de {{ $bolsa->quant_bolsa }} · {{ $pedido->tipo_sanguineo_ped }}</strong><br>
            ISBT {{ $bolsa->isbt }} · Validade {{ $bolsa->data_venc_bolsa ?? 'não informada' }}</span>
        </label>
      @endfor
    @empty
      <p>Nenhuma bolsa disponível para este pedido.</p>
    @endforelse
  </div>
  @if ($bolsas->isNotEmpty())
    @if ($bolsas->sum('quant_bolsa') < $pedido->quant_ped)
      <p>Estoque insuficiente para a quantidade solicitada.</p>
    @else
      <p aria-live="polite"><output>0</output> de {{ $pedido->quant_ped }} selecionadas</p>
      <button class="btn btn-primary" type="submit" disabled>Confirmar separação</button>
    @endif
  @endif
</form>
