@extends('layouts.staff')
@section('title', $article->exists ? 'Edit Article' : 'New Article')
@section('content')
<div class="workspace-page article-editor-page">
    <nav class="ticket-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.knowledge-base') }}">Knowledge base</a><span aria-hidden="true">/</span><span aria-current="page">{{ $article->exists ? 'Edit article' : 'New article' }}</span>
    </nav>
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">KNOWLEDGE BASE / ARTICLE EDITOR</span>
            <h1>{{ $article->exists ? 'Edit article' : 'Create an article' }}</h1>
            <p>Write a helpful guide and add images to make each step easy to follow.</p>
        </div>
    </header>
    <section class="workspace-card article-editor-card">
        @if ($errors->any())
            <div class="workspace-alert workspace-alert--error" role="alert"><strong>Please review these fields:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form action="{{ $article->exists ? route('admin.knowledge-base.update', $article) : route('admin.knowledge-base.store') }}" method="post" enctype="multipart/form-data" class="article-editor-form">
            @csrf
            @if ($article->exists) @method('put') @endif
            <div class="article-editor-fields">
                <label class="article-form-field"><span>Article title <b>*</b></span><input name="title" value="{{ old('title', $article->title) }}" maxlength="180" required placeholder="e.g. Connect to campus Wi-Fi"></label>
                <label class="article-form-field"><span>Category <b>*</b></span><input name="category" value="{{ old('category', $article->category) }}" maxlength="80" required placeholder="e.g. Network"></label>
            </div>
            <label class="article-form-field"><span>Short summary <b>*</b></span><textarea name="summary" rows="3" maxlength="500" required placeholder="Briefly describe what this article helps with.">{{ old('summary', $article->summary) }}</textarea><small>Displayed in the article list.</small></label>
            <div class="article-form-field article-content-field">
                <span id="article-content-label">Article content <b>*</b></span>
                <div class="article-content-toolbar" role="toolbar" aria-label="Article formatting">
                    <button type="button" data-editor-command="bold" aria-label="Bold" title="Bold"><strong>B</strong></button>
                    <button type="button" data-editor-command="italic" aria-label="Italic" title="Italic"><em>I</em></button>
                    <button type="button" data-editor-command="underline" aria-label="Underline" title="Underline"><u>U</u></button>
                    <button type="button" data-editor-command="strikeThrough" aria-label="Strikethrough" title="Strikethrough"><s>S</s></button>
                    <span class="article-content-toolbar-divider" aria-hidden="true"></span>
                    <button type="button" data-editor-command="formatBlock" data-editor-value="h1" aria-label="Heading 1" title="Heading 1">H1</button>
                    <button type="button" data-editor-command="formatBlock" data-editor-value="h2" aria-label="Heading 2" title="Heading 2">H2</button>
                    <button type="button" data-editor-command="formatBlock" data-editor-value="p" aria-label="Paragraph" title="Paragraph">P</button>
                    <button type="button" data-editor-command="formatBlock" data-editor-value="pre" aria-label="Code block" title="Code block">&lt;/&gt;</button>
                    <span class="article-content-toolbar-divider" aria-hidden="true"></span>
                    <button type="button" data-editor-command="insertUnorderedList" aria-label="Bulleted list" title="Bulleted list">• List</button>
                    <button type="button" data-editor-command="insertOrderedList" aria-label="Numbered list" title="Numbered list">1. List</button>
                    <button type="button" data-editor-command="formatBlock" data-editor-value="blockquote" aria-label="Quote" title="Quote">Quote</button>
                    <button type="button" data-editor-command="insertHorizontalRule" aria-label="Horizontal divider" title="Horizontal divider">Divider</button>
                    <span class="article-content-toolbar-divider" aria-hidden="true"></span>
                    <button type="button" data-editor-link aria-label="Insert link" title="Insert link">Link</button>
                    <button type="button" data-editor-command="undo" aria-label="Undo" title="Undo">Undo</button>
                    <button type="button" data-editor-command="redo" aria-label="Redo" title="Redo">Redo</button>
                    <button type="button" data-editor-command="removeFormat" aria-label="Clear formatting" title="Clear formatting">Clear</button>
                </div>
                <div class="article-content-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="article-content-label" aria-required="true" data-article-content-editor data-placeholder="Write the full instructions or answer here.">{{-- Safe, allow-listed article markup is supplied by the controller. --}}{!! $articleContent !!}</div>
                <textarea class="article-content-value" name="body" tabindex="-1" aria-hidden="true" data-article-content-value></textarea>
                <small>Use the toolbar to format headings, lists, emphasis, links, and quotes.</small>
                <small class="article-content-error" data-article-content-error hidden>Article content is required.</small>
            </div>

            <section class="article-image-panel" aria-labelledby="article-images-heading">
                <div class="article-image-panel-heading">
                    <div><span class="workspace-eyebrow">VISUAL ATTACHMENTS</span><h2 id="article-images-heading">Article images</h2><p>Upload screenshots or diagrams to make the instructions clearer.</p></div>
                </div>
                @if ($article->exists && $article->images->isNotEmpty())
                    <div class="article-image-list">
                        @foreach ($article->images as $image)
                            <label class="article-image-item">
                                <img src="{{ $image->url() }}" alt="" loading="lazy">
                                <span><strong>Image {{ $loop->iteration }}</strong><small>Select to remove this attachment when saving.</small></span>
                                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" aria-label="Remove image {{ $loop->iteration }}">
                            </label>
                        @endforeach
                    </div>
                @endif
                <label class="article-image-upload" for="article-images">
                    <span class="article-image-upload-icon" aria-hidden="true">↑</span>
                    <strong>Add image attachments</strong>
                    <small>Choose up to 5 JPEG, PNG, or WebP images, up to 5 MB each.</small>
                    <input id="article-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                </label>
            </section>

            <label class="article-publish-toggle"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $article->is_published))><span><strong>Publish this article</strong><small>Published articles are visible on the public Helpdesk knowledge base.</small></span></label>
            <div class="article-editor-actions"><a class="workspace-button workspace-button--secondary" href="{{ route('admin.knowledge-base') }}">Cancel</a><button class="workspace-button workspace-button--primary" type="submit">{{ $article->exists ? 'Save changes' : 'Create article' }}</button></div>
        </form>
    </section>
</div>
@endsection
