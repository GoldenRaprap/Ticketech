@extends('layouts.staff')
@section('title', 'Profile')
@section('content')
@php
    $user = auth()->user();
    $roleLabel = $user->isAdmin() ? 'Administrator' : 'Helpdesk Staff';
@endphp
<div class="workspace-page profile-page">
    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Account settings</span>
            <h1>My profile</h1>
            <p>View your account information and staff access details.</p>
        </div>
        <span class="profile-account-status"><span aria-hidden="true"></span> Active account</span>
    </header>

    <section class="profile-hero-card">
        <div class="profile-hero-pattern" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="profile-hero-content">
            <span class="profile-large-avatar" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <div class="profile-hero-copy">
                <span class="profile-role-badge">{{ $roleLabel }}</span>
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
            </div>
            <div class="profile-hero-meta"><small>Member since</small><strong>{{ $user->created_at->format('F Y') }}</strong></div>
        </div>
    </section>

    <div class="profile-content-grid">
        <section class="workspace-card profile-information-card">
            <div class="workspace-card-heading">
                <div><span class="workspace-eyebrow">Personal details</span><h2>Account information</h2><p>Update the name and email associated with your staff account.</p></div>
            </div>
            @if ($errors->any())
                <div class="workspace-alert workspace-alert--error" role="alert"><strong>Unable to update profile:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('staff.profile.update') }}" method="post" class="profile-edit-form">
                @csrf
                @method('put')
                <label class="profile-edit-field" for="profile-name"><span><span class="profile-detail-icon" aria-hidden="true">♙</span>Full name</span><input id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="255" required></label>
                <label class="profile-edit-field" for="profile-email"><span><span class="profile-detail-icon" aria-hidden="true">@</span>Email address</span><input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" maxlength="255" required></label>
                <div class="profile-detail-row"><dt><span class="profile-detail-icon" aria-hidden="true">▤</span>Account role</dt><dd><span class="profile-detail-role">{{ $roleLabel }}</span></dd></div>
                <div class="profile-detail-row"><dt><span class="profile-detail-icon" aria-hidden="true">◷</span>Account created</dt><dd>{{ $user->created_at->format('F j, Y') }}</dd></div>
                <p class="profile-information-note"><span aria-hidden="true">ⓘ</span> If you change your email address, use the new address for future sign-ins.</p>
                <div class="profile-form-actions"><button class="workspace-button workspace-button--primary" type="submit">Save profile changes</button></div>
            </form>
        </section>

        <aside class="profile-security-column">
            <section class="workspace-card profile-security-card">
                <span class="profile-security-icon" aria-hidden="true">♢</span>
                <span class="workspace-eyebrow">Account protection</span>
                <h2>Password &amp; security</h2>
                <p>Keep your helpdesk account secure by using a strong, unique password.</p>
                <a class="workspace-button workspace-button--secondary workspace-button--full" href="{{ route('password.request') }}">Reset your password <span aria-hidden="true">→</span></a>
            </section>
            <section class="profile-access-card">
                <span class="profile-access-icon" aria-hidden="true">✓</span>
                <div><strong>Staff access enabled</strong><p>Your account can access staff workspace features.</p></div>
            </section>
        </aside>
    </div>
</div>
@endsection
