@extends('layouts.public')
@section('title', 'Staff Portal')
@section('content')
<main class="container">
    <form class="panel staff-login" action="{{ route('staff.login.store') }}" method="post">
        @csrf
        <span class="eyebrow">Staff access</span><h1 class="page-title">Ticketech Staff Portal</h1>
        <p class="page-intro">Sign in with your PCU helpdesk account.</p>
        @if ($errors->any())
            <ul class="form-validation-errors" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <div class="field"><label for="staff-email">Email</label><input id="staff-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></div>
        <div class="field"><label for="staff-password">Password</label><input id="staff-password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="btn btn-primary" style="width:100%;margin-top:8px" type="submit">Sign In</button>
    </form>
</main>
@endsection