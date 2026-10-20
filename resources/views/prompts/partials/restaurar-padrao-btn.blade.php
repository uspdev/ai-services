{{--
  Botão que restaura o template e metadados originais definidos na classe do Agente.
  Parâmetros esperados:
  - $defaultTemplate (string): template original do system_prompt em código.
  - $metadataKey (string|null): chave de metadados editável, quando aplicável.
  - $defaultMetadataItems (string): itens padrão de metadados (um por linha).
--}}
<button type="button" id="btn-restaurar-padrao" class="btn btn-sm btn-outline-warning mr-1"
  title="Preencher o formulário com o template e opções originais definidos no código-fonte">
  <i class="fas fa-undo mr-1"></i> Restaurar padrão do código
</button>

@once
  <textarea id="prompt-padrao-codigo" class="d-none" aria-hidden="true">{{ $defaultTemplate }}</textarea>
  @if ($metadataKey !== null)
    <textarea id="metadata-padrao-codigo" class="d-none" aria-hidden="true">{{ $defaultMetadataItems }}</textarea>
  @endif

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const btnRestaurar = document.getElementById('btn-restaurar-padrao');
        const editorPrompt = document.getElementById('system_prompt');
        const padraoCodigo = document.getElementById('prompt-padrao-codigo');
        const metadataInput = document.getElementById('metadata_items');
        const metadataPadrao = document.getElementById('metadata-padrao-codigo');

        // Restaura o template e metadados originais definidos na classe do Agente e dispara a atualização do Diff.
        btnRestaurar?.addEventListener('click', () => {
          if (editorPrompt && padraoCodigo) {
            editorPrompt.value = padraoCodigo.value;
            editorPrompt.dispatchEvent(new Event('input', { bubbles: true }));
          }
          if (metadataInput && metadataPadrao) {
            metadataInput.value = metadataPadrao.value;
          }
        });
      });
    </script>
  @endpush
@endonce

