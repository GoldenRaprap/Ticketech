@extends('layouts.staff')
@section('title', 'Tickets')
@section('content')
<div class="staff-heading"><div><h1>Tickets</h1><p>Browse and review help desk requests.</p></div><span class="badge">59 total</span></div>
<section class="panel">
    <div class="toolbar">
        <input placeholder="Search tickets" aria-label="Search tickets">
        <select aria-label="Filter by status"><option>Status: All</option><option>New</option><option>Assigned</option><option>In Progress</option><option>Waiting for User</option><option>Resolved</option><option>Closed</option></select>
        <select aria-label="Filter by category"><option>Category: All</option><option>Registrar</option><option>Bluebook</option><option>PRIISM</option><option>Network</option><option>Hardware</option><option>Software</option></select>
        <select aria-label="Filter by priority"><option>Priority: All</option><option>Low</option><option>Normal</option><option>High</option><option>Urgent</option></select>
        <select aria-label="Filter by staff"><option>Assigned staff: All</option><option>Jordan Davis</option><option>IT Staff A</option><option>IT Staff B</option></select>
        <input type="date" aria-label="Filter by date">
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Ticket #</th><th>Subject</th><th>Requester</th><th>Category</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach ([['ED-2026-00125','Cannot access Bluebook','Mia Chen','Bluebook','High','IT Staff B','In Progress','Sep 26, 2026'],['ED-2026-00124','Wi-Fi disconnects in library','Noah Williams','Network','Normal','Jordan Davis','Assigned','Sep 26, 2026'],['ED-2026-00123','Student portal shows an error','Ava Thompson','PRIISM','Normal','Unassigned','New','Sep 25, 2026'],['ED-2026-00122','Laptop will not connect to projector','Ethan Brown','Hardware','Low','Jordan Davis','Waiting for User','Sep 24, 2026'],['ED-2026-00121','Unable to print transcript','Olivia Garcia','Registrar','Normal','IT Staff A','Resolved','Sep 23, 2026'],['ED-2026-00120','Install approved design software','Liam Wilson','Software','Low','IT Staff C','Closed','Sep 22, 2026']] as $ticket)
                <tr><td><a href="{{ route('staff.ticket-detail') }}">{{ $ticket[0] }}</a></td><td>{{ $ticket[1] }}</td><td>{{ $ticket[2] }}</td><td>{{ $ticket[3] }}</td><td>{{ $ticket[4] }}</td><td>{{ $ticket[5] }}</td><td><span class="badge">{{ $ticket[6] }}</span></td><td>{{ $ticket[7] }}</td><td><a href="{{ route('staff.ticket-detail') }}">Open</a></td></tr>
            @endforeach
        </tbody>
    </table></div>
</section>
@endsection