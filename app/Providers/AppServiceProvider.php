<?php

namespace App\Providers;

use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReferenceNumberService::class, function ($app) {
            /** @var DatabaseManager $databaseManager */
            $databaseManager = $app->make(DatabaseManager::class);

            return new ReferenceNumberService($databaseManager->connection());
        });

        $this->app->singleton(ProcurementBusinessRules::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
