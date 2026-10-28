@extends('layouts.staff')
@section('title', 'Reports')
@section('heading', 'Staff Workspace')
@section('content')
<div class="workspace-page reports-page">
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Performance insights</span>
            <h1>Reports</h1>
            <p>Track helpdesk volume, resolution performance, and staff workload.</p>
        </div>
        <span class="report-range-badge"><span aria-hidden="true">◷</span> {{ $periodRangeLabel }}</span>
    </header>

    @if ($errors->any())
        <div class="report-validation-errors" role="alert">
            <strong>Check the report period.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="workspace-card report-filter-form" method="get" action="{{ route('staff.reports') }}" data-report-period-form>
        <div class="report-filter-fields">
            <label class="report-period-select" for="report-period">
                Reporting period
                <select id="report-period" name="period" data-report-period>
                    <option value="daily" @selected($period === 'daily')>Daily</option>
                    <option value="weekly" @selected($period === 'weekly')>Weekly</option>
                    <option value="monthly" @selected($period === 'monthly')>Monthly</option>
                    <option value="yearly" @selected($period === 'yearly')>Yearly</option>
                    <option value="custom" @selected($period === 'custom')>Custom range</option>
                </select>
            </label>
            <label class="report-date-field" for="report-reference-date" data-report-reference-field @if ($period === 'custom') hidden @endif>
                <span data-report-reference-label>{{ $period === 'yearly' ? 'Date in year' : ($period === 'monthly' ? 'Date in month' : ($period === 'weekly' ? 'Date in week' : 'Report date')) }}</span>
                <input id="report-reference-date" type="date" name="reference_date" value="{{ $referenceDate }}" data-report-reference-input @disabled($period === 'custom')>
            </label>
            <div class="report-custom-range" data-report-custom-range @if ($period !== 'custom') hidden @endif>
                <label class="report-date-field" for="report-start-date">
                    <span>Start date</span>
                    <input id="report-start-date" type="date" name="start_date" value="{{ $startDate }}" data-report-start-date @required($period === 'custom') @disabled($period !== 'custom')>
                </label>
                <label class="report-date-field" for="report-end-date">
                    <span>End date</span>
                    <input id="report-end-date" type="date" name="end_date" value="{{ $endDate }}" data-report-end-date @required($period === 'custom') @disabled($period !== 'custom')>
                </label>
            </div>
        </div>
        <div class="report-filter-actions">
            <button class="workspace-button workspace-button--export" type="submit" formaction="{{ route('staff.reports.export') }}">Export Excel (.xlsx) <span aria-hidden="true">↓</span></button>
        </div>
    </form>

    <section class="report-metrics" aria-label="Helpdesk performance summary">
        <article class="report-metric-card">
            <span class="report-metric-icon report-metric-icon--blue" aria-hidden="true">▤</span>
            <span class="report-metric-label">Total tickets</span>
            <strong>{{ $totalTicketCount }}</strong>
            <small>Created in this period</small>
        </article>
        <article class="report-metric-card">
            <span class="report-metric-icon report-metric-icon--amber" aria-hidden="true">◷</span>
            <span class="report-metric-label">Open tickets</span>
            <strong>{{ $openTicketCount }}</strong>
            <small>Currently awaiting completion</small>
        </article>
        <article class="report-metric-card">
            <span class="report-metric-icon report-metric-icon--green" aria-hidden="true">✓</span>
            <span class="report-metric-label">Resolved / closed</span>
            <strong>{{ $resolvedTicketCount }}</strong>
            <small>In the selected ticket cohort</small>
        </article>
        <article class="report-metric-card">
            <span class="report-metric-icon report-metric-icon--violet" aria-hidden="true">⌁</span>
            <span class="report-metric-label">Avg. resolution</span>
            <strong>{{ $averageResolutionHours !== null ? round($averageResolutionHours / 24, 1) : '—' }} <small>{{ $averageResolutionHours !== null ? 'days' : '' }}</small></strong>
            <small>For tickets with a resolution time</small>
        </article>
    </section>

    <div class="report-insights-grid">
        <section class="workspace-card report-trend-card">
            <div class="workspace-card-heading">
                <div>
                    <span class="workspace-eyebrow">Ticket activity</span>
                    <h2>Incoming vs. resolved</h2>
                    <p>Tickets received and resolved in each part of the selected period.</p>
                </div>
                <span class="chart-period-label">{{ $periodRangeLabel }}</span>
            </div>
            <div class="report-trend-legend" aria-hidden="true">
                <span><i class="report-trend-dot"></i>Incoming</span>
                <span><i class="report-trend-dot report-trend-dot--green"></i>Resolved</span>
            </div>
            @if ($totalTicketCount > 0 || $trendBuckets->sum('resolved') > 0)
                <div class="report-trend-chart" style="--report-bucket-count:{{ $trendBuckets->count() }}" role="img" aria-label="Bar chart comparing tickets received and resolved across the selected period">
                    @foreach ($trendBuckets as $bucket)
                        <div class="report-trend-column" title="{{ $bucket['label'] }}: {{ $bucket['received'] }} incoming, {{ $bucket['resolved'] }} resolved">
                            <div class="report-trend-bars">
                                <span class="report-trend-bar" style="height:{{ $bucket['receivedHeight'] }}%"></span>
                                <span class="report-trend-bar report-trend-bar--green" style="height:{{ $bucket['resolvedHeight'] }}%"></span>
                            </div>
                            <span class="report-trend-label">{{ $bucket['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="report-trend-counts">
                    <span><strong>{{ $totalTicketCount }}</strong> incoming tickets</span>
                    <span><strong>{{ $trendBuckets->sum('resolved') }}</strong> resolved in period</span>
                </div>
            @else
                <div class="workspace-empty-state workspace-empty-state--compact">
                    <strong>No ticket activity for this period</strong>
                    <p>Choose another date range to view activity.</p>
                </div>
            @endif
        </section>

        <section class="workspace-card report-performance-card">
            <div class="workspace-card-heading">
                <div>
                    <span class="workspace-eyebrow">Service performance</span>
                    <h2>Resolution rate</h2>
                    <p>Share of tickets in this period that are resolved or closed.</p>
                </div>
            </div>
            <div class="report-resolution-rate">
                <strong>{{ number_format($resolutionRate, 1) }}<small>%</small></strong>
                <span>{{ $resolvedTicketCount }} of {{ $totalTicketCount }} tickets</span>
            </div>
            <div class="report-resolution-track" role="progressbar" aria-label="Resolution rate" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $resolutionRate }}">
                <span style="width:{{ $resolutionRate }}%"></span>
            </div>
            <div class="report-performance-stats">
                <div><span>Open tickets</span><strong>{{ $openTicketCount }}</strong></div>
                <div><span>Average resolution</span><strong>{{ $averageResolutionHours !== null ? number_format($averageResolutionHours / 24, 1).' days' : 'N/A' }}</strong></div>
            </div>
        </section>
    </div>

    <div class="report-chart-grid">
        <section class="workspace-card report-chart-card">
            <div class="workspace-card-heading">
                <div>
                    <span class="workspace-eyebrow">Request distribution</span>
                    <h2>Tickets by priority</h2>
                    <p>Ticket urgency in the selected period.</p>
                </div>
                <span class="chart-period-label">{{ $totalTicketCount }} tickets</span>
            </div>
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
            <div class="chart-footnote"><span class="chart-legend-dot"></span>Tickets created in the period</div>
        </section>

        <section class="workspace-card report-chart-card">
            <div class="workspace-card-heading">
                <div>
                    <span class="workspace-eyebrow">Current queue</span>
                    <h2>Tickets by status</h2>
                    <p>Current status of tickets created in this period.</p>
                </div>
                <span class="chart-period-label">{{ $totalTicketCount }} tickets</span>
            </div>
            <div class="report-bars">
                @php($maxStatusCount = max(1, (int) $statusCounts->max()))
                @foreach (['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'] as $label)
                    @php($count = (int) $statusCounts->get($label, 0))
                    @php($width = (int) round($count / $maxStatusCount * 100))
                    <div class="report-bar-row"><span>{{ $label }}</span><div class="report-bar-track"><span style="width:{{ $width }}%"></span></div><strong>{{ $count }}</strong></div>
                @endforeach
            </div>
            <div class="chart-footnote"><span class="chart-legend-dot chart-legend-dot--green"></span>Current status breakdown</div>
        </section>

        <section class="workspace-card report-chart-card report-workload-card">
            <div class="workspace-card-heading">
                <div>
                    <span class="workspace-eyebrow">Team workload</span>
                    <h2>Tickets by staff</h2>
                    <p>Current assignments for tickets created in this period.</p>
                </div>
            </div>
            <div class="report-workload-list">
                @forelse ($staffWorkload as $staffMember)
                    <div class="report-workload-row">
                        <div class="report-workload-heading">
                            <strong>{{ $staffMember->staff_name }}</strong>
                            <span>{{ $staffMember->total_tickets }} tickets</span>
                        </div>
                        <div class="report-bar-track report-workload-track" aria-hidden="true"><span style="width:{{ $staffMember->workloadWidth }}%"></span></div>
                        <div class="report-workload-meta"><span>{{ $staffMember->open_tickets }} open</span><span>{{ $staffMember->completed_tickets }} resolved / closed</span></div>
                    </div>
                @empty
                    <div class="workspace-empty-state workspace-empty-state--compact">
                        <strong>No workload data for this period</strong>
                        <p>Ticket assignments will appear here.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <p class="report-data-note"><span aria-hidden="true">ⓘ</span> Report totals use ticket records created during the selected period. Resolution activity is grouped by the recorded resolution date.</p>
</div>
@endsection
