<?php

declare(strict_types=1);

use App\AcceptanceRuntime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Route};

Route::get('/session', function () {
    if (!session()->has('binding')) {
        session()->put('binding', ['id' => bin2hex(random_bytes(16)), 'driver' => 'acceptance.http',
            'session' => session()->getId(), 'expires' => time() + 3600]);
    }
    return ['csrfToken' => csrf_token(), 'actorId' => Auth::id(), 'tenantId' => AcceptanceRuntime::tenant(),
        'recordId' => session('record'), 'selection' => session('selection', []), 'bindingId' => session('binding.id')];
});
Route::post('/login', function (Request $request) {
    abort_unless(Auth::attempt($request->only('email', 'password')), 401);
    $request->session()->regenerate();
    session()->put('binding.session', session()->getId());
    session()->put(['tenant' => 'tenant-a', 'record' => 101, 'selection' => [101]]);
    return ['actorId' => Auth::id(), 'csrfToken' => csrf_token()];
});
Route::post('/logout', function (Request $request) {
    Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
    return ['actorId' => null, 'csrfToken' => csrf_token()];
});
Route::post('/tenant', function (Request $request) {
    $tenant = $request->input('tenantId');
    abort_unless(Auth::check() && is_string($tenant) && DB::table('memberships')->where('user_id', Auth::id())->where('tenant_id', $tenant)->exists(), 403);
    $id = $tenant === 'tenant-a' ? 101 : 201;
    session()->put(['tenant' => $tenant, 'record' => $id, 'selection' => [$id]]);
    return ['tenantId' => $tenant, 'recordId' => $id, 'selection' => [$id]];
});
Route::post('/context', function (Request $request) {
    $record = $request->input('recordId'); $selection = $request->input('selection');
    abort_unless(is_int($record) && is_array($selection) && $selection !== []
        && count(array_filter($selection, 'is_int')) === count($selection), 422);
    $ids = array_unique([$record, ...$selection]);
    abort_unless(AcceptanceRuntime::tenant() !== null && DB::table('orders')->where('tenant_id', AcceptanceRuntime::tenant())->whereIn('id', $ids)->count() === count($ids), 403);
    session()->put(['record' => $record, 'selection' => array_values(array_unique($selection))]);
    return ['recordId' => $record, 'selection' => session('selection')];
});
Route::post('/invoke', fn (Request $request) => AcceptanceRuntime::invoke($request->all()));
Route::post('/approve', function (Request $request) {
    $challenge = $request->input('challengeId');
    abort_unless(Auth::check() && is_string($challenge) && session('challenges.' . $challenge) === AcceptanceRuntime::approvalOwner(), 403);
    $receipt = AcceptanceRuntime::confirmation()->approveChallenge($challenge);
    abort_unless($receipt !== null, 409);
    return ['receipt' => $receipt];
});
// Test-only controls and observer. This fixture is never a deployable product app.
Route::post('/binding', function (Request $request) {
    abort_unless(Auth::check(), 403);
    match ($request->input('operation')) {
        'expire' => session()->put('binding.expires', time() - 1),
        'unsupported' => session()->put('binding.driver', 'unsupported.driver'),
        'remove' => session()->forget('binding'),
        default => abort(422),
    };
    return ['updated' => true];
});
Route::get('/evidence', function () {
    $effects = DB::table('effects')->orderBy('id')->get()->map(function ($row) {
        $row->order_ids = json_decode($row->order_ids, true, flags: JSON_THROW_ON_ERROR); return $row;
    });
    $audits = DB::table('surfacerelay_audit_events')->orderBy('recorded_at')->get()->map(function ($row) {
        $row->trusted_context_manifest = json_decode($row->trusted_context_manifest, true, flags: JSON_THROW_ON_ERROR); return $row;
    });
    return ['effects' => $effects, 'audits' => $audits,
        'idempotency' => DB::table('surfacerelay_idempotency_records')->select('state')->get()];
});
