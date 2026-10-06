<section>
    <h2>Current order {{ $recordId }} · {{ $tenant ?? 'guest' }}</h2>
    @if($allowed)
        <script type="application/json" data-surfacerelay>{!! json_encode(['definition' => $definition, 'binding' => $binding], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    @endif
    <p>Hold permission: {{ $allowed ? 'allowed' : 'denied' }}. Invocation checks current permission again.</p>
    @foreach($orders as $order)<button wire:click="selectOrder({{ $order->id }})">Select order {{ $order->id }}</button>@endforeach
    <button wire:click="selectOrder(201)">Negative: try tenant B order 201</button>
    <button wire:click="holdOrder('customer-request')">Request / retry hold</button>
    <button wire:click="holdOrder('duplicate-order')">Negative: change reason on retry</button>
    @if($pending)
        <aside><p>Review: hold order {{ $recordId }} in {{ $tenant }} for {{ $pendingReason }}.</p>
        @if($approved)<p>Human approval recorded. Retry before its server expiry.</p>
        @else<button id="approve-hold" wire:click="approveHold">Approve hold in this browser</button>@endif</aside>
    @endif
    @if(auth()->id() === 1)<button id="toggle-permission" wire:click="togglePermission">Toggle own hold permission (local test control)</button>@endif
    <h3>Canonical action result</h3><pre id="action-result">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</section>
