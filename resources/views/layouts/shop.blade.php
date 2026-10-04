<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' | ' : '' }}NuttyLand – Nuts for a better tomorrow</title>
    <meta name="description" content="Fresh nuts, dried fruits and snacks from NuttyLand. Order online and collect at our Sydney and Wollongong markets.">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nut-50 font-sans text-nut-900 antialiased">
    @php($cartCount = app(\App\Services\Cart::class)->count())

    <header class="sticky top-0 z-30 border-b border-nut-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="NuttyLand home">
                @include('partials.logo')
                <span class="leading-tight">
                    <span class="block text-lg font-extrabold tracking-tight text-nut-800">NuttyLand</span>
                    <span class="hidden text-[10px] font-semibold uppercase tracking-widest text-leaf-600 sm:block">Nuts for a better tomorrow</span>
                </span>
            </a>

            <form action="{{ route('shop') }}" method="get" class="hidden flex-1 md:block" role="search">
                <label for="site-search" class="sr-only">Search products</label>
                <input id="site-search" name="q" value="{{ request('q') }}" class="input" placeholder="Search nuts, dried fruits, snacks…">
            </form>

            <nav class="ml-auto hidden items-center gap-5 text-sm font-semibold text-nut-700 md:flex">
                <a href="{{ route('shop') }}" class="hover:text-nut-900">Shop</a>
                <a href="{{ route('markets') }}" class="hover:text-nut-900">Markets</a>
                @auth
                    @if (auth()->user()->canUseStaffScreens())
                        <a href="{{ route('staff.home') }}" class="hover:text-nut-900">Staff</a>
                    @endif
                    @if (auth()->user()->hasRole('owner', 'marketing'))
                        <a href="/admin" class="hover:text-nut-900">Admin</a>
                    @endif
                    <a href="{{ route('account') }}" class="hover:text-nut-900">My account</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-nut-900">Log in</a>
                @endauth
            </nav>

            <a href="{{ route('cart') }}" class="relative ml-auto rounded-full p-2 text-nut-800 hover:bg-nut-50 md:ml-0" aria-label="Cart ({{ $cartCount }} items)">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>
                @if ($cartCount)
                    <span class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
        <form action="{{ route('shop') }}" method="get" class="px-4 pb-3 md:hidden" role="search">
            <label for="site-search-m" class="sr-only">Search products</label>
            <input id="site-search-m" name="q" value="{{ request('q') }}" class="input" placeholder="Search nuts, dried fruits, snacks…">
        </form>
    </header>

    <main class="mx-auto max-w-6xl px-4 pt-5 pb-28 md:pb-12">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="hidden border-t border-nut-100 bg-white md:block">
        <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-4 px-4 py-6 text-sm text-nut-600">
            <p>© {{ date('Y') }} NuttyLand · Sydney &amp; Wollongong markets</p>
            <p>Prototype – payments are simulated. Prices and schedules are sample data.</p>
        </div>
    </footer>

    {{-- Mobile bottom navigation, as in the prototype screens --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-nut-100 bg-white pb-[env(safe-area-inset-bottom)] text-[11px] font-semibold text-nut-600 md:hidden" aria-label="Main">
        @php($tabs = [
            ['home', 'Home', 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z'],
            ['shop', 'Shop', 'M4 5h16M4 12h16M4 19h16'],
            ['cart', 'Cart', 'M3 3h2l2.6 12.4a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 7H6'],
            ['account', 'Orders', 'M9 5h6m-7 4h8m-8 4h8m-8 4h5M6 3h12a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z'],
            [auth()->check() ? 'account' : 'login', 'Account', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0'],
        ])
        @foreach ($tabs as [$route, $label, $path])
            <a href="{{ route($route) }}" class="relative flex flex-col items-center gap-0.5 py-2 {{ request()->routeIs($route) ? 'text-nut-800' : '' }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                {{ $label }}
                @if ($route === 'cart' && $cartCount)
                    <span class="absolute top-1 left-1/2 ml-2 rounded-full bg-red-600 px-1.5 text-[10px] text-white">{{ $cartCount }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</body>
</html>
