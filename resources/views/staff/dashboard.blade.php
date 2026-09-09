@extends('layouts.staff')
@section('title', 'Dashboard')
@section('content')
<div class="staff-heading"><div><h1>Dashboard</h1><p>Overview of your Help Desk activity.</p></div><a class="btn btn-secondary" href="{{ route('staff.tickets') }}">View all tickets</a></div>
<section class="stat-grid">
    @foreach ([['New Tickets', '12'], ['Assigned to Me', '5'], ['In Progress', '8'], ['Waiting for User', '3'], ['Resolved', '14'], ['Closed', '28']] as [$label, $value])
        <article class="stat-card"><span>{{ $label }}</span><strong>{{ $value }}</strong><small>Sample data</small></article>
    @endforeach
</section>
<section class="panel">
    <div class="panel-heading"><h2>Recent Tickets</h2><a href="{{ route('staff.tickets') }}">View all</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Ticket #</th><th>Subject</th><th>Requester</th><th>Category</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Last Updated</th></tr></thead>
        <tbody>
            @foreach ([['ED-2026-00125','Cannot access Bluebook','Mia Chen','Bluebook','High','IT Staff B','In Progress','Today, 10:42 AM'],['ED-2026-00124','Wi-Fi disconnects in library','Noah Williams','Network','Normal','Jordan Davis','Assigned','Today, 9:18 AM'],['ED-2026-00123','Student portal shows an error','Ava Thompson','PRIISM','Normal','Unassigned','New','Yesterday, 3:50 PM'],['ED-2026-00122','Laptop will not connect to projector','Ethan Brown','Hardware','Low','Jordan Davis','Waiting for User','Sep 24, 2026']] as $ticket)
                <tr><td><a href="{{ route('staff.ticket-detail') }}">{{ $ticket[0] }}</a></td><td>{{ $ticket[1] }}</td><td>{{ $ticket[2] }}</td><td>{{ $ticket[3] }}</td><td>{{ $ticket[4] }}</td><td>{{ $ticket[5] }}</td><td><span class="badge">{{ $ticket[6] }}</span></td><td>{{ $ticket[7] }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</section>
@endsection