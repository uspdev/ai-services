@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-header card-header-sticky">
      <div class="h4 mb-0">
        Assistente bibliográfico
        @include('bibliografia.partials.ajuda', ['button' => 1])
      </div>
      @include('bibliografia.partials.ajuda')

    </div>

    <div class="card-body">

      @include('bibliografia.partials.form')

      @if ($respostas ?? null)
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
                @foreach ($respostas as $index => $referencia)
                  <div class="referencia" data-index="{{ $index }}">{{ $referencia }}</div>
                @endforeach
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="ml-2" for="explicacao">Explicações e qualidade da entrada</label>
            @include('bibliografia.partials.ajuda-abreviacoes')
            <div id="explicacao" class="form-control explicacao">
              @foreach ($explicacoes as $index => $texto)
                @php
                  $confianca = $confiancas[$index] ?? null;

                  $classeConfianca = match (true) {
                      $confianca >= 89 => 'badge-success',
                      $confianca >= 64 => 'badge-warning',
                      default => 'badge-danger',
                  };
                @endphp
                <div class="explicacao-item" data-index="{{ $index }}">
                  {{ $texto }}
                  <span class="badge {{ $classeConfianca }}">{{ $confianca }}%</span>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @else
        <div id="error-bibliografia" class="alert alert-info">
          @foreach ($explicacoes ?? [] as $index => $texto)
            <div class="explicacao-item" data-index="{{ $index }}">{{ $texto }}</div>
          @endforeach
        </div>
      @endif

      @if ($estatisticas ?? [])
        <hr>
        <div class="small text-muted">
          <pre>Estatísticas:
            {{ print_r($estatisticas, true) }}
            Prompt:
            {{ $prompt ?? '' }}
        </pre>
        </div>
      @endif

    </div>
  @endsection

  @push('scripts')
    <script>
      window.bibliografia = {
        obterTextoResposta() {
          const resposta = document.getElementById('resposta');
          return resposta?.innerText.trim() ?? '';
        },

        escapeHtml(text) {
          return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }
      };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/diff@8.0.2/dist/diff.min.js"></script>

    <script>
      document.addEventListener('DOMContentLoaded', () => {

        const original = document.getElementById('referencias');
        const resposta = document.getElementById('resposta');
        const diff = document.getElementById('referencias-diff');
        const explicacao = document.getElementById('explicacao');

        if (!original || !resposta || !diff) return;

        const {
          obterTextoResposta,
          escapeHtml
        } = window.bibliografia;

        const ajustarAltura = () => {
          diff.style.height = 'auto';
          resposta.style.height = 'auto';

          if (explicacao) {
            explicacao.style.height = 'auto';
          }

          requestAnimationFrame(() => {
            const altura = Math.max(
              diff.scrollHeight,
              resposta.scrollHeight,
              explicacao?.scrollHeight ?? 0,
              280
            );

            diff.style.height = `${altura}px`;
            resposta.style.height = `${altura}px`;

            if (explicacao) {
              explicacao.style.height = `${altura}px`;
            }
          });
        };

        const atualizarDiff = () => {
          const changes = Diff.diffWordsWithSpace(
            original.value,
            obterTextoResposta()
          );

          diff.innerHTML = changes
            .map(change => {
              const text = escapeHtml(change.value);

              if (change.removed) {
                return `<del class="del">${text}</del>`;
              }

              if (change.added) {
                return `<ins class="ins">${text}</ins>`;
              }

              return text;
            })
            .join('');

          ajustarAltura();
        };

        const destacar = (index, ativo) => {
          resposta
            .querySelector(`.referencia[data-index="${index}"]`)
            ?.classList.toggle('destacada', ativo);

          explicacao
            ?.querySelector(`.explicacao-item[data-index="${index}"]`)
            ?.classList.toggle('destacada', ativo);
        };

        explicacao
          ?.querySelectorAll('.explicacao-item')
          .forEach(item => {
            item.addEventListener('mouseenter', () =>
              destacar(item.dataset.index, true)
            );

            item.addEventListener('mouseleave', () =>
              destacar(item.dataset.index, false)
            );
          });

        resposta
          .querySelectorAll('.referencia')
          .forEach(item => {
            item.addEventListener('mouseenter', () =>
              destacar(item.dataset.index, true)
            );

            item.addEventListener('mouseleave', () =>
              destacar(item.dataset.index, false)
            );
          });

        resposta.addEventListener('input', atualizarDiff);

        let resizeTimer;

        window.addEventListener('resize', () => {
          clearTimeout(resizeTimer);
          resizeTimer = setTimeout(ajustarAltura, 100);
        });

        atualizarDiff();
      });
    </script>
  @endpush

  @push('styles')
    <style>
      .diff-container {
        min-height: 280px;
        overflow: hidden;
        white-space: pre-wrap;
        word-break: break-word;
        line-height: 1.5;
        background-color: #e9ecef !important;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 0.375rem 0.75rem;
      }

      .resposta-editavel {
        min-height: 280px;
        overflow: hidden;
        background-color: #fff;
        line-height: 1.2;
        cursor: text;
      }

      .resposta-editavel .referencia {
        margin-bottom: 0.5rem;
        padding: 0.15rem 0.25rem;
        border-radius: 0.25rem;
        transition: background-color 0.15s ease;
      }

      .resposta-editavel .referencia:last-child {
        margin-bottom: 0;
      }

      .explicacao {
        min-height: 280px;
        overflow: hidden;
        background-color: #e9ecef !important;
        line-height: 1.2;
      }

      .explicacao-item {
        margin-bottom: 0.5rem;
        padding: 0.15rem 0.25rem;
        border-radius: 0.25rem;
        transition: background-color 0.15s ease;
      }

      .explicacao-item:last-child {
        margin-bottom: 0;
      }

      .referencia.destacada,
      .explicacao-item.destacada {
        background-color: #fff3cd;
      }

      del,
      .del {
        color: #dc3545 !important;
        text-decoration: line-through;
      }

      ins,
      .ins {
        color: #28a745 !important;
        text-decoration: none;
      }
    </style>
  @endpush
