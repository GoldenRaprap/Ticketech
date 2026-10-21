@extends('layouts.staff')
@section('title', $article->exists ? 'Edit Article' : 'New Article')
@section('content')
<div class="workspace-page article-editor-page">
    <nav class="ticket-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('staff.knowledge-base') }}">Knowledge base</a><span aria-hidden="true">/</span><span aria-current="page">{{ $article->exists ? 'Edit article' : 'New article' }}</span>
    </nav>
    <header class="workspace-page-heading">
        <div><span class="workspace-eyebrow">Knowledge base</span><h1>{{ $article->exists ? 'Edit article' : 'Create an article' }}</h1><p>Write a clear answer that helps the PCU community resolve common questions.</p></div>
    </header>
    <section class="workspace-card article-editor-card">
        @if ($errors->any())
            <div class="workspace-alert workspace-alert--error" role="alert"><strong>Please review these fields:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form action="{{ $article->exists ? route('staff.knowledge-base.update', $article) : route('staff.knowledge-base.store') }}" method="post" class="article-editor-form">
            @csrf
            @if ($article->exists) @method('put') @endif
            <label class="article-form-field"><span>Article title <b>*</b></span><input name="title" value="{{ old('title', $article->title) }}" maxlength="180" required placeholder="e.g. Connect to campus Wi-Fi"></label>
            <label class="article-form-field"><span>Category <b>*</b></span><input name="category" value="{{ old('category', $article->category) }}" maxlength="80" required placeholder="e.g. Network"></label>
            <label class="article-form-field"><span>Short summary <b>*</b></span><textarea name="summary" rows="3" maxlength="500" required placeholder="Briefly describe what this article helps with.">{{ old('summary', $article->summary) }}</textarea><small>Displayed in the article list.</small></label>
            <label class="article-form-field"><span>Article content <b>*</b></span><textarea name="body" rows="14" maxlength="30000" required placeholder="Write the full instructions or answer here.">{{ old('body', $article->body) }}</textarea><small>Plain text is supported. Separate paragraphs with a blank line.</small></label>
            <label class="article-publish-toggle"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $article->is_published))><span><strong>Publish this article</strong><small>Published articles are visible on the public Helpdesk knowledge base.</small></span></label>
            <div class="article-editor-actions"><a class="workspace-button workspace-button--secondary" href="{{ route('staff.knowledge-base') }}">Cancel</a><button class="workspace-button workspace-button--primary" type="submit">{{ $article->exists ? 'Save changes' : 'Create article' }}</button></div>
        </form>
    </section>
</div>
@endsection
