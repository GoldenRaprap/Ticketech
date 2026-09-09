@extends('layouts.public')
@section('title', 'Knowledge Base')
@section('content')
<main class="page-wrap"><div class="container">
    <span class="eyebrow">Self-service support</span><h1 class="page-title">Knowledge Base</h1>
    <p class="page-intro">Browse common help topics. Articles shown here are sample content for the prototype.</p>
    <div class="toolbar"><input style="flex:1;max-width:520px" placeholder="Search help articles" aria-label="Search help articles"><button class="btn btn-primary" type="button">Search</button></div>
    <div class="support-grid">
        @foreach ([['Getting started with Bluebook','Sign in and find your course materials.'],['Connect to campus Wi-Fi','Set up a personal device on the school network.'],['Reset your school account password','Steps to regain access to your school account.'],['Request equipment support','What information to include with a device issue.']] as [$title, $summary])
            <article class="panel"><span class="eyebrow">IT guide</span><h3 style="margin-top:10px">{{ $title }}</h3><p style="margin:0;color:var(--muted);font-size:13px">{{ $summary }}</p></article>
        @endforeach
    </div>
</div></main>
@endsection