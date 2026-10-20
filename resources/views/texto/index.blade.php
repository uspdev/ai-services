@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-header card-header-sticky">
      <div class="h4 mb-0">
        Assistente de elaboração de textos
        @include('texto.partials.ajuda', ['button' => 1])
      </div>
      @include('texto.partials.ajuda')
    </div>

    <div class="card-body">

      @include('texto.partials.form')

      @if (!empty($textoElaborado))
        <hr>
        <h5 class="mb-3" id="resultados">
          Resultados
          @include('bibliografia.partials.estatisticas')
        </h5>

        @if (!empty($assuntoSugerido) || !empty($estiloAplicado))
          <div class="alert alert-light border mb-3 py-2">
            @if (!empty($estiloAplicado))
              <div class="{{ !empty($assuntoSugerido) ? 'mb-1' : '' }}">
                <strong class="text-secondary mr-1">Estilo aplicado:</strong>
                <span id="estilo-aplicado">{{ $estiloAplicado }}</span>
              </div>
            @endif
            @if (!empty($assuntoSugerido))
              <div>
                <strong class="text-secondary mr-1">Assunto / Título sugerido:</strong>
                <span id="assunto-sugerido">{{ $assuntoSugerido }}</span>
              </div>
            @endif
          </div>
        @endif

        <div class="row">
          <div class="col-md-4">
            <label class="ml-2">Alterações</label>
            <div id="texto-diff" class="form-control diff-container"></div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <div class="d-flex align-items-center gap-2">
                <label class="ml-2" for="resposta">Texto elaborado
                  <span class="badge badge-outline-info usptheme-contador-paragrafos"></span>
                </label>
                <div class="ml-auto pb-2">@include('texto.partials.copiar-btn')</div>
              </div>

              {{-- Mantém quebras de linha e escapa HTML gerado pelo modelo para edição segura. --}}
              <div id="resposta" class="form-control resposta-editavel usptheme-contador" contenteditable="true">{!! nl2br(e($textoElaborado)) !!}</div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="ml-2" for="explicacao">Melhorias aplicadas e observações</label>
            <div id="explicacao" class="form-control explicacao">
              @if (!empty($observacoes))
                <div class="alert alert-warning py-2 px-2 mb-2 small">
                  <strong><i class="fas fa-exclamation-circle mr-1"></i>Atenção / Preenchimento:</strong>
                  <div>{{ $observacoes }}</div>
                </div>
              @endif

              @if (!empty($melhorias))
                <ul class="pl-3 mb-0">
                  @foreach ($melhorias as $index => $melhoria)
                    <li class="explicacao-item mb-1" data-index="{{ $index }}">
                      {{ $melhoria }}
                    </li>
                  @endforeach
                </ul>
              @endif
            </div>
          </div>
        </div>
      @endif

    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.height-sync')
  @include('partials.diff-viewer')
  @include('partials.cross-highlight')

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const original = document.getElementById('rascunho');
      const resposta = document.getElementById('resposta');
      const diff = document.getElementById('texto-diff');
      const explicacao = document.getElementById('explicacao');

      if (!original || !resposta || !diff) return;

      // 1. Sincroniza alturas entre os três painéis de resultados
      UIHeightSync.iniciar([diff, resposta, explicacao]);

      // 2. Atualiza o comparador visual de alterações sempre que o texto elaborado for editado
      const executarDiff = () => {
        UIDiffViewer.atualizar(original, diff, resposta);
      };

      resposta.addEventListener('input', executarDiff);
      executarDiff();
    });
  </script>
@endpush

