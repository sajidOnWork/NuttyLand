@php($colours = ['pending_payment' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-blue-100 text-blue-800', 'preparing' => 'bg-indigo-100 text-indigo-800', 'ready' => 'bg-leaf-100 text-leaf-700', 'collected' => 'bg-nut-100 text-nut-700', 'cancelled' => 'bg-red-100 text-red-700'])
<span class="badge {{ $colours[$order->status] ?? 'bg-nut-100' }}">{{ $order->status_label }}</span>
