<?php

namespace App\Providers;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\HaigushaKojoCalculator;
use App\Domain\Tax\Calculators\JintekiKojoCalculator;
use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Calculators\KifukinCalculator;
use App\Domain\Tax\Calculators\KisoKojoCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Domain\Tax\Providers\MasterProvider;
use App\Domain\Tax\Support\PayloadNormalizer;
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

        $this->app->singleton(PayloadNormalizer::class, PayloadNormalizer::class);

        $calculatorClasses = [
            KifukinCalculator::class,
            KisoKojoCalculator::class,
            JintekiKojoCalculator::class,
            HaigushaKojoCalculator::class,
            KojoAggregationCalculator::class,
            ShotokuTaxCalculator::class,
            JuminTaxCalculator::class,
            SeitotoTokubetsuZeigakuKojoCalculator::class,
            FurusatoResultCalculator::class,
        ];

        foreach ($calculatorClasses as $class) {
            $this->app->singleton($class, $class);
        }

        $this->app->tag($calculatorClasses, 'tax.furusato.calculators');

        $this->app->bind(RecalculateFurusatoPayload::class, function ($app) {
            return new RecalculateFurusatoPayload(
                $app->make(PayloadNormalizer::class),
                $app->tagged('tax.furusato.calculators')
            );
        });
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
