@extends('layouts.staff', ['title' => 'Sell'])

@section('content')
    <div id="pos" class="grid gap-4 md:grid-cols-[3fr_2fr]" data-default-location="{{ $location?->id }}" data-catalogue-url="{{ route('staff.api.catalogue') }}" data-sync-url="{{ route('staff.api.sales') }}">
        <section>
            <div class="mb-3 flex gap-2">
                <select id="pos-market" class="input" aria-label="Market"></select>
            </div>
            <input id="pos-search" class="input mb-3" placeholder="Search products…" aria-label="Search products" autocomplete="off">
            <div id="pos-products" class="grid grid-cols-2 gap-2 sm:grid-cols-3" aria-live="polite"></div>
            <p id="pos-loading" class="p-6 text-center text-sm text-nut-600">Loading products…</p>
        </section>

        <aside class="card h-fit p-4 md:sticky md:top-28">
            <h2 class="font-extrabold">Current sale</h2>
            <ul id="pos-basket" class="mt-2 divide-y divide-nut-100 text-sm"></ul>
            <p id="pos-empty" class="py-6 text-center text-sm text-nut-500">Tap a product to add it.</p>
            <div class="mt-3 flex justify-between border-t border-nut-100 pt-3 text-xl font-extrabold"><span>Total</span><span id="pos-total">$0.00</span></div>
            <div class="mt-3 grid grid-cols-2 gap-2" role="radiogroup" aria-label="Payment method">
                <button type="button" data-pay="eftpos" class="pay-btn btn-secondary py-3" aria-pressed="true">EFTPOS</button>
                <button type="button" data-pay="cash" class="pay-btn btn-secondary py-3" aria-pressed="false">Cash</button>
            </div>
            <button id="pos-complete" class="btn-success mt-3 w-full py-4 text-base" disabled>Complete sale</button>
            <button id="pos-clear" class="mt-2 w-full text-sm font-semibold text-nut-600 hover:underline">Clear</button>

            <div class="mt-4 rounded-lg bg-nut-50 p-3 text-xs text-nut-700">
                <p><b>Waiting to sync:</b> <span id="pos-queue">0</span> sale(s) <button id="pos-sync" class="ml-1 font-semibold underline">Sync now</button></p>
                <p id="pos-sync-msg" class="mt-1"></p>
            </div>
        </aside>
    </div>

    <div id="pos-toast" class="pointer-events-none fixed inset-x-0 bottom-6 z-50 mx-auto hidden w-fit rounded-full bg-leaf-700 px-5 py-3 text-sm font-bold text-white shadow-lg" role="status"></div>
@endsection

@push('scripts')
    <script src="/js/staff-pos.js?v=1" defer></script>
@endpush
