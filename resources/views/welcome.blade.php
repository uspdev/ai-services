@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-header h4">
      Serviços
    </div>
    <div class="card-body">
      <div class="mb-4">
        <a href="bibliografia">Assistente bibliográfico</a><br>
        <small class="text-muted">Formate, revise e padronize referências bibliográficas de acordo com a ABNT.</small>
      </div>

      <div class="mb-4">
        <a href="ementa">Revisor de ementas e conteúdo pedagógico</a><br>
        <small class="text-muted">Revise, valide e padronize ementas pedagógicas com análise gramatical e técnica.</small>
      </div>

      <div>
        <a href="texto">Assistente de elaboração de textos</a><br>
        <small class="text-muted">Elabore e aprimore comunicados, ofícios, pareceres e textos acadêmicos a partir de rascunhos.</small>
      </div>
    </div>
  </div>
@endsection
