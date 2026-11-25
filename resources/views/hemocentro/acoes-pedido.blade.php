<form class="{{ $classeAcoes }}" method="POST" action="{{ route('hemocentro.pedidos.status', $pedido->id_ped) }}">
  @csrf
  @method('PATCH')
  @if ($pedido->status_ped === 'pendente')
    <button class="btn btn-primary" type="button" data-bolsas="{{ route('hemocentro.pedidos.bolsas', $pedido->id_ped) }}" aria-controls="separar-pedido">Aceitar e Separar</button>
    <button class="btn btn-ghost-danger" type="button" data-abrir-modal aria-controls="recusa-{{ $pedido->id_ped }}">Recusar</button>
  @elseif ($pedido->status_ped === 'em_separacao')
    <button class="btn btn-primary" type="submit" name="status" value="enviado">{{ $pedido->urgencia_ped === 'critica' ? 'Marcar como Enviado' : 'Marcar Pronto' }}</button>
  @endif
  <button class="btn btn-outline" type="button" data-abrir-modal aria-controls="detalhes-{{ $pedido->id_ped }}">Ver Detalhes</button>
</form>

@if ($pedido->status_ped === 'pendente')
  <dialog class="modal" id="recusa-{{ $pedido->id_ped }}" aria-labelledby="titulo-recusa-{{ $pedido->id_ped }}">
    <h2 id="titulo-recusa-{{ $pedido->id_ped }}">Recusar pedido #{{ $pedido->id_ped }}</h2>
    <p>Destinatário: {{ $pedido->nome_hosp }}</p>
    <form method="POST" action="{{ route('hemocentro.pedidos.status', $pedido->id_ped) }}">
      @csrf
      @method('PATCH')
      <input type="hidden" name="status" value="recusado">
      <label for="motivo-{{ $pedido->id_ped }}">Motivo da recusa</label>
      <textarea class="motivo-recusa" id="motivo-{{ $pedido->id_ped }}" name="motivo" rows="4" maxlength="4000" required></textarea>
      <footer class="requisicao-actions">
        <button type="submit" formmethod="dialog" formnovalidate class="btn btn-outline">Cancelar</button>
        <button type="submit" class="btn btn-primary">Enviar</button>
        
      </footer>
    </form>
  </dialog>
@endif

<dialog class="modal" id="detalhes-{{ $pedido->id_ped }}" aria-labelledby="titulo-pedido-{{ $pedido->id_ped }}" closedby="any">
  <form method="dialog"><button class="close" aria-label="Fechar detalhes"><i class="ph ph-x"></i></button></form>
  <h2 id="titulo-pedido-{{ $pedido->id_ped }}">{{ $pedido->nome_hosp }}</h2>
  <div class="details-body">
    <div class="d-row"><span>Protocolo</span><span>#{{ $pedido->id_ped }}</span></div>
    <div class="d-row"><span>Tipo sanguíneo</span><span>{{ $pedido->tipo_sanguineo_ped ?? 'Não informado' }}</span></div>
    <div class="d-row"><span>Quantidade</span><span>{{ $pedido->quantidade }}</span></div>
    <div class="d-row"><span>Paciente / setor</span><span>{{ $pedido->paciente_setor_ped ?? 'Não informado' }}</span></div>
    <div class="d-row"><span>Urgência</span><span>{{ $pedido->urgencia }}</span></div>
    <div class="d-row"><span>Status atual</span><span>{{ $pedido->situacao }}</span></div>
    <div class="d-row"><span>Solicitado em</span><span>{{ $pedido->solicitadoEm }}</span></div>
    @if ($pedido->enviadoEm)
      <div class="d-row"><span>Enviado em</span><span>{{ $pedido->enviadoEm }}</span></div>
    @endif
    <div class="d-row"><span>Técnico responsável</span><span>{{ $pedido->nome_fun ?? 'Não informado' }}</span></div>
    <div class="d-row"><span>Descrição</span><span>{{ $pedido->desc_ped }}</span></div>
    @foreach ($reservas->get($pedido->id_ped, []) as $reserva)
      <div class="d-row"><span>ISBT {{ $reserva->isbt }}</span><span>{{ $reserva->quantidade }} un.</span></div>
    @endforeach
  </div>
  <footer class="requisicao-actions">
    @if ($pedido->requisicao)
      <a class="btn btn-primary" href="{{ $pedido->requisicao }}" target="_blank" rel="noopener noreferrer">Ver requisição</a>
    @else
      <span>Requisição indisponível</span>
      <button class="btn btn-outline" type="button" disabled>Ver requisição</button>
    @endif
  </footer>
</dialog>
