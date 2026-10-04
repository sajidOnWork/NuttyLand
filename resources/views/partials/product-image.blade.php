{{-- Product photo, or an illustrated placeholder until NuttyLand supplies photos. --}}
@php
    $tints = ['Nuts' => ['#f4e8d8', '#a86b35'], 'Dried Fruits' => ['#fde8d7', '#d9772b'], 'Mixes' => ['#efe9dc', '#7c5a2e'], 'Seeds' => ['#eef6ee', '#4f7d3a'], 'Snacks & Treats' => ['#f6e6ea', '#9b4a5c']];
    [$bg, $fg] = $tints[$product->category->name ?? ''] ?? ['#f4e8d8', '#a86b35'];
@endphp
@if ($product->image_url)
    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="{{ $class ?? '' }} object-cover" loading="lazy">
@else
    <div class="{{ $class ?? '' }} relative flex items-center justify-center overflow-hidden" style="background: {{ $bg }}" role="img" aria-label="{{ $product->name }}">
        <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full opacity-90" aria-hidden="true">
            @foreach ([[30, 38, 12], [55, 30, 10], [70, 52, 13], [42, 60, 11], [62, 72, 9], [25, 66, 8]] as [$cx, $cy, $r])
                <ellipse cx="{{ $cx }}" cy="{{ $cy }}" rx="{{ $r }}" ry="{{ $r * 0.72 }}" fill="{{ $fg }}" transform="rotate({{ ($cx * 7) % 60 - 30 }} {{ $cx }} {{ $cy }})" opacity=".85"/>
            @endforeach
        </svg>
        <span class="relative rounded bg-white/85 px-2 py-0.5 text-xs font-bold text-nut-800">{{ $product->name }}</span>
    </div>
@endif
