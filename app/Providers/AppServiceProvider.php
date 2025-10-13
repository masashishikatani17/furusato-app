<?php

namespace App\Providers;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Domain\Tax\Providers\MasterProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 予防的に 'files' を明示バインド（コンテナの基本バインドが読めない時の復旧策）
        $this->app->singleton('files', function () {
            return new Filesystem();
        });

        $this->app->singleton(MasterProviderContract::class, MasterProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');
        Paginator::useBootstrapFive();
    }
}
