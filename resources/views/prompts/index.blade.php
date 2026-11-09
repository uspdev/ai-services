@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-header card-header-sticky">
      <div class="h4 mb-0">
        <i class="fas fa-sliders-h mr-1 text-info"></i> Gerenciamento de Prompts de IA
      </div>
    </div>

    <div class="card-body">
      <p class="text-muted mb-4">
        Gerencie as instruções de sistema (<em>system prompts</em>), listas de opções e a versão ativa em produção de cada serviço de IA.
        Apenas uma versão por serviço permanece <strong>Ativa (Produção)</strong> por vez.
      </p>

      @foreach ($promptsBySlug as $slug => $versions)
        @php
          /** @var \App\Models\AiPrompt|null $activeVersion */
          $activeVersion = $versions->firstWhere('status', \App\Enums\PromptStatus::Active) ?? $versions->first();
        @endphp

        <div class="card mb-4 border shadow-sm">
          <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between">
            <div>
              <h5 class="mb-0 font-weight-bold text-dark">
                {{ $activeVersion?->nome ?? ucfirst($slug) }}
                <small class="badge badge-light border text-monospace ml-1">{{ $slug }}</small>
              </h5>
              @if ($activeVersion?->descricao)
                <small class="text-muted">{{ $activeVersion->descricao }}</small>
              @endif
            </div>

            <div>
              @include('prompts.partials.logs-btn', [
                  'logs' => $logsBySlug[$slug] ?? [],
                  'btnClass' => 'btn-xs btn-outline-secondary',
              ])
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="thead-light">
                <tr>
                  <th style="width: 90px;">Versão</th>
                  <th style="width: 170px;">Status</th>
                  <th>Notas da versão</th>
                  <th style="width: 170px;">Publicada em</th>
                  <th style="width: 170px;">Atualizada em</th>
                  <th class="text-right" style="width: 200px;">Ações</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($versions as $prompt)
                  <tr class="{{ $prompt->status === \App\Enums\PromptStatus::Active ? 'table-success' : '' }}">
                    <td class="align-middle font-weight-bold">v{{ $prompt->version }}</td>
                    <td class="align-middle">
                      @include('prompts.partials.status-badge')
                    </td>
                    <td class="align-middle">
                      @if ($prompt->changelog)
                        <span class="text-dark">{{ $prompt->changelog }}</span>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td class="align-middle small text-muted">
                      {{ $prompt->published_at ? $prompt->published_at->format('d/m/Y H:i') : '—' }}
                    </td>
                    <td class="align-middle small text-muted">
                      {{ $prompt->updated_at ? $prompt->updated_at->format('d/m/Y H:i') : '—' }}
                    </td>
                    <td class="align-middle text-right">
                      @include('prompts.partials.ativar-btn')

                      <a href="{{ route('prompts.edit', $prompt) }}" class="btn btn-xs btn-sm btn-outline-primary">
                        <i class="fas fa-edit mr-1"></i> Editar
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        @include('prompts.partials.logs-modal', [
            'promptNome' => $activeVersion?->nome ?? ucfirst($slug),
            'logs' => $logsBySlug[$slug] ?? [],
        ])
      @endforeach

    </div>
  </div>
@endsection
