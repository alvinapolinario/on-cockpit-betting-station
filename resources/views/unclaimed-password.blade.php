@extends('layouts.layoutMaster')

@section('title', 'Password Required')

@section('content')
<div class="container mt-5">
    <h3>Enter Password to Access Unclaimed Page</h3>
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <form method="POST" action="{{ route('unclaimed.password.check') }}">
        @csrf
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="Enter password..." required>
        </div>
        <button type="submit" class="btn btn-primary">Access</button>
    </form>
</div>
@endsection
