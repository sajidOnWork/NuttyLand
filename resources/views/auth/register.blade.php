@extends('layouts.shop', ['title' => 'Create account'])

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-4 text-2xl font-extrabold">Create your account</h1>
        <form method="post" action="{{ route('register') }}" class="card space-y-4 p-5">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="first_name">First name</label><input id="first_name" name="first_name" class="input" required value="{{ old('first_name') }}" autocomplete="given-name"></div>
                <div><label class="label" for="last_name">Last name</label><input id="last_name" name="last_name" class="input" required value="{{ old('last_name') }}" autocomplete="family-name"></div>
            </div>
            <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" class="input" required value="{{ old('email') }}" autocomplete="email"></div>
            <div><label class="label" for="phone">Mobile (optional)</label><input id="phone" name="phone" class="input" value="{{ old('phone') }}" autocomplete="tel"></div>
            <div><label class="label" for="password">Password</label><input id="password" type="password" name="password" class="input" required minlength="8" autocomplete="new-password"></div>
            <div><label class="label" for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password"></div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 h-4 w-4 accent-nut-700"> Send me NuttyLand news and market updates (optional)</label>
            <p class="text-xs text-nut-500">We use your details to manage your orders, in line with the Australian Privacy Principles.</p>
            <button class="btn-primary w-full py-3">Create account</button>
            <p class="text-center text-sm">Already have an account? <a href="{{ route('login') }}" class="font-semibold underline">Log in</a></p>
        </form>
    </div>
@endsection
