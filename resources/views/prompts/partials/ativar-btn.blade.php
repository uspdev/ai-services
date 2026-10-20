{{--
  Botão e formulário PATCH para tornar uma versão de prompt ativa em produção.
  Parâmetros esperados:
  - $prompt (\App\Models\AiPrompt): versão do prompt a ser ativada.
--}}
@if ($prompt->status !== \App\Enums\PromptStatus::Active)
  <form action="{{ route('prompts.activate', $prompt) }}" method="POST" class="d-inline">
    @csrf
    @method('PATCH')
    <button type="submit" class="btn btn-xs btn-sm btn-outline-success mr-1 btn-spinner"
      title="Tornar a versão v{{ $prompt->version }} ativa em produção">
      <i class="fas fa-check mr-1"></i> Ativar
    </button>
  </form>
@endif

