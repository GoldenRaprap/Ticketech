@extends('layouts.staff')
@section('title', 'Reports')
@section('content')
<div class="workspace-page reports-page">
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Performance insights</span>
            <h1>Reports</h1>
            <p>Understand request volume and helpdesk workload at a glance.</p>
        </div>
        <form class="report-period-select" method="get" action="{{ route('staff.reports') }}"><span>Reporting period</span><select name="period" aria-label="Report period" onchange="this.form.submit()"><option value="7d" @selected($period === '7d')>Last 7 days</option><option value="30d" @selected($period === '30d')>Last 30 days</option><option value="semester" @selected($period === 'semester')>Last 6 months</option><option value="year" @selected($period === 'year')>Last 12 months</option><option value="all" @selected($period === 'all')>All time</option></select></form>
    </header>

    <section class="report-metrics" aria-label="Helpdesk performance summary">
        <article class="report-metric-card"><span class="report-metric-icon report-metric-icon--blue" aria-hidden="true">▤</span><span class="report-metric-label">Total tickets</span><strong>{{ $totalTicketCount }}</strong><small>Created during selected period</small></article>
        <article class="report-metric-card"><span class="report-metric-icon report-metric-icon--amber" aria-hidden="true">◷</span><span class="report-metric-label">Open tickets</span><strong>{{ $openTicketCount }}</strong><small>Across active statuses</small></article>
        <article class="report-metric-card"><span class="report-metric-icon report-metric-icon--green" aria-hidden="true">✓</span><span class="report-metric-label">Resolved / closed</span><strong>{{ $resolvedTicketCount }}</strong><small>Completed during selected period</small></article>
        <article class="report-metric-card"><span class="report-metric-icon report-metric-icon--violet" aria-hidden="true">⌁</span><span class="report-metric-label">Avg. resolution</span><strong>{{ $averageResolutionHours !== null ? round($averageResolutionHours / 24, 1) : '—' }} <small>{{ $averageResolutionHours !== null ? 'days' : '' }}</small></strong><small>Based on completed tickets</small></article>
    </section>

    <div class="report-chart-grid">
        <section class="workspace-card report-chart-card">
            <div class="workspace-card-heading"><div><span class="workspace-eyebrow">Request distribution</span><h2>Tickets by priority</h2><p>Volume by urgency across the selected period.</p></div><span class="chart-period-label">Last 30 days</span></div>
            <div class="report-bars">
                @php($maxPriorityCount = max(1, (int) $priorityCounts->max()))
                @forelse (['Urgent', 'High', 'Medium', 'Low'] as $label)
                    @php($count = (int) $priorityCounts->get($label, 0))
                    @php($width = (int) round($count / $maxPriorityCount * 100))
                    <div class="report-bar-row"><span>{{ $label }}</span><div class="report-bar-track"><span style="width:{{ $width }}%"></span></div><strong>{{ $count }}</strong></div>
                @empty
                    <p class="workspace-empty-note">No priority data for this period.</p>
                @endforelse
            </div>
            <div class="chart-footnote"><span class="chart-legend-dot"></span>Tickets received</div>
        </section>
        <section class="workspace-card report-chart-card">
            <div class="workspace-card-heading"><div><span class="workspace-eyebrow">Work in progress</span><h2>Tickets by status</h2><p>Current distribution of ticket statuses.</p></div><span class="chart-period-label">59 tickets</span></div>
            <div class="report-bars">
                @php($maxStatusCount = max(1, (int) $statusCounts->max()))
                @foreach (['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'] as $label)
                    @php($count = (int) $statusCounts->get($label, 0))
                    @php($width = (int) round($count / $maxStatusCount * 100))
                    <div class="report-bar-row"><span>{{ $label }}</span><div class="report-bar-track"><span style="width:{{ $width }}%"></span></div><strong>{{ $count }}</strong></div>
                @endforeach
            </div>
            <div class="chart-footnote"><span class="chart-legend-dot chart-legend-dot--green"></span>Ticket count</div>
        </section>
    </div>
    <p class="report-data-note"><span aria-hidden="true">ⓘ</span> Report totals are calculated from tickets stored in the helpdesk database.</p>
</div>
@endsection
