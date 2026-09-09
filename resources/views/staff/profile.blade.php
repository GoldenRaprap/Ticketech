@extends('layouts.staff')
@section('title', 'Profile')
@section('content')
<div class="staff-heading"><div><h1>Profile</h1><p>Staff profile preview.</p></div><button class="btn btn-primary" type="button">Save Profile</button></div>
<section class="panel" style="max-width:760px"><div style="display:flex;align-items:center;gap:16px;margin-bottom:22px"><span class="avatar" style="width:56px;height:56px;font-size:18px">JD</span><div><h2 style="margin:0">Jordan Davis</h2><span style="color:var(--muted)">Employee / IT Staff</span></div></div>
<div class="form-grid"><div class="field"><label for="profile-name">Full Name</label><input id="profile-name" value="Jordan Davis"></div><div class="field"><label for="profile-email">Email</label><input id="profile-email" value="jordan.davis@school.edu"></div><div class="field"><label for="profile-phone">Contact Information</label><input id="profile-phone" value="Campus extension 214"></div><div class="field"><label for="profile-role">Role</label><input id="profile-role" value="Employee / IT Staff" readonly></div></div>
</section>
@endsection