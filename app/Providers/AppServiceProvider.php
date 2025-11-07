<?php

namespace App\Providers;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\HaigushaKojoCalculator;
use App\Domain\Tax\Calculators\JintekiKojoCalculator;
use App\Domain\Tax\Calculators\JuminzeiKifukinCalculator;
use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Calculators\KifukinCalculator;
use App\Domain\Tax\Calculators\KisoKojoCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\CommonSumsCalculator;
use App\Domain\Tax\Calculators\CommonTaxableBaseCalculator;
use App\Domain\Tax\Calculators\KyuyoNenkinCalculator;
use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Calculators\TaxBaseMirrorCalculator;
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

        // 実行順ポリシー（重要）:
        //  - period系（Bunri*, KojoSeimeiJishin）は UseCase 側で先行実行
        //  - CommonSums は控除集計（Jinteki/Haigusha/KojoAggregation）より前に置く
        //  - TaxBaseMirror は控除計算の後で最新結果をミラー
        //  - CommonTaxableBase/ShotokuTax/JuminTax → Tokurei/BunriMin → Result の順
        $taggedCalculatorClasses = [
            // 収入→基礎所得化
            KifukinCalculator::class,
            KisoKojoCalculator::class,
            KyuyoNenkinCalculator::class,
            // 合計系は控除より前に確定（sum_for_* を後段が参照）
            CommonSumsCalculator::class,
            // 人的控除・集計（sum_for_* を参照）
            JintekiKojoCalculator::class,
            HaigushaKojoCalculator::class,
            KojoAggregationCalculator::class,
            // 表示ミラー（税額・特例の前に最新値を反映）
            TaxBaseMirrorCalculator::class,
            // 課税ベース/税額は現行の並びのまま（後段で置換予定）
            CommonTaxableBaseCalculator::class,
            ShotokuTaxCalculator::class,
            // ▼ 所得税額の直後に「所得税・寄附金税額控除」を適用
            SeitotoTokubetsuZeigakuKojoCalculator::class,
            // ▼ 住民税はその後に計算
            JuminTaxCalculator::class,
            JuminzeiKifukinCalculator::class,
            TokureiRateCalculator::class,
            // 分離の下限税率 → 最終結果
            BunriSeparatedMinRateCalculator::class,
            FurusatoResultCalculator::class,
        ];

        // period 単位で UseCase 側（applyAutoCalculatedFields）から直接呼ぶ計算器群。
        // ここに登録しておけばコンテナ解決が確実になります（tag には付けない）。
        $periodicCalculatorClasses = [
            KojoSeimeiJishinCalculator::class,
            SogoShotokuNettingCalculator::class,
            SogoShotokuNettingStagesCalculator::class,
            BunriNettingCalculator::class,
            BunriKabutekiNettingCalculator::class,
        ];

        $calculatorClasses = array_merge($taggedCalculatorClasses, $periodicCalculatorClasses);

        foreach ($calculatorClasses as $class) {
            $this->app->singleton($class, $class);
        }

        $this->app->tag($taggedCalculatorClasses, 'tax.furusato.calculators');

        // RecalculateFurusatoPayload には「通常Calculator（tagged）」のみを注入する。
        // period系（prev/curr引数をとるCalculator）は、UseCase側のperiodループで個別に実行される前提。
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
