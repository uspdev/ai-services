<button type="button" id="btn-copiar-resposta" class="btn btn-sm btn-outline-secondary py-0">
  <i class="fas fa-copy"></i> <span>Copiar texto</span>
</button>

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const btnCopiar = document.getElementById('btn-copiar-resposta');
      const respostaEl = document.getElementById('resposta');

      if (btnCopiar && respostaEl) {
        btnCopiar.addEventListener('click', async () => {
          // Extrai o texto limpo mantendo as quebras de linha
          const textoParaCopiar = respostaEl.innerText.trim();

          try {
            await navigator.clipboard.writeText(textoParaCopiar);

            // Feedback visual no botão
            const labelOriginal = btnCopiar.innerHTML;
            btnCopiar.innerHTML = '<i class="fas fa-check text-success"></i> <span>Copiado!</span>';
            btnCopiar.classList.add('btn-outline-success');

            setTimeout(() => {
              btnCopiar.innerHTML = labelOriginal;
              btnCopiar.classList.remove('btn-outline-success');
            }, 2000);
          } catch (err) {
            console.error('Falha ao copiar o texto:', err);
          }
        });
      }
    });
  </script>
@endpush
