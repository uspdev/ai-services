{{--
  Botão de salvar alterações do prompt e modal de escolha entre atualizar a versão atual ou criar nova versão.
  Parâmetros esperados:
  - $prompt (\App\Models\AiPrompt): versão sendo editada.
  - $nextVersion (int): próximo número de versão disponível para o slug.
  - $btnClass (string, opcional): classes CSS adicionais do botão.
--}}
<button type="button" class="btn btn-primary {{ $btnClass ?? '' }} btn-abrir-modal-salvar">
  <i class="fas fa-save mr-1"></i> Salvar alterações
</button>

@once
  <div class="modal fade" id="modal-confirmar-salvamento" tabindex="-1" role="dialog"
    aria-labelledby="modal-salvar-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-primary shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title h6 font-weight-bold" id="modal-salvar-label">
            <i class="fas fa-code-branch mr-1"></i> Como deseja salvar este prompt?
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <p class="mb-3">
            Escolha se deseja sobrescrever a versão atual (<strong>v{{ $prompt->version }}</strong>) ou criar uma
            nova versão (<strong>v{{ $nextVersion }}</strong>) preservando o histórico anterior para eventual rollback:
          </p>

          <div class="custom-control custom-radio mb-2">
            <input type="radio" id="radio-save-new" name="modal_save_choice" class="custom-control-input"
              value="new_version" checked>
            <label class="custom-control-label font-weight-bold" for="radio-save-new">
              Criar nova versão (v{{ $nextVersion }})
              <small class="d-block text-muted font-weight-normal">
                Recomendado: mantém a versão v{{ $prompt->version }} intacta no histórico.
              </small>
            </label>
          </div>

          <div class="custom-control custom-radio mb-3">
            <input type="radio" id="radio-save-update" name="modal_save_choice" class="custom-control-input"
              value="update">
            <label class="custom-control-label font-weight-bold" for="radio-save-update">
              Atualizar a própria versão v{{ $prompt->version }}
              <small class="d-block text-muted font-weight-normal">
                Sobrescreve o texto e os metadados diretamente na versão v{{ $prompt->version }}.
              </small>
            </label>
          </div>

          <hr>

          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="check-activate-now"
              {{ $prompt->status === \App\Enums\PromptStatus::Active ? 'checked' : '' }}>
            <label class="custom-control-label font-weight-bold text-success" for="check-activate-now">
              Tornar esta versão ativa em produção imediatamente
            </label>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Voltar</button>
          <button type="button" id="btn-confirmar-submit" class="btn btn-sm btn-primary font-weight-bold">
            <i class="fas fa-check mr-1"></i> Confirmar e salvar
          </button>
        </div>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('form-editar-prompt');
        const modalSalvar = document.getElementById('modal-confirmar-salvamento');
        const botoesAbrirModal = document.querySelectorAll('.btn-abrir-modal-salvar');
        const btnConfirmarSubmit = document.getElementById('btn-confirmar-submit');
        const inputSaveMode = document.getElementById('input-save-mode');
        const inputActivateNow = document.getElementById('input-activate-now');
        const checkActivateNow = document.getElementById('check-activate-now');

        if (!form || !modalSalvar) return;

        // Move o modal para dentro do <form> (no .card-body), fora do .card-header-sticky (z-index: 100),
        // permitindo que o helper btn-spinner do tema encontre btn.closest('form') e submeta o formulário.
        form.appendChild(modalSalvar);

        const sincronizarOpcoesModal = () => {
          const modoSelecionado = document.querySelector('input[name="modal_save_choice"]:checked')?.value || 'update';
          if (inputSaveMode) inputSaveMode.value = modoSelecionado;
          if (inputActivateNow) inputActivateNow.value = checkActivateNow?.checked ? '1' : '0';
        };

        // Abre o modal de escolha entre atualizar a versão atual ou criar nova versão.
        botoesAbrirModal.forEach((botao) => {
          botao.addEventListener('click', () => {
            if (!form.reportValidity()) return;
            sincronizarOpcoesModal();
            $('#modal-confirmar-salvamento').modal('show');
          });
        });

        modalSalvar.querySelectorAll('input').forEach((input) => {
          input.addEventListener('change', sincronizarOpcoesModal);
        });

        // Sincroniza os campos ocultos e aciona spinnerRun() do tema para bloquear cliques duplicados e submeter.
        btnConfirmarSubmit?.addEventListener('click', () => {
          sincronizarOpcoesModal();
          botoesAbrirModal.forEach((botao) => {
            botao.classList.add('disabled');
            botao.disabled = true;
          });
          if (typeof spinnerRun === 'function') {
            spinnerRun($(btnConfirmarSubmit));
          } else {
            btnConfirmarSubmit.disabled = true;
            form.submit();
          }
        });
      });
    </script>
  @endpush
@endonce

