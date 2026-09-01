@if ($button ?? false)
  <button type="button" id="btn-ajuda" class="btn btn-sm btn-outline-info py-0" data-toggle="collapse"
    data-target="#ajuda-bibliografia">
    Como utilizar
  </button>
@else
  <div id="ajuda-bibliografia" class="alert alert-info collapse my-1">

    <button type="button" class="close" id="btn-fechar-ajuda" aria-label="Fechar">
      <span aria-hidden="true">&times;</span>
    </button>

    <strong>Como utilizar</strong>

    <p class="mb-2">
      Cole no campo <strong>Referências bibliográficas</strong> as referências
      que deseja revisar, preferencialmente uma referência por linha, e clique
      em <strong>Processar</strong>.
    </p>

    <p class="mb-1">O resultado apresentará:</p>

    <ul class="mb-2">
      <li>
        <strong>Alterações:</strong> comparação entre o texto original e a
        versão corrigida. As exclusões aparecem
        <span class="del">em vermelho</span> e as inclusões
        <span class="ins">em verde</span>.
      </li>
      <li>
        <strong>Referências corrigidas:</strong> versão formatada conforme as
        orientações definidas para o processamento. O texto pode ser editado
        manualmente.
      </li>
      <li>
        <strong>Explicações e qualidade da entrada:</strong> Descrição das principais alterações realizadas pela IA
        acompanhada de um percentual que indica a qualidade e integridade da referência original (valores mais baixos
        indicam que a entrada estava muito incompleta ou exigiu alterações profundas).
      </li>
    </ul>

    <p class="mb-0">
      <strong>Atenção:</strong> a resposta da inteligência artificial deve ser
      utilizada como apoio à revisão. Verifique as referências e faça os ajustes
      necessários antes de utilizá-las.
    </p>
  </div>
@endif

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const ajuda = $('#ajuda-bibliografia');
      const abrir = document.getElementById('btn-ajuda');
      const fechar = document.getElementById('btn-fechar-ajuda');

      if (!ajuda.length) return;

      const storageKey = 'ai-bibliografia-ajuda-fechada';

      const atualizarBotao = (aberta) => {
        if (abrir) {
          abrir.textContent = aberta ? 'Ocultar ajuda' : 'Como utilizar';
        }
      };

      // Estado inicial
      if (localStorage.getItem(storageKey) === 'true') {
        ajuda.collapse('hide');
        atualizarBotao(false);
      } else {
        ajuda.collapse('show');
        atualizarBotao(true);
      }

      abrir?.addEventListener('click', () => {
        ajuda.collapse('toggle');
      });

      fechar?.addEventListener('click', () => {
        ajuda.collapse('hide');
      });

      ajuda.on('shown.bs.collapse', () => {
        localStorage.setItem(storageKey, 'false');
        atualizarBotao(true);
      });

      ajuda.on('hidden.bs.collapse', () => {
        localStorage.setItem(storageKey, 'true');
        atualizarBotao(false);
      });
    });
  </script>
@endpush
