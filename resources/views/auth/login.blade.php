@extends('layouts.shop', ['title' => 'Log in'])

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-4 text-2xl font-extrabold">Log in</h1>
        <form method="post" action="{{ route('login') }}" class="card space-y-4 p-5">
            @csrf
            <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" class="input" required autofocus autocomplete="email" value="{{ old('email') }}"></div>
            <div><label class="label" for="password">Password</label><input id="password" type="password" name="password" class="input" required autocomplete="current-password"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-nut-700"> Remember me</label>
            <button class="btn-primary w-full py-3">Log in</button>
            <p class="text-center text-sm">New to NuttyLand? <a href="{{ route('register') }}" class="font-semibold underline">Create an account</a></p>
        </form>
        @if (app()->environment('local'))
            <div class="mt-4 rounded-lg border border-dashed border-nut-300 p-3 text-xs text-nut-700">
                <b>Demo accounts</b> (password <code>password</code>): owner@nuttyland.test · staff@nuttyland.test · marketing@nuttyland.test · customer@nuttyland.test
            </div>
        @endif
    </div>
@endsection
