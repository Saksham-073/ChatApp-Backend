<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Return resources without the outer "data" wrapper (paginated responses keep it)
        JsonResource::withoutWrapping();
    }
}
