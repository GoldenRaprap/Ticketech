@extends('layouts.staff')
@section('title', 'Notifications')
@section('content')
<div class="staff-heading"><div><h1>Notifications</h1><p>Recent updates from your Help Desk workspace.</p></div><button class="btn btn-secondary" type="button">Mark all as read</button></div>
<section class="panel">
    @foreach ([['New ticket assigned to you','ED-2026-00124 · Wi-Fi disconnects in library','Today · 9:18 AM'],['Collaborator added to ticket','ED-2026-00125 · Cannot access Bluebook','Today · 9:42 AM'],['Requester replied','ED-2026-00122 · Laptop will not connect to projector','Yesterday · 2:15 PM'],['Ticket resolved','ED-2026-00118 · Printer setup in Room 204','Sep 24 · 11:30 AM']] as [$title,$detail,$time])
        <div class="person-row" style="justify-content:flex-start"><span class="action-icon" style="width:38px;height:38px;flex-basis:38px">•</span><div style="flex:1"><strong>{{ $title }}</strong><small style="display:block;color:var(--muted)">{{ $detail }}</small></div><small style="color:var(--muted)">{{ $time }}</small></div>
    @endforeach
</section>
@endsection