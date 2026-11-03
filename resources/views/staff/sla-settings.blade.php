@extends('layouts.staff')
@section('title', 'Ticket Options')
@section('heading', 'Ticket Options')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / SERVICE POLICY &amp; TICKET SETUP</span>
            <h1>Ticket options</h1>
            <p>Configure service targets, subject-based priority rules, and the departments and categories available on ticket submissions.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Admin configuration</span>
    </header>

    <section class="admin-policy-note">
        <span class="admin-policy-note-icon" aria-hidden="true">i</span>
        <div><strong>Targets are configurable references</strong><p>Saved values are shown here for staff guidance. The system currently records calendar elapsed time but does not calculate SLA deadlines or breaches.</p></div>
        <span class="admin-readonly-badge">Editable</span>
    </section>

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="workspace-alert workspace-alert--error" role="alert">
            <strong>Unable to save these settings:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.sla-settings.update') }}" method="post" class="admin-sla-form">
        @csrf
        @method('put')
        <section class="admin-sla-grid" aria-label="Service targets by priority">
            @foreach ($targets as $key => $target)
                <article class="admin-sla-card {{ $target['class'] }}">
                    <header><span class="admin-priority-indicator"></span><span>{{ $target['priority'] }} priority</span><span class="admin-readonly-badge">Editable</span></header>
                    <p>{{ $target['description'] }}</p>
                    @foreach (['first_response' => 'First response', 'resolution' => 'Target resolution'] as $targetKey => $label)
                        <div class="admin-sla-target">
                            <label for="sla-{{ $key }}-{{ $targetKey }}">{{ $label }}</label>
                            <div class="admin-sla-input-group">
                                <input
                                    id="sla-{{ $key }}-{{ $targetKey }}"
                                    name="targets[{{ $key }}][{{ $targetKey }}][value]"
                                    type="number"
                                    min="1"
                                    max="999"
                                    step="1"
                                    value="{{ old("targets.{$key}.{$targetKey}.value", $target[$targetKey]['value']) }}"
                                    required
                                >
                                <select name="targets[{{ $key }}][{{ $targetKey }}][unit]" aria-label="{{ $label }} unit for {{ strtolower($target['priority']) }} priority">
                                    <option value="business_hour" @selected(old("targets.{$key}.{$targetKey}.unit", $target[$targetKey]['unit']) === 'business_hour')>Business hours</option>
                                    <option value="business_day" @selected(old("targets.{$key}.{$targetKey}.unit", $target[$targetKey]['unit']) === 'business_day')>Business days</option>
                                </select>
                            </div>
                            @error("targets.{$key}.{$targetKey}.value")
                                <small class="admin-sla-error">{{ $message }}</small>
                            @enderror
                            @error("targets.{$key}.{$targetKey}.unit")
                                <small class="admin-sla-error">{{ $message }}</small>
                            @enderror
                        </div>
                    @endforeach
                    <footer><span aria-hidden="true">◷</span> Reference target only</footer>
                </article>
            @endforeach
        </section>
        <section class="admin-console-card" aria-labelledby="priority-rules-heading">
            <header class="admin-console-card-heading">
                <div>
                    <span class="admin-page-eyebrow">AUTOMATIC PRIORITY</span>
                    <h2 id="priority-rules-heading">Subject keyword rules</h2>
                    <p>New tickets are classified from the subject only. Add comma- or line-separated words and phrases. The system checks configured rules from Urgent through Low; tickets with no match default to Low.</p>
                </div>
            </header>
            <div class="admin-priority-rules-grid">
                @foreach (['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $key => $label)
                    <label class="admin-priority-rule-field" for="priority-keywords-{{ $key }}">
                        <span>{{ $label }} priority keywords</span>
                        <textarea id="priority-keywords-{{ $key }}" name="priority_keywords[{{ $key }}]" rows="3" maxlength="2000" placeholder="Separate keywords with commas or new lines">{{ old("priority_keywords.{$key}", $priorityKeywords[$key]) }}</textarea>
                        @error("priority_keywords.{$key}")
                            <small class="admin-sla-error">{{ $message }}</small>
                        @enderror
                    </label>
                @endforeach
            </div>
        </section>
        <div class="admin-sla-actions">
            <button class="workspace-button workspace-button--primary" type="submit">Save ticket options</button>
        </div>
    </form>

    <section class="admin-console-card">
        <header class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">HOW TO INTERPRET</span><h2>Service target definitions</h2><p>These settings define target guidance and are not currently used to calculate ticket deadlines or breaches.</p></div>
        </header>
        <div class="admin-definition-grid">
            <article><span class="admin-definition-number">01</span><div><strong>First response</strong><p>The target time for a staff member to make an initial response to the requester.</p></div></article>
            <article><span class="admin-definition-number">02</span><div><strong>Resolution</strong><p>The target time for resolving the reported issue. Reopened tickets may need a new resolution target.</p></div></article>
            <article><span class="admin-definition-number">03</span><div><strong>Coverage</strong><p>Targets can be set in business hours or business days; ticket timing displays calendar elapsed time.</p></div></article>
        </div>
    </section>

    <section class="admin-console-card">
        <header class="admin-console-card-heading">
            <div>
                <span class="admin-page-eyebrow">TICKET CLASSIFICATION</span>
                <h2>Departments and categories</h2>
                <p>Active departments and categories appear on the Submit a ticket form. Department is required; category is optional.</p>
            </div>
        </header>

        <div class="admin-ticket-options-grid">
            <section class="admin-ticket-option-panel" aria-labelledby="departments-heading">
                <header class="admin-ticket-option-heading">
                    <div>
                        <span class="admin-page-eyebrow">01 / TICKET ROUTING</span>
                        <h3 id="departments-heading">Departments</h3>
                        <p>Choose where incoming tickets are sent.</p>
                    </div>
                    <span class="admin-ticket-option-count"><span aria-hidden="true"></span>{{ $departments->where('is_active', true)->count() }} active</span>
                </header>
                <form class="admin-console-toolbar admin-ticket-option-form" action="{{ route('admin.sla-settings.departments.store') }}" method="post">
                    @csrf
                    <label class="admin-filter-field" for="department-name">
                        <span class="visually-hidden">New department name</span>
                        <input id="department-name" name="department_name" value="{{ old('department_name') }}" maxlength="255" placeholder="New department name" required>
                    </label>
                    <button class="admin-console-button" type="submit"><span aria-hidden="true">+</span> Add department</button>
                </form>
                <div class="admin-ticket-option-list">
                    @forelse ($departments as $department)
                        <article class="admin-ticket-option-item">
                            <div class="admin-ticket-option-copy"><strong>{{ $department->name }}</strong></div>
                            <span class="admin-ticket-option-status {{ $department->is_active ? '' : 'admin-ticket-option-status--inactive' }}">
                                <span aria-hidden="true"></span>{{ $department->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <form action="{{ route('admin.sla-settings.departments.toggle', $department) }}" method="post">
                                @csrf
                                @method('patch')
                                <button class="admin-console-button {{ $department->is_active ? 'admin-console-button--danger' : '' }}" type="submit">
                                    {{ $department->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                        </article>
                    @empty
                        <div class="workspace-empty-state workspace-empty-state--compact"><strong>No departments yet</strong><p>Add a department before accepting ticket submissions.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="admin-ticket-option-panel" aria-labelledby="categories-heading">
                <header class="admin-ticket-option-heading">
                    <div>
                        <span class="admin-page-eyebrow">02 / TICKET DETAILS</span>
                        <h3 id="categories-heading">Categories</h3>
                        <p>Give requesters a way to describe the issue.</p>
                    </div>
                    <span class="admin-ticket-option-count"><span aria-hidden="true"></span>{{ $categories->where('is_active', true)->count() }} active</span>
                </header>
                <form class="admin-console-toolbar admin-ticket-option-form admin-ticket-option-form--category" action="{{ route('admin.sla-settings.categories.store') }}" method="post">
                    @csrf
                    <label class="admin-filter-field" for="category-name">
                        <span class="visually-hidden">New category name</span>
                        <input id="category-name" name="category_name" value="{{ old('category_name') }}" maxlength="255" placeholder="New category name" required>
                    </label>
                    <label class="admin-filter-field" for="category-description">
                        <span class="visually-hidden">Category description</span>
                        <input id="category-description" name="description" value="{{ old('description') }}" maxlength="255" placeholder="Description (optional)">
                    </label>
                    <button class="admin-console-button" type="submit"><span aria-hidden="true">+</span> Add category</button>
                </form>
                <div class="admin-ticket-option-list">
                    @forelse ($categories as $category)
                        <article class="admin-ticket-option-item">
                            <div class="admin-ticket-option-copy">
                                <strong>{{ $category->name }}</strong>
                                @if ($category->description)
                                    <small>{{ $category->description }}</small>
                                @endif
                            </div>
                            <span class="admin-ticket-option-status {{ $category->is_active ? '' : 'admin-ticket-option-status--inactive' }}">
                                <span aria-hidden="true"></span>{{ $category->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <form action="{{ route('admin.sla-settings.categories.toggle', $category) }}" method="post">
                                @csrf
                                @method('patch')
                                <button class="admin-console-button {{ $category->is_active ? 'admin-console-button--danger' : '' }}" type="submit">
                                    {{ $category->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                        </article>
                    @empty
                        <div class="workspace-empty-state workspace-empty-state--compact"><strong>No categories yet</strong><p>Add categories to help classify incoming tickets.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
</div>
@endsection
