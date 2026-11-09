@extends('layouts.app')

@push('styles')
  <style>
    .servico-card {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-top: 4px solid #17a2b8;
      border-radius: 0.5rem;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .servico-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.1) !important;
      border-top-color: #007bff;
    }

    .servico-icone {
      width: 48px;
      height: 48px;
      border-radius: 0.5rem;
      background-color: rgba(23, 162, 184, 0.12);
      color: #17a2b8;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      transition: background-color 0.18s ease, color 0.18s ease;
    }

    .servico-card:hover .servico-icone {
      background-color: #007bff;
      color: #fff;
    }
  </style>
@endpush

@section('content')
  <div class="card border-0 bg-transparent shadow-none">
    <div class="card-header bg-white border rounded mb-4 d-flex align-items-center justify-content-between">
      <div>
        <h4 class="mb-1">Serviços de Inteligência Artificial</h4>
        <div class="text-muted">
          Este espaço reúne ferramentas e recursos desenvolvidos para apoiar as atividades cotidianas das unidades da
          Universidade de São Paulo (USP), promovendo maior eficiência, organização e padronização na execução das rotinas
          administrativas e institucionais. O objetivo é oferecer soluções práticas que contribuam para aprimorar a
          qualidade dos trabalhos, simplificar processos e favorecer o alinhamento com os padrões estabelecidos pela
          Universidade.
        </div>
      </div>
    </div>

    <div class="row">
      {{-- 1. Assistente Bibliográfico --}}
      <div class="col-md-4 mb-4">
        <div class="card servico-card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center mb-3">
              <div class="servico-icone mr-3">
                <i class="fas fa-book"></i>
              </div>
              <div>
                <h5 class="card-title mb-0 font-weight-bold text-dark">Assistente bibliográfico</h5>
                <small class="text-info font-weight-bold">Normalização &amp; Dedalus</small>
              </div>
            </div>

            <p class="card-text text-muted flex-grow-1">
              Formate, revise e padronize listas de referências bibliográficas segundo a ABNT e normas
              internacionais, com consulta integrada ao acervo Dedalus/USP.
            </p>

            <div class="mb-3">
              <span class="badge badge-light border text-secondary mr-1">ABNT / APA / IEEE</span>
              <span class="badge badge-light border text-secondary mr-1">Comparador Diff</span>
              <span class="badge badge-light border text-secondary">Busca Dedalus</span>
            </div>

            <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
              <a href="{{ route('bibliografia.index') }}"
                class="btn btn-sm btn-outline-primary stretched-link font-weight-bold">
                Acessar serviço <i class="fas fa-arrow-right ml-1"></i>
              </a>
            </div>
          </div>
        </div>
      </div>

      {{-- 2. Revisor de Ementas --}}
      <div class="col-md-4 mb-4">
        <div class="card servico-card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center mb-3">
              <div class="servico-icone mr-3">
                <i class="fas fa-graduation-cap"></i>
              </div>
              <div>
                <h5 class="card-title mb-0 font-weight-bold text-dark">Revisor de ementas</h5>
                <small class="text-info font-weight-bold">Conteúdo pedagógico PT/EN</small>
              </div>
            </div>

            <p class="card-text text-muted flex-grow-1">
              Revise, valide e padronize ementas, objetivos e conteúdo programático em português e inglês com
              análise gramatical, clareza pedagógica e terminologia técnica.
            </p>

            <div class="mb-3">
              <span class="badge badge-light border text-secondary mr-1">Revisão Bilíngue</span>
              <span class="badge badge-light border text-secondary mr-1">Score de Qualidade</span>
              <span class="badge badge-light border text-secondary">Tradução Técnica</span>
            </div>

            <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
              <a href="{{ route('ementa.index') }}"
                class="btn btn-sm btn-outline-primary stretched-link font-weight-bold">
                Acessar serviço <i class="fas fa-arrow-right ml-1"></i>
              </a>
            </div>
          </div>
        </div>
      </div>

      {{-- 3. Elaboração de Textos --}}
      <div class="col-md-4 mb-4">
        <div class="card servico-card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center mb-3">
              <div class="servico-icone mr-3">
                <i class="fas fa-pen-nib"></i>
              </div>
              <div>
                <h5 class="card-title mb-0 font-weight-bold text-dark">Elaboração de textos</h5>
                <small class="text-info font-weight-bold">Redação a partir de rascunhos</small>
              </div>
            </div>

            <p class="card-text text-muted flex-grow-1">
              Transforme rascunhos, tópicos ou anotações em comunicados, ofícios, despachos, pareceres e
              textos acadêmicos claros, coesos e bem estruturados.
            </p>

            <div class="mb-3">
              <span class="badge badge-light border text-secondary mr-1">Detecção de Estilo</span>
              <span class="badge badge-light border text-secondary mr-1">Sugestão de Assunto</span>
              <span class="badge badge-light border text-secondary">Comparador Diff</span>
            </div>

            <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
              <a href="{{ route('texto.index') }}" class="btn btn-sm btn-outline-primary stretched-link font-weight-bold">
                Acessar serviço <i class="fas fa-arrow-right ml-1"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
