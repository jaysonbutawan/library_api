<?php

namespace App\Modules\Library;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::prefix('api')->group(__DIR__ . '/Routes/api.php');
    }
}