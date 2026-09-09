@extends('layouts.staff')
@section('title', 'Audit Logs')
@section('content')
<div class="staff-heading"><div><h1>Audit Logs</h1><p>Sample system activity and ticket history.</p></div><button class="btn btn-secondary" type="button">Export</button></div>
<section class="panel"><div class="toolbar"><input placeholder="Search activity" aria-label="Search activity"><input type="date" aria-label="Filter by date"><select aria-label="Filter by user"><option>All users</option><option>Jordan Davis</option><option>IT Staff A</option><option>IT Staff B</option></select></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Ticket</th><th>Details</th></tr></thead><tbody>
@foreach ([['Sep 26, 2026 10:05','IT Staff B','Status changed','ED-2026-00125','Status changed from Assigned to In Progress'],['Sep 26, 2026 09:42','Jordan Davis','Collaborator added','ED-2026-00125','IT Staff A added as collaborator'],['Sep 26, 2026 09:35','Jordan Davis','Ticket reassigned','ED-2026-00125','Reassigned from IT Staff A to IT Staff B'],['Sep 26, 2026 08:22','Jordan Davis','Ticket assigned','ED-2026-00124','Assigned to Jordan Davis']] as $log)
<tr><td>{{ $log[0] }}</td><td>{{ $log[1] }}</td><td>{{ $log[2] }}</td><td><a href="{{ route('staff.ticket-detail') }}">{{ $log[3] }}</a></td><td>{{ $log[4] }}</td></tr>
@endforeach
</tbody></table></div></section>
@endsection