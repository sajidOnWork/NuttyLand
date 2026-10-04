<ol class="mb-6 flex items-center gap-2 text-xs font-semibold text-nut-500" aria-label="Checkout progress">
    @foreach (['Collection', 'Your details', 'Payment', 'Confirmed'] as $i => $label)
        <li class="flex flex-1 items-center gap-2">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $i + 1 <= $step ? 'bg-nut-800 text-white' : 'bg-nut-100 text-nut-600' }}" @if ($i + 1 === $step) aria-current="step" @endif>{{ $i + 1 }}</span>
            <span class="hidden sm:inline {{ $i + 1 <= $step ? 'text-nut-800' : '' }}">{{ $label }}</span>
            @if (! $loop->last)<span class="h-px flex-1 bg-nut-200"></span>@endif
        </li>
    @endforeach
</ol>
