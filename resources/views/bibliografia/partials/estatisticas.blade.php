@if ($estatisticas['cached'])
  <span class="badge badge-outline-info"> ⚡ Em cache. Economizou {{ $estatisticas['usage']['total_tokens'] }} tokens</span>
@else
  <span class="badge badge-outline-info"> Tempo: {{ $estatisticas['duration'] }}s</span>
  <span class="badge badge-outline-info"> {{ $estatisticas['usage']['total_tokens'] ?? '-' }} tokens</span>
@endif


{{-- @if ($estatisticas['cached'])
  <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-semibold">
    ⚡ Em Cache
  </span>
@endif --}}
