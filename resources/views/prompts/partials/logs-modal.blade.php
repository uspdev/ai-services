{{--
  Modal de histórico de alterações (auditoria) de um prompt por `slug`.
  Parâmetros esperados:
  - $slug (string): identificador do serviço.
  - $promptNome (string): nome de exibição do prompt.
  - $logs (array): lista de registros de log ordenada do mais recente ao mais antigo.
--}}
<div class="modal fade" id="modal-logs-{{ $slug }}" tabindex="-1" role="dialog"
  aria-labelledby="modal-logs-label-{{ $slug }}" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 92vw;" role="document">
    <div class="modal-content shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title h6 font-weight-bold text-dark" id="modal-logs-label-{{ $slug }}">
          <i class="fas fa-history text-info mr-1"></i>
          Histórico de Alterações — {{ $promptNome }}
          <span class="badge badge-light border text-monospace ml-1">{{ $slug }}</span>
          <small class="text-muted font-weight-normal ml-1">(últimos {{ \App\Models\AiPrompt::logRetentionMonths() }} meses)</small>
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body p-0">
        @if (empty($logs))
          <div class="p-4 text-center text-muted">
            <i class="fas fa-info-circle mr-1"></i> Nenhuma alteração registrada até o momento para este prompt.
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead class="thead-light">
                <tr>
                  <th style="width: 155px;">Quando</th>
                  <th style="width: 180px;">Quem</th>
                  <th style="width: 80px;">Versão</th>
                  <th style="width: 150px;">Operação</th>
                  <th>Detalhes</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($logs as $log)
                  @php
                    $actionBadge = match ($log['action'] ?? '') {
                        'new_version' => 'badge-primary',
                        'activate' => 'badge-success',
                        default => 'badge-info',
                    };
                  @endphp
                  <tr>
                    <td class="align-middle small text-nowrap text-muted">
                      {{ $log['formatted_date'] ?? ($log['timestamp'] ?? '—') }}
                    </td>
                    <td class="align-middle small text-dark">
                      {{ !empty($log['user_codpes']) ? $log['user_codpes'] . ' | ' . ($log['user_name'] ?? 'Sistema') : ($log['user_name'] ?? 'Sistema') }}
                    </td>
                    <td class="align-middle font-weight-bold">
                      v{{ $log['version'] ?? '?' }}
                    </td>
                    <td class="align-middle">
                      <span class="badge {{ $actionBadge }}">
                        {{ $log['action_label'] ?? ($log['action'] ?? 'Alteração') }}
                      </span>
                    </td>
                    <td class="align-middle small text-dark">
                      {{ $log['details'] ?? '—' }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      <div class="modal-footer bg-light py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

