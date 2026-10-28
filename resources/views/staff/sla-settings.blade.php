@extends('layouts.staff')
@section('title', 'SLA Settings')
@section('heading', 'SLA Settings')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / SERVICE POLICY</span>
            <h1>Service level targets</h1>
            <p>Configure first-response and resolution targets for each ticket priority.</p>
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
            <strong>Unable to update SLA targets:</strong>
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
        <div class="admin-sla-actions">
            <button class="workspace-button workspace-button--primary" type="submit">Save SLA targets</button>
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
</div>
@endsection
