@if ($button ?? false)
  <button type="button" id="btn-ajuda" class="btn btn-sm btn-outline-info py-0" data-toggle="collapse"
    data-target="#ajuda-texto">
    Como utilizar
  </button>
@else
  <div id="ajuda-texto" class="alert alert-info collapse my-1">

    <button type="button" class="close" id="btn-fechar-ajuda" aria-label="Fechar">
      <span aria-hidden="true">&times;</span>
    </button>

    <h5 class="pb-0">Como utilizar</h5>
    <hr />

    <p class="mb-2">
      Informe no campo <strong>Rascunho ou tópicos</strong> as ideias principais, anotações ou uma versão preliminar
      do texto que deseja elaborar, selecione o <strong>Estilo / Formato</strong> desejado e clique em
      <strong>Processar</strong>. Opcionalmente, preencha o campo de contexto com o destinatário ou objetivo.
    </p>

    <p class="mb-1">O resultado apresentará:</p>

    <ul class="mb-2">
      <li>
        <strong>Alterações:</strong> comparação visual entre o rascunho original e o texto elaborado. As exclusões
        aparecem <span class="del">em vermelho</span> e as inclusões <span class="ins">em verde</span>.
      </li>
      <li>
        <strong>Texto elaborado:</strong> versão estruturada e revisada pronta para uso, acompanhada de uma sugestão
        de assunto/título. O texto pode ser editado diretamente na tela antes de copiar.
      </li>
      <li>
        <strong>Melhorias e observações:</strong> resumo dos aprimoramentos aplicados pela IA e alerta sobre
        eventuais informações que precisem ser preenchidas manualmente (como datas ou números de documento).
      </li>
    </ul>

    <p class="mb-0">
      <strong>Atenção:</strong> confira sempre os dados factuais e preencha eventuais campos indicados entre colchetes
      antes de enviar ou publicar o documento.
    </p>

    <div class="text-right mt-3">
      <button type="button" class="btn btn-sm btn-info text-white font-weight-bold px-3"
        onclick="$('#btn-fechar-ajuda').click()">
        Entendi, fechar ajuda
      </button>
    </div>
  </div>
@endif

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const ajuda = $('#ajuda-texto');
      const abrir = document.getElementById('btn-ajuda');
      const fechar = document.getElementById('btn-fechar-ajuda');

      if (!ajuda.length) return;

      const storageKey = 'ai-texto-ajuda-fechada';

      /**
       * Atualiza o rótulo do botão de ajuda conforme o estado do painel retrátil.
       *
       * @param {boolean} aberta Indica se a seção de ajuda está visível.
       * @returns {void}
       */
      const atualizarBotao = (aberta) => {
        if (abrir) {
          abrir.textContent = aberta ? 'Ocultar ajuda' : 'Como utilizar';
        }
      };

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

