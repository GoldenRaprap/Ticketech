@extends('layouts.staff')
@section('title', 'Ticket ED-2026-00125')
@section('content')
<div class="staff-heading"><div><span class="ticket-id">#ED-2026-00125</span><h1>Cannot access Bluebook</h1><p>Submitted by Mia Chen · September 26, 2026 at 8:14 AM</p></div><a class="btn btn-secondary" href="{{ route('staff.tickets') }}">Back to tickets</a></div>
<div class="info-grid">
    <div class="info-card"><small>Requester</small><strong>Mia Chen</strong></div><div class="info-card"><small>Category</small><strong>Bluebook / Online Learning</strong></div><div class="info-card"><small>Priority</small><strong>High</strong></div>
    <div class="info-card"><small>Status</small><strong><span class="badge">In Progress</span></strong></div><div class="info-card"><small>Assigned To</small><strong>IT Staff B</strong></div><div class="info-card"><small>Created</small><strong>September 26, 2026</strong></div>
</div>
<div class="ticket-layout">
    <div class="stack">
        <section class="panel"><div class="panel-heading"><h2>Problem Description</h2></div><p style="margin:0;color:#405770">I can sign in to the school portal, but Bluebook displays a blank page when I open my courses. I tried refreshing and using another browser, but the issue is still happening.</p></section>
        <section class="panel"><div class="panel-heading"><h2>Attachments</h2></div><div class="empty-note">No attachments were included with this sample ticket.</div></section>
        <section class="panel"><div class="panel-heading"><h2>Conversation</h2><span class="badge">2 comments</span></div>
            <div class="person-row" style="justify-content:flex-start;align-items:flex-start"><span class="avatar">JD</span><div><strong>Jordan Davis</strong><small style="display:block;color:var(--muted)">IT Staff · 9:02 AM</small><span>We’re checking the course access service and will update you shortly.</span></div></div>
            <div class="person-row" style="justify-content:flex-start;align-items:flex-start"><span class="avatar">MC</span><div><strong>Mia Chen</strong><small style="display:block;color:var(--muted)">Requester · 9:20 AM</small><span>Thank you. I can access the rest of the portal normally.</span></div></div>
            <div class="field" style="margin-top:16px"><label for="comment">Add a comment</label><textarea id="comment" placeholder="Write a reply or internal note"></textarea></div><div class="form-actions"><button class="btn btn-secondary" type="button">Internal note</button><button class="btn btn-primary" type="button">Add Comment</button></div>
        </section>
    </div>
    <aside class="stack">
        <section class="panel"><div class="panel-heading"><h2>Ticket Information</h2></div>
            <div class="field"><label>Status</label><select><option>In Progress</option><option>New</option><option>Assigned</option><option>Waiting for User</option><option>Resolved</option><option>Closed</option></select></div>
            <div class="field" style="margin-top:12px"><label>Priority</label><select><option>High</option><option>Low</option><option>Normal</option><option>Urgent</option></select></div>
            <div class="field" style="margin-top:12px"><label>Category</label><select><option>Bluebook / Online Learning</option><option>Registrar</option><option>PRIISM</option><option>Network</option></select></div>
            <div class="field" style="margin-top:12px"><label>Assigned To</label><select><option>IT Staff B</option><option>IT Staff A</option><option>IT Staff C</option><option>Unassigned</option></select></div>
            <div class="form-actions"><button class="btn btn-secondary btn-small" type="button">Assign</button><button class="btn btn-primary btn-small" type="button">Update</button></div>
        </section>
        <section class="panel"><div class="panel-heading"><h2>Assignment</h2></div><div class="person-row"><span>Primary Assignee</span><strong>IT Staff B</strong></div><button class="btn btn-secondary btn-small" type="button" style="margin-top:10px">Reassign</button><h3 style="margin-top:18px">Collaborators</h3><div class="person-row"><span>IT Staff A</span><span class="avatar">A</span></div><div class="person-row"><span>IT Staff C</span><span class="avatar">C</span></div><button class="btn btn-secondary btn-small" type="button" style="margin-top:10px">+ Add Collaborator</button><div class="field" style="margin-top:14px"><label>Select staff (prototype)</label><select><option>Choose a staff member</option><option>IT Staff A</option><option>IT Staff B</option><option>IT Staff C</option></select></div></section>
        <section class="panel"><div class="panel-heading"><h2>Ticket Activity</h2></div>
            @foreach ([['Ticket submitted','Mia Chen · 8:14 AM'],['Assigned to IT Staff A','Jordan Davis · 8:22 AM'],['IT Staff A added comment','9:02 AM'],['Reassigned to IT Staff B','Jordan Davis · 9:35 AM'],['IT Staff A added as collaborator','9:42 AM'],['Status changed to In Progress','IT Staff B · 10:05 AM']] as [$event, $detail])
                <div class="activity-item">{{ $event }}<small>{{ $detail }}</small></div>
            @endforeach
        </section>
    </aside>
</div>
@endsection