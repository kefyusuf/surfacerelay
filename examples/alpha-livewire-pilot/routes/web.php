<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Route};

Route::get('/', fn () => view('pilot'));
Route::get('/session', fn () => ['csrfToken' => csrf_token(), 'actorId' => Auth::id()]);
Route::post('/login', function (Request $request) {
    abort_unless(Auth::attempt($request->only('email', 'password')), 401);
    $request->session()->regenerate();
    session()->put(['tenant' => 'tenant-a', 'record' => 101]);
    return ['csrfToken' => csrf_token(), 'actorId' => Auth::id()];
});
Route::post('/logout', function (Request $request) {
    Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
    return ['csrfToken' => csrf_token(), 'actorId' => null];
});
Route::post('/tenant', function (Request $request) {
    $tenant = $request->input('tenantId');
    abort_unless(Auth::check() && is_string($tenant) && DB::table('memberships')->where('user_id', Auth::id())
        ->where('tenant_id', $tenant)->exists(), 403);
    session()->put(['tenant' => $tenant, 'record' => $tenant === 'tenant-a' ? 101 : 201]);
    return ['tenantId' => $tenant];
});
Route::get('/evidence', function () {
    abort_unless(Auth::check(), 403);
    return ['effects' => DB::table('effects')->where('actor_id', Auth::id())
        ->where('tenant_id', App\HoldGateway::tenant())->orderBy('id')->get()];
});
