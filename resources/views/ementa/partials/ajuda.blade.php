@if ($button ?? null)
  <button type="button" id="btn-ajuda" class="btn btn-sm btn-outline-info py-0" data-toggle="collapse"
    data-target="#ajuda-bibliografia" aria-expanded="false" aria-controls="ajuda-bibliografia">
    Como utilizar
  </button>
@else
  <div id="ajuda-bibliografia" class="alert alert-info collapse my-1">

    <button type="button" class="close" id="btn-fechar-ajuda" aria-label="Fechar">
      <span aria-hidden="true">&times;</span>
    </button>

    <h5 class="mb-0">Como utilizar</h5>
    <hr />

    <p class="mb-1">
      Para uma análise precisa, o texto de entrada deve conter obrigatoriamente as seções:
      <strong>Ementa</strong>, <strong>Objetivos</strong> e <strong>Conteúdo Programático</strong>.
    </p>
    <p class="mb-1">
      A versão do texto em <strong>inglês é opcional</strong>.
      Caso seja fornecida, ela deve vir na sequência do texto em português. A ferramenta realizará o
      diagnóstico e alinhamento terminológico bilíngue automaticamente.
    </p>

    <p class="mb-1 font-weight-bold">Resultados</p>
    <ul class="mb-2">
      <li class="mb-1"><strong>Explicações e qualidade da entrada:</strong> Exibe o score global e o diagnóstico
        técnico do texto por idioma (PT e EN).</li>
      <li class="mb-1"><strong>Alterações (Diff):</strong> Mostra a comparação visual das correções (remoções em
        <span class="del">vermelho</span> e adições em <span class="ins">verde</span>).
      </li>
      <li class="mb-1"><strong>Texto corrigido:</strong> Apresenta a versão final revisada. Este campo é
        <strong>editável</strong> para ajustes manuais.
      </li>
    </ul>

    <p class="mb-0">
      <strong>Atenção:</strong> a resposta da inteligência artificial deve ser utilizada como apoio à revisão.
      Verifique as referências e faça os ajustes necessários antes de utilizá-las.
    </p>

    <!-- Guia do Corretor de Inglês Unificado -->
    <div class="bg-white p-3 rounded border border-info mt-3">
      <div>
        <i class="fas fa-spell-check text-info mr-2"></i>
        <span class="">Dica: Corretor Ortográfico em Inglês</span>
      </div>
      <p class="small text-muted mb-2">
        Se o texto em inglês estiver marcado com sublinhados vermelhos de erro, adicione o idioma ao seu
        navegador:
      </p>
      <ol class="pl-3 mb-0 small text-muted">
        <li>Acesse as <strong>Configurações</strong> (no Chrome/Edge: <code
            class="bg-light px-1 border rounded text-dark">chrome://settings/languages</code>).</li>
        <li>Em <strong>Idiomas</strong>, ative a opção <strong>Verificação ortográfica</strong>.</li>
        <li>Adicione e ative a chave para o idioma <strong>Inglês</strong>.</li>
      </ol>
    </div>

    <div class="text-right mt-3">
      <button type="button" class="btn btn-sm btn-info text-white font-weight-bold px-3"
        onclick="$('#btn-fechar-ajuda').click()">
        Entendi, fechar ajuda
      </button>
    </div>
  </div>
@endif

@pushOnce('scripts')
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
          abrir.classList.toggle('btn-info', aberta);
          abrir.classList.toggle('btn-outline-info', !aberta);
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
@endpushOnce
