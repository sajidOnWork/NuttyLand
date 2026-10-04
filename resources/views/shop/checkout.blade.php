@extends('layouts.shop', ['title' => 'Checkout'])

@section('content')
    <h1 class="mb-4 text-2xl font-extrabold">Checkout</h1>
    @include('partials.checkout-steps', ['step' => 1])

    <form method="post" action="{{ route('checkout.store') }}" class="grid gap-6 md:grid-cols-[2fr_1fr]">
        @csrf
        <div class="space-y-6">
            <section class="card p-4">
                <h2 class="text-lg font-extrabold">1. Delivery method</h2>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div class="flex items-center gap-3 rounded-lg border-2 border-leaf-600 bg-leaf-50 p-3">
                        <span class="h-4 w-4 rounded-full border-4 border-leaf-600 bg-white" aria-hidden="true"></span>
                        <div><b>Click &amp; Collect</b><p class="text-xs text-nut-600">Pick up from a NuttyLand market</p></div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-nut-200 p-3 opacity-60" aria-disabled="true">
                        <span class="h-4 w-4 rounded-full border-2 border-nut-300" aria-hidden="true"></span>
                        <div><b>Home delivery</b><p class="text-xs text-nut-600">Australia-wide shipping – coming soon</p></div>
                    </div>
                </div>

                <h3 class="mt-5 font-bold">Choose your collection market &amp; day</h3>
                @if ($marketDays->isEmpty())
                    <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">No market days are open for Click &amp; Collect right now. Please check back soon.</p>
                @endif
                <div class="mt-2 space-y-4">
                    @foreach ($marketDays as $marketName => $days)
                        <fieldset>
                            <legend class="mb-1 text-sm font-semibold text-nut-700">{{ $marketName }} <span class="font-normal text-nut-500">· {{ $days->first()->location->suburb }}</span></legend>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($days as $day)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="market_day_id" value="{{ $day->id }}" class="peer sr-only" required @checked(old('market_day_id') == $day->id)>
                                        <span class="block rounded-lg border-2 border-nut-200 bg-white p-2 text-sm peer-checked:border-nut-700 peer-checked:bg-nut-50 peer-focus-visible:ring-2 peer-focus-visible:ring-nut-400">
                                            <b class="block">{{ $day->date->format('D j M') }}</b>
                                            <span class="text-xs text-nut-600">{{ $day->hours }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <section class="card p-4">
                <h2 class="text-lg font-extrabold">2. Your details</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div><label class="label" for="first_name">First name</label><input id="first_name" name="first_name" class="input" required value="{{ old('first_name', $customer->first_name) }}" autocomplete="given-name"></div>
                    <div><label class="label" for="last_name">Last name</label><input id="last_name" name="last_name" class="input" required value="{{ old('last_name', $customer->last_name) }}" autocomplete="family-name"></div>
                    <div><label class="label" for="phone">Mobile</label><input id="phone" name="phone" class="input" required value="{{ old('phone', $customer->phone) }}" autocomplete="tel"></div>
                    <div><label class="label">Email</label><p class="input bg-nut-50">{{ $customer->email }}</p></div>
                    <div class="sm:col-span-2"><label class="label" for="notes">Notes for the stall (optional)</label><textarea id="notes" name="notes" rows="2" class="input">{{ old('notes') }}</textarea></div>
                </div>
            </section>
        </div>

        <aside class="card h-fit p-4">
            <h2 class="font-extrabold">Order summary</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($lines as $line)
                    <li class="flex justify-between gap-2"><span>{{ $line['quantity'] }} × {{ $line['variant']->product->name }} {{ $line['variant']->weight_label }}</span><span>{{ \App\Support\Money::format($line['subtotal_cents']) }}</span></li>
                @endforeach
            </ul>
            <div class="mt-3 flex justify-between border-t border-nut-100 pt-3 text-lg font-extrabold"><span>Total</span><span>{{ \App\Support\Money::format($subtotal) }}</span></div>
            <button class="btn-primary mt-4 w-full py-3" @disabled($marketDays->isEmpty())>Continue to payment</button>
        </aside>
    </form>
@endsection
