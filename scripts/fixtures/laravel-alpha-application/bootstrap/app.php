<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: dirname(__DIR__) . '/routes/web.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(App\WorkerIdentity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $error, Request $request) {
            $status = $error instanceof Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $error->getStatusCode() : 500;
            return response()->json(['error' => 'request_rejected'], $status);
        });
    })->create();
