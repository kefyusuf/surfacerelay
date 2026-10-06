<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PilotServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component('order-desk', OrderDesk::class);
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware('web'));
    }
}
