{{--
  Badge de status do ciclo de vida do prompt (`active`, `draft`, `archived`).
  Parâmetros esperados no escopo:
  - $prompt (\App\Models\AiPrompt): instância da versão do prompt.
  - $badgeClassExtra (string, opcional): classes utilitárias extras (ex.: 'ml-1').
--}}
@php
  $statusBadge = match ($prompt->status) {
      \App\Enums\PromptStatus::Active => 'badge-success',
      \App\Enums\PromptStatus::Draft => 'badge-info',
      \App\Enums\PromptStatus::Archived => 'badge-secondary',
  };
@endphp
<span class="badge {{ $statusBadge }} {{ $badgeClassExtra ?? '' }}">{{ $prompt->status->label() }}</span>

