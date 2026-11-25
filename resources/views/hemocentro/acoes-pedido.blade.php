<form class="{{ $classeAcoes }}" method="POST" action="{{ route('hemocentro.pedidos.status', $pedido->id_ped) }}">
  @csrf
  @method('PATCH')
  @if ($pedido->status_ped === 'pendente')
    <button class="btn btn-primary" type="submit" name="status" value="em_separacao">Aceitar e Separar</button>
    <button class="btn btn-ghost-danger" type="submit" name="status" value="recusado">Recusar</button>
  @elseif ($pedido->status_ped === 'em_separacao')
    <button class="btn btn-primary" type="submit" name="status" value="enviado">{{ $pedido->urgencia_ped === 'critica' ? 'Marcar como Enviado' : 'Marcar Pronto' }}</button>
  @endif
  <button class="btn btn-outline" type="button" data-detalhes aria-controls="detalhes-{{ $pedido->id_ped }}">Ver Detalhes</button>
</form>

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
    @if ($pedido->requisicao)
      <div class="d-row"><span>Requisição de transfusão</span><a href="{{ $pedido->requisicao }}" target="_blank" rel="noopener noreferrer">Abrir requisição</a></div>
    @endif
  </div>
</dialog>
