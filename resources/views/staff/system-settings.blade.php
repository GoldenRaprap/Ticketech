@extends('layouts.staff')
@section('title', 'System Settings')
@section('content')
<div class="staff-heading"><div><h1>System Settings</h1><p>Basic presentation settings for Ticketech.</p></div><button class="btn btn-primary" type="button">Save Settings</button></div>
<div class="stack" style="max-width:820px">
    <section class="panel"><div class="panel-heading"><h2>System Information</h2></div><div class="form-grid"><div class="field"><label for="system-name">System Name</label><input id="system-name" value="Ticketech"></div><div class="field"><label for="system-description">Description</label><input id="system-description" value="School IT Help Desk and Ticketing System"></div><div class="field"><label for="support-email">Support Email</label><input id="support-email" value="helpdesk@school.edu"></div><div class="field"><label for="timezone">Timezone</label><select id="timezone"><option>School local time</option></select></div></div></section>
    <section class="panel"><div class="panel-heading"><h2>Public Help Desk</h2></div><div class="person-row"><span><strong>Accept new tickets</strong><small style="display:block;color:var(--muted)">Allow visitors to see the ticket submission form.</small></span><input type="checkbox" checked aria-label="Accept new tickets"></div><div class="person-row"><span><strong>Show knowledge base</strong><small style="display:block;color:var(--muted)">Display self-service articles to visitors.</small></span><input type="checkbox" checked aria-label="Show knowledge base"></div></section>
    <section class="panel"><div class="panel-heading"><h2>Roles</h2></div><p style="margin:0;color:var(--muted)">This prototype uses the two requested role labels: Admin and Employee / IT Staff.</p></section>
</div>
@endsection