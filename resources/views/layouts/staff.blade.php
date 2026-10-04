<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#55301a">
    <link rel="manifest" href="/staff.webmanifest">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title>{{ $title ?? 'Staff' }} | NuttyLand Staff</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nut-50 font-sans text-nut-900 antialiased">
    <header class="sticky top-0 z-30 bg-nut-800 text-white">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
            <a href="{{ route('staff.home') }}" class="font-extrabold">NuttyLand <span class="font-normal text-nut-200">Staff</span></a>
            @isset($location)
                <span class="truncate rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">{{ $location?->name ?? 'No market selected' }}</span>
            @endisset
            <span id="net-status" class="ml-auto hidden rounded-full px-2 py-0.5 text-xs font-bold"></span>
            <form method="post" action="{{ route('logout') }}" class="ml-auto">@csrf<button class="text-xs text-nut-200 hover:text-white">Log out</button></form>
        </div>
        <nav class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-2 pb-2 text-sm font-semibold" aria-label="Staff">
            @foreach (['staff.home' => 'Today', 'staff.sell' => 'Sell', 'staff.orders' => 'Click & Collect', 'staff.stock' => 'Stock', 'staff.sales' => 'Sales log'] as $route => $label)
                <a href="{{ route($route) }}" class="shrink-0 rounded-lg px-3 py-1.5 {{ request()->routeIs($route) ? 'bg-white text-nut-800' : 'text-nut-100 hover:bg-white/10' }}">{{ $label }}</a>
            @endforeach
            @if (auth()->user()->isOwner())
                <a href="/admin" class="shrink-0 rounded-lg px-3 py-1.5 text-nut-100 hover:bg-white/10">Admin ↗</a>
            @endif
        </nav>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-4">
        @include('partials.flash')
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
