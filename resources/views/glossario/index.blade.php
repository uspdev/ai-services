@extends('laravel-usp-theme::master')

@section('title')
    Glossário
@endsection

@section('content')
    <div class="container">
        <article class="card">
            <div class="card-body">
                {!! $glossarioHtml !!}
            </div>
        </article>
    </div>
@endsection
