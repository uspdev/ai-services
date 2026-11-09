@if (!empty($dedalus))
  @foreach ($dedalus as $index => $dedalusItem)
    @php
      $referenciaFormatada = $respostas[$index] ?? '';
      $query = $dedalusItem['query'] ?? '';
      $library = $dedalusItem['library'] ?? '';
    @endphp

    <div class="modal fade" id="modal-dedalus-{{ $index }}" tabindex="-1" role="dialog"
      aria-labelledby="modal-dedalus-label-{{ $index }}" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-info shadow">

          <div class="modal-header bg-info text-white">
            <h5 class="modal-title h6 font-weight-bold" id="modal-dedalus-label-{{ $index }}">
              <i class="fas fa-book mr-1"></i> Consulta ao Acervo Dedalus (USP)
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <div class="modal-body bg-light text-dark">
            @if ($referenciaFormatada)
              <div class="mb-3 p-2 bg-white border rounded small">
                <strong class="text-secondary d-block mb-1">Referência avaliada:</strong>
                <span>{{ $referenciaFormatada }}</span>
              </div>
            @endif

            <form class="dedalus-search-form mb-3" data-index="{{ $index }}"
              action="{{ route('bibliografia.dedalus') }}" method="GET">
              <div class="form-row align-items-end">
                <div class="col-sm-8 mb-2 mb-sm-0">
                  <label class="small font-weight-bold text-secondary mb-1"
                    for="dedalus-query-{{ $index }}">Termo de busca (Título / Autor)</label>
                  <input type="text" id="dedalus-query-{{ $index }}" name="search"
                    class="form-control form-control-sm dedalus-query-input" value="{{ $query }}"
                    placeholder="Digite título ou autor para pesquisar..." required>
                </div>
                <div class="col-sm-2 mb-2 mb-sm-0">
                  <label class="small font-weight-bold text-secondary mb-1"
                    for="dedalus-library-{{ $index }}">Unidade</label>
                  <input type="text" id="dedalus-library-{{ $index }}" name="library"
                    class="form-control form-control-sm dedalus-library-input" value="{{ $library }}"
                    placeholder="Ex.: EESC">
                </div>
                <div class="col-sm-2">
                  <button type="submit" class="btn btn-sm btn-info btn-block dedalus-submit-btn">
                    <i class="fas fa-search mr-1"></i> Buscar
                  </button>
                </div>
              </div>
            </form>

            <div class="dedalus-results-container" data-index="{{ $index }}">
              <div class="text-center py-3 text-muted small">
                <i class="fas fa-spinner fa-spin mr-1"></i> Consultando acervo Dedalus...
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light border-top-0">
            <button type="button" class="btn btn-sm btn-outline-info" data-dismiss="modal">
              Fechar
            </button>
          </div>

        </div>
      </div>
    </div>
  @endforeach

  @push('scripts')
    <script>
      /**
       * Gerencia a busca assíncrona via AJAX no acervo Dedalus após a renderização da resposta da IA.
       *
       * @returns {Object} API pública do componente Dedalus.
       */
      window.UIDedalus = (() => {
        /**
         * Escapa caracteres especiais HTML para evitar injeção ao renderizar resultados via JS.
         *
         * @param {string} texto Valor textual bruto.
         * @returns {string} Texto seguro para inserção em HTML.
         */
        const escaparHtml = (texto) => {
          const div = document.createElement('div');
          div.textContent = texto ?? '';
          return div.innerHTML;
        };

        /**
         * Constrói o HTML da lista de publicações ou mensagens de estado para o container do modal.
         *
         * @param {Object} data Dados normalizados retornados pelo endpoint `bibliografia.dedalus`.
         * @returns {string} Marcação HTML pronta para exibição.
         */
        const renderizarResultados = (data) => {
          if (data.error) {
            return `<div class="alert alert-warning mb-0 small">
              <i class="fas fa-exclamation-triangle mr-1"></i> ${escaparHtml(data.error)}
            </div>`;
          }

          const publications = Array.isArray(data.publications) ? data.publications : [];
          const library = data.library ? ` (${escaparHtml(data.library)})` : '';

          if (!publications.length) {
            return `<div class="alert alert-secondary mb-0 small">
              Nenhuma publicação encontrada no Dedalus${library} para
              <strong>"${escaparHtml(data.query || '')}"</strong>. Você pode ajustar o termo de busca acima e tentar novamente.
            </div>`;
          }

          const itensHtml = publications.map((pub) => {
            const titulo = escaparHtml(pub.title || 'Sem título');
            const dataPub = pub.publicationDate
              ? `<span class="badge badge-light border ml-2">${escaparHtml(pub.publicationDate)}</span>`
              : '';
            const autores = Array.isArray(pub.authors) && pub.authors.length
              ? `<div class="small text-dark mb-1"><strong>Autor(es):</strong> ${escaparHtml(pub.authors.join('; '))}</div>`
              : '';

            const detalhes = [];
            if (pub.editor) detalhes.push(`<span class="mr-2"><strong>Editora/Local:</strong> ${escaparHtml(pub.editor)}</span>`);
            if (pub.format) detalhes.push(`<span class="mr-2"><strong>Formato:</strong> ${escaparHtml(pub.format)}</span>`);
            if (pub.mediaType) detalhes.push(`<span class="mr-2"><strong>Tipo:</strong> ${escaparHtml(pub.mediaType)}</span>`);
            if (pub.idiom) detalhes.push(`<span class="mr-2"><strong>Idioma:</strong> ${escaparHtml(pub.idiom)}</span>`);
            if (pub.sysNo) detalhes.push(`<span><strong>SysNo:</strong> ${escaparHtml(pub.sysNo)}</span>`);

            const assuntos = Array.isArray(pub.subjects) && pub.subjects.length
              ? `<div class="mt-1">${pub.subjects.map(s => `<span class="badge badge-light border text-secondary mr-1">${escaparHtml(s)}</span>`).join('')}</div>`
              : '';

            return `<div class="list-group-item list-group-item-action flex-column align-items-start p-2">
              <div class="d-flex w-100 justify-content-between align-items-baseline">
                <h6 class="mb-1 font-weight-bold text-primary">${titulo}</h6>
                ${dataPub}
              </div>
              ${autores}
              <div class="small text-muted">${detalhes.join('')}</div>
              ${assuntos}
            </div>`;
          }).join('');

          const unidadeTexto = data.library ? ` na biblioteca ${escaparHtml(data.library)}` : '';

          return `<div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
            <span>Exibindo <strong>${publications.length}</strong> de <strong>${Number(data.total || publications.length)}</strong> resultado(s) encontrado(s)${unidadeTexto}.</span>
          </div>
          <div class="list-group dedalus-publications-list">${itensHtml}</div>`;
        };

        /**
         * Atualiza o badge resumido na coluna de explicações conforme o retorno da busca.
         *
         * @param {string} index Índice da referência bibliográfica.
         * @param {Object} data Resultado retornado pelo backend.
         * @returns {void}
         */
        const atualizarBadge = (index, data) => {
          const botao = document.querySelector(`.dedalus-trigger[data-index="${index}"]`);
          if (!botao) return;

          const total = Number(data.total || 0);
          const temErro = Boolean(data.error);
          const libTexto = data.library ? ` (${data.library})` : '';

          botao.classList.remove('badge-info', 'badge-secondary', 'badge-warning');
          botao.classList.add(temErro ? 'badge-warning' : (total > 0 ? 'badge-info' : 'badge-secondary'));
          botao.innerHTML = `<i class="fas fa-book mr-1"></i>Dedalus${escaparHtml(libTexto)}: <span class="dedalus-count" data-index="${escaparHtml(index)}">${temErro ? 'erro' : total}</span>`;
        };

        /**
         * Executa a consulta AJAX ao endpoint `bibliografia.dedalus` para o formulário informado.
         *
         * @param {HTMLFormElement} form Formulário de busca do modal correspondente à referência.
         * @returns {Promise<void>} Promessa concluída após atualizar o modal e o badge.
         */
        const executarBusca = async (form) => {
          const index = form.dataset.index;
          const container = document.querySelector(`.dedalus-results-container[data-index="${index}"]`);
          const botaoSubmit = form.querySelector('.dedalus-submit-btn');
          const queryInput = form.querySelector('.dedalus-query-input');
          const libraryInput = form.querySelector('.dedalus-library-input');

          if (!container || !queryInput) return;

          const termo = queryInput.value.trim();
          const library = libraryInput ? libraryInput.value.trim() : '';

          if (!termo) {
            const vazio = { query: '', library, total: 0, publications: [], error: null };
            container.innerHTML = renderizarResultados(vazio);
            atualizarBadge(index, vazio);
            return;
          }

          const params = new URLSearchParams({ search: termo, library });

          if (botaoSubmit) botaoSubmit.disabled = true;
          container.innerHTML = '<div class="text-center py-3 text-muted small"><i class="fas fa-spinner fa-spin mr-1"></i> Consultando acervo Dedalus...</div>';

          try {
            const response = await fetch(`${form.action}?${params.toString()}`, {
              headers: { 'Accept': 'application/json' },
            });
            const payload = await response.json();
            const data = payload.data || { library, error: payload.message || 'Falha ao consultar o Dedalus.' };

            container.innerHTML = renderizarResultados(data);
            atualizarBadge(index, data);
          } catch (error) {
            const falha = { library, error: 'Não foi possível consultar o acervo Dedalus no momento.' };
            container.innerHTML = renderizarResultados(falha);
            atualizarBadge(index, falha);
          } finally {
            if (botaoSubmit) botaoSubmit.disabled = false;
          }
        };

        /**
         * Registra os eventos de submissão e dispara as buscas iniciais em segundo plano via AJAX.
         *
         * @returns {void}
         */
        const iniciar = () => {
          document.querySelectorAll('.dedalus-search-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
              event.preventDefault();
              executarBusca(form);
            });

            // Dispara a busca assíncrona após carregar a resposta da IA sem bloquear a renderização.
            executarBusca(form);
          });
        };

        return { iniciar, executarBusca };
      })();

      document.addEventListener('DOMContentLoaded', () => {
        window.UIDedalus.iniciar();
      });
    </script>
  @endpush
@endif
