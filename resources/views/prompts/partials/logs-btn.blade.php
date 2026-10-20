{{--
  Botão que aciona o modal de histórico de alterações (logs) de um prompt.
  Parâmetros esperados:
  - $slug (string): identificador do serviço.
  - $logs (array): lista de registros de log do prompt.
  - $btnClass (string, opcional): classes CSS adicionais do botão.
--}}
<button type="button" class="btn btn-sm {{ $btnClass ?? 'btn-outline-secondary' }}" data-toggle="modal"
  data-target="#modal-logs-{{ $slug }}" title="Visualizar histórico de alterações deste prompt">
  <i class="fas fa-history mr-1"></i> Logs ({{ count($logs ?? []) }})
</button>

