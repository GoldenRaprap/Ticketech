@extends('layouts.staff')
@section('title', 'SLA Settings')
@section('content')
<div class="staff-heading"><div><h1>SLA Settings</h1><p>Sample response and resolution targets by priority.</p></div><button class="btn btn-primary" type="button">Save Changes</button></div>
<div class="stat-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    @foreach ([['Low','2 business days','5 business days'],['Normal','1 business day','3 business days'],['High','4 business hours','1 business day']] as [$priority, $response, $resolution])
        <article class="stat-card"><span>{{ $priority }} Priority</span><strong style="font-size:19px">{{ $response }}</strong><small>Response target</small><div style="margin-top:12px;color:var(--muted);font-size:12px">Resolution target</div><b style="color:var(--navy)">{{ $resolution }}</b></article>
    @endforeach
</div>
<section class="panel"><div class="panel-heading"><h2>Service Level Targets</h2></div><div class="table-wrap"><table><thead><tr><th>Priority</th><th>Response Time</th><th>Resolution Time</th><th>Coverage</th></tr></thead><tbody>
<tr><td>Urgent</td><td>1 business hour</td><td>4 business hours</td><td>School hours</td></tr><tr><td>High</td><td>4 business hours</td><td>1 business day</td><td>School hours</td></tr><tr><td>Normal</td><td>1 business day</td><td>3 business days</td><td>School hours</td></tr><tr><td>Low</td><td>2 business days</td><td>5 business days</td><td>School hours</td></tr>
</tbody></table></div></section>
@endsection