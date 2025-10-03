<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

class HealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! config('feature.health')) {
            return;
        }
        // すべてのプロバイダ登録後（= cache などが確実にバインドされた後）に登録する
        $this->app->booted(function () {
            Health::checks([
                DatabaseCheck::new(),
                RedisCheck::new(),
                QueueCheck::new()
                    ->onQueue('default')                           // 監視対象のキュー名
                    ->failWhenHealthJobTakesLongerThanMinutes(5)    // 5分以内に処理されなければFail
                    ->useCacheStore(config('cache.default', 'redis')),
                CacheCheck::new(),
                UsedDiskSpaceCheck::new()->failWhenUsedSpaceIsAbovePercentage(90),
            ]);
        });
    }
}
