{{--
  Botão de variável dinâmica (placeholder) que insere seu valor na posição atual do cursor no editor.
  Parâmetros esperados:
  - $placeholder (string): variável a ser inserida (ex.: '{$standard}').
  - $desc (string): descrição do placeholder exibida ao lado e no tooltip.
--}}
<button type="button" class="badge badge-light border text-monospace mr-1 btn-inserir-placeholder"
  data-placeholder="{{ $placeholder }}" title="{{ $desc }}">
  {{ $placeholder }}
</button>
<span class="text-dark">{{ $desc }}</span>

@once
  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const editorPrompt = document.getElementById('system_prompt');
        if (!editorPrompt) return;

        // Controla a última posição conhecida do cursor no textarea do prompt.
        let cursorDefinido = false;
        let ultimoInicio = editorPrompt.value.length;
        let ultimoFim = editorPrompt.value.length;

        /**
         * Registra a posição atual de seleção/cursor no textarea de instruções.
         *
         * @returns {void}
         */
        const registrarCursor = () => {
          if (typeof editorPrompt.selectionStart === 'number') {
            cursorDefinido = true;
            ultimoInicio = editorPrompt.selectionStart;
            ultimoFim = editorPrompt.selectionEnd ?? editorPrompt.selectionStart;
          }
        };

        ['focus', 'click', 'keyup', 'select', 'input'].forEach((evento) => {
          editorPrompt.addEventListener(evento, registrarCursor);
        });

        // Insere placeholders dinâmicos na posição atual do cursor no editor preservando o foco e a rolagem.
        document.querySelectorAll('.btn-inserir-placeholder').forEach((botao) => {
          botao.addEventListener('mousedown', (evento) => {
            // Impede que o clique no botão retire o foco ou resete a seleção/rolagem do textarea.
            evento.preventDefault();
          });

          botao.addEventListener('click', () => {
            const placeholder = botao.dataset.placeholder || '';
            const inicio = cursorDefinido
              ? (editorPrompt.selectionStart ?? ultimoInicio)
              : editorPrompt.value.length;
            const fim = cursorDefinido
              ? (editorPrompt.selectionEnd ?? ultimoFim)
              : editorPrompt.value.length;
            const scrollAnterior = editorPrompt.scrollTop;

            editorPrompt.focus({ preventScroll: true });
            editorPrompt.setRangeText(placeholder, inicio, fim, 'end');

            if (cursorDefinido) {
              editorPrompt.scrollTop = scrollAnterior;
            } else {
              editorPrompt.scrollTop = editorPrompt.scrollHeight;
            }

            registrarCursor();
            editorPrompt.dispatchEvent(new Event('input', { bubbles: true }));
          });
        });
      });
    </script>
  @endpush
@endonce

