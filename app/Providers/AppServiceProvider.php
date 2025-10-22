<?php

namespace App\Providers;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\HaigushaKojoCalculator;
use App\Domain\Tax\Calculators\JintekiKojoCalculator;
use App\Domain\Tax\Calculators\JuminzeiKifukinCalculator;
use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Calculators\KifukinCalculator;
use App\Domain\Tax\Calculators\KisoKojoCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\KyuyoNenkinCalculator;
use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
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

        $taggedCalculatorClasses = [
            KifukinCalculator::class,
            KisoKojoCalculator::class,
            KyuyoNenkinCalculator::class,
            JintekiKojoCalculator::class,
            HaigushaKojoCalculator::class,
            KojoAggregationCalculator::class,
            JuminzeiKifukinCalculator::class,
            ShotokuTaxCalculator::class,
            JuminTaxCalculator::class,
            SeitotoTokubetsuZeigakuKojoCalculator::class,
            TokureiRateCalculator::class,
            BunriSeparatedMinRateCalculator::class,
            FurusatoResultCalculator::class,
        ];

        $periodCalculatorClasses = [
            KojoSeimeiJishinCalculator::class,
            BunriNettingCalculator::class,
            BunriKabutekiNettingCalculator::class,
        ];

        $calculatorClasses = array_merge($taggedCalculatorClasses, $periodCalculatorClasses);

        foreach ($calculatorClasses as $class) {
            $this->app->singleton($class, $class);
        }

        $this->app->tag($taggedCalculatorClasses, 'tax.furusato.calculators');

        $this->app->bind(RecalculateFurusatoPayload::class, function ($app) use ($taggedCalculatorClasses) {
            $calculators = array_map(static fn (string $class) => $app->make($class), $taggedCalculatorClasses);

            return new RecalculateFurusatoPayload(
                $app->make(PayloadNormalizer::class),
                $calculators,
                $app->make(FurusatoResultCalculator::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
        Paginator::useBootstrapFive();
    }
}
