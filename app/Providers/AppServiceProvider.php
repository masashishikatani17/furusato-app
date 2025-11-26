<?php

namespace App\Providers;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\HaigushaKojoCalculator;
use App\Domain\Tax\Calculators\JintekiKojoCalculator;
use App\Domain\Tax\Calculators\JintekiKojoDiffCalculator;
use App\Domain\Tax\Calculators\JuminJutakuLoanCreditCalculator;
use App\Domain\Tax\Calculators\JuminzeiKifukinCalculator;
use App\Domain\Tax\Calculators\JuminTaxCalculator;
use App\Domain\Tax\Calculators\JutakuLoanCreditCalculator;
use App\Domain\Tax\Calculators\KifukinCalculator;
use App\Domain\Tax\Calculators\KisoKojoCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\CommonSumsCalculator;
use App\Domain\Tax\Calculators\CommonTaxableBaseCalculator;
use App\Domain\Tax\Calculators\KyuyoNenkinCalculator;
use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use App\Domain\Tax\Calculators\SakimonoCalculator;
use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Calculators\TaxBaseMirrorCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Domain\Tax\Providers\MasterProvider;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Services\Tax\FurusatoMasterService;
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

        // MasterProviderContract は常に FurusatoMasterService を解決
        $this->app->singleton(MasterProviderContract::class, FurusatoMasterService::class);

        $this->app->singleton(PayloadNormalizer::class, PayloadNormalizer::class);

        // 実行順
        //  1) details→alias（UseCase 側）
        //  2) 個別“所得”化（period系：KyuyoNenkin, Sakimono, Bunri/Sogo Netting は UseCase 側で先行）
        //  3) 合計 SoT（CommonSums）
        //  4) 控除（Jinteki/Haigusha/KojoSeimeiJishin/Kiso/Kifukin → KojoAggregation）
        //  5) 課税標準/税額/特例（CommonTaxableBase → ShotokuTax → Seitoto → JuminTax → Tokurei → BunriMin → JuminzeiKifukin）
        //  6) 最終表示（FurusatoResult → TaxBaseMirror）
        $taggedCalculatorClasses = [
            // 3) 合計 SoT
            CommonSumsCalculator::class,
            // 4) 所得控除（CommonSums に依存）
            JintekiKojoCalculator::class,
            HaigushaKojoCalculator::class,
            KojoSeimeiJishinCalculator::class,
            KisoKojoCalculator::class,
            JintekiKojoDiffCalculator::class,
            KifukinCalculator::class,
            KojoAggregationCalculator::class,
            // 5) 課税標準→税額→特例
            CommonTaxableBaseCalculator::class,
            ShotokuTaxCalculator::class,
            JutakuLoanCreditCalculator::class,
            SeitotoTokubetsuZeigakuKojoCalculator::class,
            JuminTaxCalculator::class,
            JuminJutakuLoanCreditCalculator::class,
            JuminzeiKifukinCalculator::class,
            TokureiRateCalculator::class,
            JuminzeiKifukinCalculator::class,
            // 6) 最終表示
            FurusatoResultCalculator::class,
            TaxBaseMirrorCalculator::class,
        ];
\Log::info('[PIPE ORDER]', array_map(fn($c)=>defined("$c::ID")?$c::ID:$c, $taggedCalculatorClasses));
        /**
         * Root fix: Calculator ID（各クラスの ::ID）をキーにユニーク化する。
         * - 同一クラスの二重登録
         * - 異なるクラスだが ::ID が衝突
         * をここで排除し、RecalculateFurusatoPayload へは
         * 「ID一意」の配列だけを渡す。
         */
        $uniqueById = static function (array $classes): array {
            $seen = [];
            $out  = [];
            foreach ($classes as $class) {
                // クラス側に ID が無ければクラス名で代用（保険）
                $id = \defined($class.'::ID') ? $class::ID : $class;
                if (isset($seen[$id])) {
                    continue; // ★ 先勝ち：最初に並んだものが正
                }
                $seen[$id] = true;
                $out[] = $class;
            }
            return $out;
        };
        $taggedCalculatorClasses = $uniqueById($taggedCalculatorClasses);

        // period 単位（UseCase側で直接実行。tagには付けない）
        $periodicCalculatorClasses = [
            KyuyoNenkinCalculator::class,
            SakimonoCalculator::class,
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
        $this->app->bind(RecalculateFurusatoPayload::class, function ($app) use ($taggedCalculatorClasses, $uniqueById) {
            // 保険：ここでも ID でユニーク化（将来の編集ミスに強くする）
            $byId = $uniqueById($taggedCalculatorClasses);
            $calculators = \array_map(static fn (string $class) => $app->make($class), $byId);

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
