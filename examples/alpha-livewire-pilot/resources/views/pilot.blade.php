<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SurfaceRelay Livewire pilot</title><link rel="stylesheet" href="/assets/pilot.css">
<script type="module" src="/assets/pilot.js"></script>@livewireStyles</head>
<body><main><header><span class="tag">0.1.0-alpha.1 · LIVEWIRE 4 · LOCAL TEST ONLY</span>
<h1>Registry-installed Livewire order desk</h1><p>Actual mounted Livewire calls the installed SurfaceRelay pipeline. Holds are local simulated effects.</p></header>
<section><h2>Sign in</h2><label>Actor <select id="actor"><option value="owner">Owner</option><option value="peer">Peer</option><option value="denied">Denied</option></select></label>
<label>Password <input id="password" type="password" value="acceptance-password"></label>
<button id="login">Sign in</button><button id="logout">Sign out</button><button id="remount">Remount component</button>
<label>Tenant <select id="tenant"><option value="tenant-a" @selected(session('tenant', 'tenant-a') === 'tenant-a')>Tenant A</option><option value="tenant-b" @selected(session('tenant') === 'tenant-b')>Tenant B</option></select></label><button id="switch-tenant">Switch tenant and remount</button>
<p id="session-state">{{ auth()->check() ? 'Signed in as actor ' . auth()->id() : 'Guest' }}</p><p id="native-state">Checking native WebMCP…</p></section>
<livewire:order-desk /><section><h2>Effects</h2><button id="refresh-evidence">Refresh effects</button><pre id="effects">[]</pre>
<p>Receipt authority stays on the server. No bank, Filament or production qualification is claimed.</p></section>
</main>@livewireScripts</body></html>
