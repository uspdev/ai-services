@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-header card-header-sticky">
      <div class="h4 mb-0">
        Revisor de ementas e conteúdo pedagógico
        @include('bibliografia.partials.ajuda', ['button' => 1])
      </div>
      @include('bibliografia.partials.ajuda')

    </div>

    <div class="card-body">

      @include('ementa.partials.form')

      @if ($resposta ?? null)
        <hr>
        <h5 class="mb-3" id="resultados">
          Resultados
          @include('bibliografia.partials.estatisticas')
        </h5>

        <div class="row">
          <div class="col-md-4">
            <label class="ml-2">Alterações</label>
            <div id="referencias-diff" class="form-control diff-container"></div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <div class="d-flex align-items-center gap-2">
                <label class="ml-2" for="resposta">Referências corrigidas
                  <span class="badge badge-outline-info usptheme-contador-paragrafos"></span>
                </label>
                <div class="ml-auto pb-2">
                  @include('bibliografia.partials.copiar-btn')
                </div>
              </div>

              <div id="resposta" class="form-control resposta-editavel usptheme-contador" contenteditable="true">
                {!! nl2br($resposta) !!}
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="ml-2" for="explicacao">Explicações e qualidade da entrada </label>
            <div id="explicacao" class="form-control explicacao">
              <div class="mb-2">
                <b>Score: {{ $score['valor'] }}</b> - {{ $score['justificativa'] }}
              </div>
              @foreach ($explicacoes as $index => $texto)
                <div class="mb-2">
                  <b>{{ $index }}</b>: {{ $texto }}
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @else
        <div id="error-bibliografia" class="alert alert-info">
          @foreach ($explicacoes ?? [] as $index => $texto)
            <div class="explicacao-item" data-index="{{ $index }}">{{ $index }}: {{ $texto }}</div>
          @endforeach
        </div>
      @endif

      {{-- @if ($estatisticas ?? [])
        <hr>
        <div class="small text-muted">
          <pre>Estatísticas:
            {{ print_r($estatisticas, true) }}
            Prompt:
            {{ $prompt ?? '' }}
        </pre>
        </div>
      @endif --}}

    </div>
  @endsection

  @push('scripts')
    @include('partials.height-sync')
    @include('partials.diff-viewer')
    @include('partials.cross-highlight')

    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const original = document.getElementById('mensagem');
        const resposta = document.getElementById('resposta');
        const diff = document.getElementById('referencias-diff');
        const explicacao = document.getElementById('explicacao');

        if (!original || !resposta || !diff) return;

        // 1. Inicia sincronização autônoma de alturas
        UIHeightSync.iniciar([diff, resposta, explicacao]);

        // 2. Inicia os destaques ao passar o mouse
        UICrossHighlight.iniciar(resposta, explicacao);

        // 3. Atualiza o Diff passando os elementos diretamente (sem window.bibliografia)
        const executarDiff = () => {
          UIDiffViewer.atualizar(original, diff, resposta);
        };

        resposta.addEventListener('input', executarDiff);

        // Execução inicial
        executarDiff();
      });
    </script>
  @endpush
