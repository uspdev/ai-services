<span
  class="badge {{ ($explicacoes['en']['status'] ?? 'ausente') === 'presente' ? 'badge-success bg-success' : 'badge-warning bg-warning text-dark' }}">
  Status: {{ ucfirst($explicacoes['en']['status'] ?? 'ausente') }}
</span>
