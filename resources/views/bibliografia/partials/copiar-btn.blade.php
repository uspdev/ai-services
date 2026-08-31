<button type="button" id="copiar-resposta" class="btn btn-sm btn-outline-secondary py-0" title="Copiar referências corrigidas">
  <i class="fas fa-copy"></i>
  Copiar
</button>

@pushOnce('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const button = document.getElementById('copiar-resposta');

      if (!button) return;

      button.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(
            window.bibliografia.obterTextoResposta()
          );

          const original = button.innerHTML;

          button.innerHTML = '<i class="fas fa-check"></i> Copiado';
          button.classList.replace('btn-outline-secondary', 'btn-success');

          setTimeout(() => {
            button.innerHTML = original;
            button.classList.replace('btn-success', 'btn-outline-secondary');
          }, 1500);

        } catch (error) {
          console.error('Erro ao copiar:', error);
        }
      });
    });
  </script>
@endpushOnce
