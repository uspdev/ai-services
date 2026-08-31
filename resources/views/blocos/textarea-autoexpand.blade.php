{{--
  Autoexpansão de <textarea>

  Expande automaticamente os elementos <textarea> com a classe `autoexpand`
  para acomodar todo o conteúdo, evitando a rolagem vertical.

  Uso:
    1. Incluir este bloco uma única vez no layout:
       @include('blocos.textarea-autoexpand')

    2. Adicionar a classe `autoexpand` ao <textarea>:
       <textarea class="form-control autoexpand"></textarea>

    3. Opcionalmente, utilizar o atributo `rows` para definir a altura mínima:
       <textarea class="form-control autoexpand" rows="4"></textarea>

  Comportamento:
    - A altura mínima é determinada pelo atributo `rows` (padrão: 2 linhas).
    - A altura é ajustada automaticamente ao carregar a página.
    - O <textarea> é redimensionado enquanto o usuário digita.
    - O redimensionamento é recalculado ao redimensionar a janela.
    - Em abas do Bootstrap 4, o ajuste é realizado quando a aba é exibida.
    - A rolagem vertical é desabilitada, pois o objetivo é exibir todo o conteúdo.

  @author Masakik, 08/05/2024
  @author Masakik, 21/08/2026 - removida a rolagem vertical.
--}}
@once
  @section('javascripts_bottom')
    @parent
    <script>
      function autoExpand(el) {
        const lineHeight = parseFloat(getComputedStyle(el).lineHeight) || 20;
        const minRows = parseInt(el.getAttribute('rows'), 10) || 2;
        const minHeight = minRows * lineHeight;

        el.style.height = 'auto';
        el.style.height = Math.max(el.scrollHeight, minHeight) + 'px';
        el.style.overflowY = 'hidden';
      }

      function autoExpandAll(container = document) {
        container.querySelectorAll('textarea.autoexpand').forEach(autoExpand);
      }

      document.addEventListener('DOMContentLoaded', () => {
        // Ajusta os textareas existentes
        autoExpandAll();

        // Ajusta enquanto o usuário digita
        document.querySelectorAll('textarea.autoexpand').forEach(el => {
          el.addEventListener('input', () => autoExpand(el));
        });

        // Ajusta ao abrir uma aba do Bootstrap 4
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
          const target = document.querySelector(e.target.getAttribute('href'));

          if (target) {
            autoExpandAll(target);
          }
        });

        // Recalcula ao redimensionar a janela
        window.addEventListener('resize', () => autoExpandAll());
      });
    </script>
  @endsection

  @push('styles')
    <style>
      textarea.autoexpand {
        overflow-y: hidden;
      }
    </style>
  @endpush
@endonce
