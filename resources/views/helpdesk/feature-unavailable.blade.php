@extends('layouts.public')
@section('title', $featureName.' Unavailable')
@section('content')
<main class="page-wrap help-scene">
    <section class="container workspace-empty-state public-feature-unavailable" role="status">
        <strong>{{ $featureName }} is temporarily unavailable</strong>
        <p>{{ $message }}</p>
        <a class="workspace-button workspace-button--primary" href="{{ route('home') }}">Return to helpdesk</a>
    </section>
</main>
@endsection
