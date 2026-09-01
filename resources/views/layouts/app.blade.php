@extends('laravel-usp-theme::master')

{{-- Blocos do laravel-usp-theme --}}
{{-- Ative ou desative cada bloco --}}

{{-- Target:card-header; class:card-header-sticky --}}
@include('laravel-usp-theme::blocos.sticky')

{{-- Target: button, a; class: btn-spinner, spinner --}}
@include('laravel-usp-theme::blocos.spinner')

{{-- Target: table; class: datatable-simples --}}
@include('laravel-usp-theme::blocos.datatable-simples')

{{-- Fim de blocos do laravel-usp-theme --}}

@section('title')
  @parent
@endsection

@stack('styles')

@section('styles')
  @parent
  <style>
    .badge-outline-primary {
      color: #007bff;
      background-color: transparent;
      border: 1px solid #007bff;
      padding: 0.25em 0.4em;
    }

    .badge-outline-info {
      color: #17a2b8;
      background-color: transparent;
      border: 1px solid #17a2b8;
      padding: 0.25em 0.4em;
    }

    /* gap-2 do BS5 */
    .gap-2 {
      gap: 0.5rem;
    }
  </style>
@endsection

{{-- blocos da aplicação --}}
@include('blocos.textarea-autoexpand')
@include('blocos.usptheme-contador')

@stack('scripts')
