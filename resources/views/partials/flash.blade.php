@if (session('status'))
    <div class="mb-4 rounded-lg border border-leaf-100 bg-leaf-50 px-4 py-3 text-sm font-medium text-leaf-700" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
