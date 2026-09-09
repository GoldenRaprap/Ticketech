@extends('layouts.staff')
@section('title', 'Reports')
@section('content')
<div class="staff-heading"><div><h1>Reports</h1><p>Help Desk activity overview · sample reporting data.</p></div><select aria-label="Report period"><option>Last 30 days</option><option>This semester</option><option>This year</option></select></div>
<section class="stat-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
    @foreach ([['Total Tickets','59'],['Open Tickets','23'],['Resolved Tickets','14'],['Closed Tickets','28'],['Avg. Resolution','1.8 days']] as [$label, $value])<article class="stat-card"><span>{{ $label }}</span><strong style="font-size:23px">{{ $value }}</strong><small>Sample data</small></article>@endforeach
</section>
<div class="support-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
    <section class="panel"><div class="panel-heading"><h2>Tickets by Category</h2></div><div class="bars">
        @foreach ([['Bluebook',78,'14'],['Network',62,'11'],['Hardware',51,'9'],['Registrar',45,'8'],['Software',39,'7'],['PRIISM',34,'6']] as [$label,$width,$count])<div class="bar-row"><span>{{ $label }}</span><div class="bar-track"><span style="width:{{ $width }}%"></span></div><strong>{{ $count }}</strong></div>@endforeach
    </div></section>
    <section class="panel"><div class="panel-heading"><h2>Tickets by Status</h2></div><div class="bars">
        @foreach ([['New',54,'12'],['Assigned',33,'5'],['In Progress',50,'8'],['Waiting for User',25,'3'],['Resolved',66,'14'],['Closed',82,'17']] as [$label,$width,$count])<div class="bar-row"><span>{{ $label }}</span><div class="bar-track"><span style="width:{{ $width }}%"></span></div><strong>{{ $count }}</strong></div>@endforeach
    </div></section>
</div>
@endsection