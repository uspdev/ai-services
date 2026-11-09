@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-header card-header-sticky d-flex flex-wrap align-items-center justify-content-between">
      <div class="h4 mb-0">
        Editar Prompt: {{ $prompt->nome }}
        <span class="badge badge-light border text-monospace ml-1">{{ $prompt->slug }} · v{{ $prompt->version }}</span>
        @include('prompts.partials.status-badge', ['badgeClassExtra' => 'ml-1'])
      </div>

      <div class="mt-2 mt-sm-0">
        @include('prompts.partials.salvar-btn', [
            'btnClass' => 'btn-sm mr-1',
        ])
        @include('prompts.partials.restaurar-padrao-btn')
        @include('prompts.partials.logs-btn', [
            'slug' => $prompt->slug,
            'btnClass' => 'btn-outline-info mr-1',
        ])
        <a href="{{ route('prompts.index') }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left mr-1"></i> Voltar para listagem
        </a>
      </div>
    </div>

    <div class="card-body">
      <form id="form-editar-prompt" method="POST" action="{{ route('prompts.update', $prompt) }}">
        @csrf
        @method('PUT')

        <input type="hidden" name="save_mode" id="input-save-mode" value="update">
        <input type="hidden" name="activate_now" id="input-activate-now"
          value="{{ $prompt->status === \App\Enums\PromptStatus::Active ? '1' : '0' }}">

        <div class="form-row">
          <div class="form-group col-md-5">
            <label for="nome" class="font-weight-bold">Nome do serviço / prompt</label>
            <input type="text" id="nome" name="nome" class="form-control" maxlength="255"
              value="{{ old('nome', $prompt->nome) }}" required>
          </div>

          <div class="form-group col-md-7">
            <label for="descricao" class="font-weight-bold">Descrição resumida</label>
            <input type="text" id="descricao" name="descricao" class="form-control" maxlength="1000"
              value="{{ old('descricao', $prompt->descricao) }}">
          </div>
        </div>

        <div class="form-group">
          <label for="changelog" class="font-weight-bold">
            Changelog / Notas da versão
            <small class="text-muted font-weight-normal">(descreva o motivo do ajuste para histórico)</small>
          </label>
          <input type="text" id="changelog" name="changelog" class="form-control" maxlength="1000"
            value="{{ old('changelog', $prompt->changelog) }}"
            placeholder="Ex.: Ajusta regra de citação de capítulos de livro...">
        </div>

        @if ($metadataKey !== null || !empty($placeholders))
          <div class="p-3 mb-3 rounded" style="background-color: #fff9db; border: 1px solid #f5e08e;">
            <div class="form-row">
              @if ($metadataKey !== null)
                <div class="form-group mb-md-0 {{ !empty($placeholders) ? 'col-md-6' : 'col-md-12' }}">
                  <label for="metadata_items" class="font-weight-bold">
                    <i class="fas fa-list-ul text-secondary mr-1"></i>
                    Opções disponíveis para seleção no formulário (<code>metadata.{{ $metadataKey }}</code>)
                    <small class="text-muted font-weight-normal">— uma por linha</small>
                  </label>
                  <textarea id="metadata_items" name="metadata_items" class="form-control text-monospace bg-white" rows="4" required>{{ old('metadata_items', $currentMetadataItems) }}</textarea>
                </div>
              @endif

              @if (!empty($placeholders))
                <div class="form-group mb-0 {{ $metadataKey !== null ? 'col-md-6' : 'col-md-12' }} d-flex flex-column">
                  <label class="font-weight-bold">
                    <i class="fas fa-code text-info mr-1"></i> Variáveis dinâmicas suportadas neste prompt
                    <small class="text-muted font-weight-normal">— clique para inserir no cursor</small>
                  </label>
                  <div class="alert alert-info py-2 px-3 mb-0 small flex-grow-1 d-flex flex-column justify-content-center">
                    @foreach ($placeholders as $placeholder => $desc)
                      <div class="{{ !$loop->last ? 'mb-2' : '' }}">
                        @include('prompts.partials.inserir-placeholder-btn')
                      </div>
                    @endforeach
                  </div>
                </div>
              @endif
            </div>
          </div>
        @endif

        {{-- Textarea oculto com a versão ativa atual para alimentar o UIDiffViewer --}}
        <textarea id="prompt-ativo-base" class="d-none" aria-hidden="true">{{ $activePrompt->system_prompt }}</textarea>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="system_prompt" class="font-weight-bold">
                Instruções de Sistema (<code>system_prompt</code>)
              </label>
              <textarea id="system_prompt" name="system_prompt" class="form-control text-monospace" rows="18"
                style="height: 420px;" required>{{ old('system_prompt', $prompt->system_prompt) }}</textarea>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">
                Comparador Diff (em relação à versão ativa v{{ $activePrompt->version }})
              </label>
              <div id="prompt-diff" class="diff-container text-monospace" style="height: 420px; overflow-y: auto;"></div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end mt-3 pt-3 border-top">
          @include('prompts.partials.salvar-btn', [
              'btnClass' => 'mr-2',
          ])
          <a href="{{ route('prompts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>

      </form>
    </div>
  </div>

  @include('prompts.partials.logs-modal', [
      'slug' => $prompt->slug,
      'promptNome' => $prompt->nome,
  ])
@endsection

@push('scripts')
  @include('partials.diff-viewer')

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const baseAtiva = document.getElementById('prompt-ativo-base');
      const editorPrompt = document.getElementById('system_prompt');
      const containerDiff = document.getElementById('prompt-diff');

      if (!baseAtiva || !editorPrompt || !containerDiff) return;

      /**
       * Recalcula o comparador visual entre a versão ativa e o texto em edição.
       *
       * @returns {void}
       */
      const atualizarDiff = () => {
        UIDiffViewer.atualizar(baseAtiva, containerDiff, editorPrompt);
      };

      editorPrompt.addEventListener('input', atualizarDiff);
      atualizarDiff();
    });
  </script>
@endpush
