@extends('layouts.public')
@section('title', 'Staff Portal')
@section('content')
<main class="container">
    <form class="panel staff-login" action="{{ route('staff.dashboard') }}" method="get">
        <span class="eyebrow">Staff access</span><h1 class="page-title">Ticketech Staff Portal</h1>
        <p class="page-intro">Sign-in design preview for school IT staff.</p>
        <div class="field"><label for="staff-email">Email</label><input id="staff-email" type="email" placeholder="staff@school.edu" autocomplete="username"></div>
        <div class="field"><label for="staff-password">Password</label><input id="staff-password" type="password" placeholder="Enter your password" autocomplete="current-password"></div>
        <button class="btn btn-primary" style="width:100%;margin-top:8px" type="submit">Sign In</button>
        <small style="display:block;margin-top:14px;color:var(--muted)">Visual prototype only. No credentials are checked.</small>
    </form>
</main>
@endsection