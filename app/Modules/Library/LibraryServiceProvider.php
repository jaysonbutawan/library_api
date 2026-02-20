<?php

namespace App\Modules\Library;

use Illuminate\Support\ServiceProvider;

class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Load module migrations
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // Optional: Load module routes
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}