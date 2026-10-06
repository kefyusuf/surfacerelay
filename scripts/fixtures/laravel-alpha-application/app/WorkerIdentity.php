<?php

namespace App;

final class WorkerIdentity
{
    public function handle($request, \Closure $next)
    {
        // Fixture-only process barrier: both requests must enter independent workers
        // before either dispatches. No application policy or effect is changed.
        $barrier = $request->header('X-Acceptance-Barrier');
        if ($barrier !== null) {
            abort_unless(is_string($barrier) && preg_match('/^[a-f0-9]{32}$/', $barrier) === 1, 422);
            $prefix = storage_path('framework/cache/race-' . $barrier . '-');
            file_put_contents($prefix . getmypid(), 'ready', LOCK_EX);
            $deadline = microtime(true) + 5;
            while (count(glob($prefix . '*')) < 2) {
                abort_if(microtime(true) >= $deadline, 503);
                usleep(10000);
            }
        }
        return $next($request)->header('X-Worker-Pid', (string) getmypid());
    }
}
