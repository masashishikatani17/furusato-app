<?php

namespace App\Application\UseCases\Tax;

use App\Domain\Tax\Calculators\DetailsSourceAliasCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\ResultToDetailsAliasCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\SakimonoCalculator;
use App\Domain\Tax\Calculators\KyuyoNenkinCalculator;
use App\Domain\Tax\Calculators\JigyoFudosanDetailsCalculator;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Domain\Tax\Services\FurusatoScenarioTaxSummaryService;
use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use DateTimeInterface;

class RecalculateFurusatoPayload
{
    private const MASTER_KIHU_YEAR = 2025;

    /** @var array<int, object> */
    private array $calculators;

    public function __construct(
        private readonly PayloadNormalizer $normalizer,
        iterable $calculators,
        private readonly FurusatoResultCalculator $resultCalculator,
        private readonly SyoriSettingsFactory $syoriSettingsFactory,
    ) {
        $this->calculators = $this->sortCalculators($calculators);
    }

    /**
     * @param  array<string, mixed>  $inputDiff
     * @param  array<string, mixed>  $ctx  Context for calculators and persistence. Pass
     *                                     `should_flash_results` (bool) to control
     *                                     whether session flashes should be emitted.
     * @return array{payload: array<string, mixed>}
     */
    public function handle(Data $data, array $inputDiff, array $ctx = []): array
    {
        $normalizedDiff = $this->normalizer->normalize($inputDiff);
        [$payloadUpdates, $labelUpdates] = $this->partitionUpdates($normalizedDiff);
        $userId = isset($ctx['user_id']) ? (int) $ctx['user_id'] : null;

        [$payload, $syoriSettings] = $this->saveDiff($data, $payloadUpdates, $labelUpdates, $userId);
    \Log::info('[Recalc payload after saveDiff]', [
        'before_tsusan_tanki_ippan_prev' => $payload['before_tsusan_tanki_ippan_prev'] ?? null,
        'after_2jitsusan_tanki_ippan_prev' => $payload['after_2jitsusan_tanki_ippan_prev'] ?? null,
        'joto_shotoku_tanki_ippan_prev' => $payload['joto_shotoku_tanki_ippan_prev'] ?? null,
    ]);
        $calculatorCtx = array_merge($ctx, ['syori_settings' => $syoriSettings]);
        $builtCtx = $this->buildContext($data, $calculatorCtx);
        $shouldFlashResults = $builtCtx['should_flash_results'] ?? true;
        unset($builtCtx['should_flash_results']);
        $finalPayload = $this->runCalculators($payload, $builtCtx);

    \Log::info('[Recalc payload after runCalculators]', [
        'before_tsusan_tanki_ippan_prev' => $finalPayload['before_tsusan_tanki_ippan_prev'] ?? null,
        'after_2jitsusan_tanki_ippan_prev' => $finalPayload['after_2jitsusan_tanki_ippan_prev'] ?? null,
        'joto_shotoku_tanki_ippan_prev' => $finalPayload['joto_shotoku_tanki_ippan_prev'] ?? null,
    ]);

\Log::info('[DBG human-adjusted final]', [
  'tb_sogo_jumin_prev' => $finalPayload['tb_sogo_jumin_prev'] ?? null,
  'tb_sogo_jumin_curr' => $finalPayload['tb_sogo_jumin_curr'] ?? null,
  'human_diff_sum_prev' => $finalPayload['human_diff_sum_prev'] ?? null,
  'human_diff_sum_curr' => $finalPayload['human_diff_sum_curr'] ?? null,
  'human_adjusted_taxable_prev' => $finalPayload['human_adjusted_taxable_prev'] ?? null,
  'human_adjusted_taxable_curr' => $finalPayload['human_adjusted_taxable_curr'] ?? null,
]);

        $this->persistFinalPayload($data, $finalPayload, $userId);

        $this->persistResults($data, $finalPayload, $builtCtx, $userId, $shouldFlashResults);

        return ['payload' => $finalPayload];
    }

    /**
     * @param  array<string, mixed>  $diff
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function partitionUpdates(array $diff): array
    {
        $payload = [];
        $labels = [];

        foreach ($diff as $key => $value) {
            if (str_contains($key, '_label_')) {
                $labels[$key] = $value;
                continue;
            }

            // ============================================================
            // ▼ 総合譲渡・一時：旧キー（非_sogo）を完全廃止
            //   - SoTは *_sogo_* のみ
            //   - 旧キーがPOST/混入しても、ここで必ず捨てて汚染を防ぐ
            // ============================================================
            if (is_string($key) && (
                preg_match('/^sashihiki_joto_(tanki|choki)_(prev|curr)$/', $key) === 1 ||
                preg_match('/^tsusango_joto_(tanki|choki)_(prev|curr)$/', $key) === 1
            )) {
                continue;
            }

            // ▼ 表示専用（サーバ算出）キーは入力diffから除外して混入を防止
            //    - result_details.blade.php が hidden で持つ human_diff_* 等が payload に残ると、
            //      内訳表示と SoT がズレる原因になるため、必ずサーバ側で再生成する。
            if (is_string($key) && (
                str_starts_with($key, 'human_diff_') ||
                str_starts_with($key, 'human_adjusted_taxable_')
            )) {
                continue;
            }


            // ▼ 表示専用（サーバ算出）キーは入力diffから除外して混入を防止（改ざん対策）
            //    tax_kijun / tax_fukkou / tax_gokei は TaxGokeiCalculator が常に再生成する。
            if (is_string($key) && (
                str_starts_with($key, 'tax_kijun_') ||
                str_starts_with($key, 'tax_fukkou_') ||
                str_starts_with($key, 'tax_gokei_')
            )) {
                continue;
            }

            // ▼ 医療費控除：派生キーは入力diffから除外（SoTはサーバで確定）
            //    - 入力SoTは A/B のみ（kojo_iryo_shiharai_*, kojo_iryo_hotengaku_*）
            //    - それ以外（ⒸⒺⒻⒼ、第一表ブリッジ）は KojoIryoCalculator が必ず再生成する
            if (is_string($key) && (
                str_starts_with($key, 'kojo_iryo_sashihiki_') ||
                str_starts_with($key, 'kojo_iryo_shotoku_gokei_') ||
                str_starts_with($key, 'kojo_iryo_shotoku_5pct_') ||
                str_starts_with($key, 'kojo_iryo_min_threshold_') ||
                str_starts_with($key, 'kojo_iryo_kojogaku_') ||
                str_starts_with($key, 'kojo_iryo_shotoku_') ||
                str_starts_with($key, 'kojo_iryo_jumin_')
            )) {
                continue;
            }

            // ▼ 計算結果詳細（result_details.tab）等の「表示専用」派生キーは入力diffから除外
            //    - input.blade.php は 1つの<form>内に結果タブがあり、name付きreadonlyがPOSTされ得る
            //    - これらは SoT ではなく、サーバが毎回再生成すべき値
            //    - details 画面の入力SoT（syunyu/keihi/sashihiki_* 等）は除外しない
            if (is_string($key) && (
                // 総合課税の段階通算（表示専用）
                str_starts_with($key, 'tsusanmae_') ||
                str_starts_with($key, 'after_1jitsusan_') ||
                str_starts_with($key, 'after_2jitsusan_') ||
                str_starts_with($key, 'after_3jitsusan_') ||
                // 総合譲渡・一時（表示専用の派生）
                str_starts_with($key, 'after_naibutsusan_') ||
                str_starts_with($key, 'tokubetsukojo_') ||
                str_starts_with($key, 'after_joto_ichiji_tousan_') ||
                // 「通算後」互換/表示用（分離・株式等でも広く使われるため、入力からは受けない）
                str_starts_with($key, 'tsusango_') ||
                // 上場株式等の損益通算スナップショット（表示専用）
                str_starts_with($key, 'before_tsusan_') ||
                str_starts_with($key, 'after_tsusan_') ||
                // 特例控除率（結果表示用：サーバが再生成）
                str_starts_with($key, 'tokurei_rate_')
            )) {
                continue;
            }
            $payload[$key] = $value;
        }

        return [$payload, $labels];
    }

    private function persistResults(Data $data, array $payload, array $ctx, ?int $userId, bool $shouldFlashResults): void
    {
        $details = $this->resultCalculator->buildDetails($payload, $ctx);

        // ============================================================
        // ▼ 上限探索（重い）
        //  - 通常の保存/再計算では不要（result_upper タブ廃止予定）
        //  - PDF出力など「必要なときだけ」実行する
        // ============================================================
        $computeUpper = (bool)($ctx['compute_upper'] ?? false);

        // 既存の保存済みがあれば温存（compute_upper=false のときに null で上書きしない）
        $prevStoredUpper = null;
        $prevStoredScenarios = null;
        $prevGeneratedAt = null;
        if (! $computeUpper) {
            $stored = FurusatoResult::query()->where('data_id', $data->id)->value('payload');
            if (is_array($stored)) {
                $prevStoredUpper = $stored['furusato_upper'] ?? null;
                $prevStoredScenarios = $stored['furusato_upper_scenarios'] ?? null;
                $prevGeneratedAt = $stored['furusato_upper_generated_at'] ?? null;
            }
        }

        $furusatoUpper = $prevStoredUpper;
        $furusatoUpperScenarios = $prevStoredScenarios;
        $generatedAt = $prevGeneratedAt;

        if ($computeUpper) {
            try {
                /** @var FurusatoPracticalUpperLimitService $upperSvc */
                $upperSvc = app(FurusatoPracticalUpperLimitService::class);
                $furusatoUpper = $upperSvc->compute($payload, $ctx);

                $yMax = is_array($furusatoUpper) ? (int)($furusatoUpper['y_max_total'] ?? 0) : 0;
                /** @var FurusatoScenarioTaxSummaryService $scSvc */
                $scSvc = app(FurusatoScenarioTaxSummaryService::class);
                $furusatoUpperScenarios = $scSvc->build($payload, $ctx, $yMax);
                $generatedAt = now()->toIso8601String();
            } catch (\Throwable $e) {
                Log::warning('[furusato.upper] compute failed in recalc', [
                    'data_id' => (int)$data->id,
                    'err' => $e->getMessage(),
                ]);
                // 失敗時は「直前保存済み」を温存（null上書きしない）
            }
        }

        $results = [
            'details' => $details,
            'payload' => $payload,
            // TODO: Remove the legacy key once all consumers migrate to `payload`.
            'upper' => $payload,
            // 新規：保存済みSoT（画面・帳票はここを読む）
            'furusato_upper' => $furusatoUpper,
            'furusato_upper_scenarios' => $furusatoUpperScenarios,
            'furusato_upper_generated_at' => $generatedAt,
        ];

        $this->storeResults($data, $results, $userId);

        if ($shouldFlashResults) {
            session()->flash('furusato_results', $results);
            session()->flash('show_furusato_result', true);
        }
    }

    private function storeResults(Data $data, array $results, ?int $userId): void
    {
        FurusatoResult::unguarded(function () use ($data, $results, $userId): void {
            $record = FurusatoResult::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->data_id = $data->id;
                $record->created_by = $userId ?: null;
            }

            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;
            $record->payload = $results;
            $record->updated_by = $userId ?: null;

            $record->save();
        });
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>  $labelUpdates
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function saveDiff(Data $data, array $updates, array $labelUpdates, ?int $userId): array
    {
        $payload = [];
        $syoriSettings = [];

        FurusatoInput::unguarded(function () use (&$payload, &$syoriSettings, $data, $updates, $labelUpdates, $userId): void {
            $record = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->fill([
                    'data_id' => $data->id,
                    'company_id' => $data->company_id,
                    'group_id' => $data->group_id,
                    'created_by' => $userId ?: null,
                ]);
            }

            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;

            if ($labelUpdates !== []) {
                foreach ($labelUpdates as $column => $value) {
                    $record->{$column} = $value;
                }
            }

            $current = is_array($record->payload) ? $record->payload : [];
            $current = $this->normalizer->normalize($current);
            $merged = array_replace($current, $updates);
            // syori_settings は Factory から Data 単位で構築
            $syoriSettings = $this->syoriSettingsFactory->buildInitial($data);

            $payload = $this->applyAutoCalculatedFields($data, $merged, $syoriSettings);

            $record->payload = $payload;
            $record->updated_by = $userId ?: null;
            $record->save();
        });

        return [$payload, $syoriSettings];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function runCalculators(array $payload, array $ctx): array
    {
        foreach ($this->calculators as $calculator) {
            if (method_exists($calculator, 'compute')) {
                $payload = $calculator->compute($payload, $ctx);
            }
        }

        return $payload;
    }

    private function persistFinalPayload(Data $data, array $payload, ?int $userId): void
    {
        FurusatoInput::unguarded(function () use ($data, $payload, $userId): void {
            $record = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->fill([
                    'data_id' => $data->id,
                    'company_id' => $data->company_id,
                    'group_id' => $data->group_id,
                    'created_by' => $userId ?: null,
                ]);
            }

            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;
            $record->payload = $payload;
            $record->updated_by = $userId ?: null;
            $record->save();
        });
    }

    /**
     * @param  iterable<int, object>  $calculators
     * @return array<int, object>
     */
    private function sortCalculators(iterable $calculators): array
    {
        $nodes = [];
        foreach ($calculators as $calculator) {
            $class = $calculator::class;
            $id = defined($class . '::ID') ? $class::ID : $class;
            $order = defined($class . '::ORDER') ? $class::ORDER : 1000;
            $before = defined($class . '::BEFORE') ? (array) $class::BEFORE : [];
            $after = defined($class . '::AFTER') ? (array) $class::AFTER : [];

            if (isset($nodes[$id])) {
                if (config('app.debug')) {
                    throw new InvalidArgumentException(sprintf('Duplicate calculator id detected: %s', $id));
                }

                continue;
            }

            $nodes[$id] = [
                'calculator' => $calculator,
                'order' => (int) $order,
                'before' => array_values(array_filter($before, 'is_string')),
                'after' => array_values(array_filter($after, 'is_string')),
            ];
        }

        $edges = [];
        $inDegree = [];
        $missingDependencies = [];
        foreach ($nodes as $id => $meta) {
            $edges[$id] = [];
            $inDegree[$id] = 0;
        }

        foreach ($nodes as $id => $meta) {
            foreach ($meta['after'] as $afterId) {
                if (! isset($nodes[$afterId])) {
                    $missingDependencies[] = sprintf('%s.after -> %s', $id, $afterId);
                    continue;
                }

                $edges[$afterId][] = $id;
                $inDegree[$id]++;
            }

            foreach ($meta['before'] as $beforeId) {
                if (! isset($nodes[$beforeId])) {
                    $missingDependencies[] = sprintf('%s.before -> %s', $id, $beforeId);
                    continue;
                }

                $edges[$id][] = $beforeId;
                $inDegree[$beforeId]++;
            }
        }

        if ($missingDependencies !== [] && config('app.debug')) {
            $message = sprintf('Missing calculator dependencies detected: %s', implode(', ', $missingDependencies));
            throw new InvalidArgumentException($message);
        }

        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $sortedIds = [];
        while ($queue !== []) {
            usort($queue, function ($a, $b) use ($nodes) {
                $orderA = $nodes[$a]['order'];
                $orderB = $nodes[$b]['order'];
                if ($orderA === $orderB) {
                    return $a <=> $b;
                }

                return $orderA <=> $orderB;
            });

            $current = array_shift($queue);
            $sortedIds[] = $current;

            foreach ($edges[$current] ?? [] as $next) {
                $inDegree[$next]--;
                if ($inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }

            unset($edges[$current]);
        }

        if (count($sortedIds) !== count($nodes)) {
            Log::warning('RecalculateFurusatoPayload: calculator ordering contains a cycle.');

            $remaining = array_diff(array_keys($nodes), $sortedIds);
            if ($remaining !== []) {
                usort($remaining, function ($a, $b) use ($nodes) {
                    $orderA = $nodes[$a]['order'];
                    $orderB = $nodes[$b]['order'];
                    if ($orderA === $orderB) {
                        return $a <=> $b;
                    }

                    return $orderA <=> $orderB;
                });

                $sortedIds = array_merge($sortedIds, $remaining);
            }
        }

        if (config('app.debug')) {
            Log::info('[furusato.pipeline] order=[' . implode(', ', $sortedIds) . ']');
        }

        return array_map(function ($id) use ($nodes) {
            return $nodes[$id]['calculator'];
        }, $sortedIds);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function buildContext(Data $data, array $ctx): array
    {
        // モデルそのものも渡す（各 Calculator から guest, kihu_year 等を参照するケース向け）
        $ctx['data'] = $data;

        // jumin_master を data_id 単位で切り替える Calculator 向けに明示しておく
        // （コントローラ側で ctx['data_id'] を渡していなくても安全になる）
        $ctx['data_id'] = $data->id !== null ? (int) $data->id : null;

        $ctx['kihu_year'] = isset($data->kihu_year) ? (int) $data->kihu_year : 0;
        $ctx['master_kihu_year'] = self::MASTER_KIHU_YEAR;
        $ctx['company_id'] = $data->company_id !== null ? (int) $data->company_id : null;

        if (! isset($ctx['syori_settings']) || ! is_array($ctx['syori_settings'])) {
            $ctx['syori_settings'] = [];
        }

        return $ctx;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function applyAutoCalculatedFields(Data $data, array $payload, array $settings): array
    {
        unset($settings);
        
        // ▼ 旧：SeitotoKihukinTokubetsuService は二重計算になるため廃止
        //   税額控除（政党等/NPO/公益）のSoTは SeitotoTokubetsuZeigakuKojoCalculator に一本化する。
        //   互換キー（shotokuzei_zeigakukojo_seitoto_tokubetsu_* 等）は
        //   後段の LegacyMirrorCalculator が新SoTからミラー生成する。

        /** @var DetailsSourceAliasCalculator $detailsAliasCalculator */
        $detailsAliasCalculator = app(DetailsSourceAliasCalculator::class);
        $payload = array_replace(
            $payload,
            $detailsAliasCalculator->compute($payload, 'prev'),
            $detailsAliasCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $detailsAliasCalculator);

        /**
         * ▼ 事業（営業等）・不動産：内訳入力から派生値（経費合計/差引/青色前/所得）をサーバ側で確定する
         *   - JSで見えている値だけにせず、payload に必ず保存する（古い所得が残る問題の根本対策）
         *   - 所得は損益通算に使うためマイナスも保持。ただし青色控除・負債利子でマイナス幅を広げない。
         */
        /** @var JigyoFudosanDetailsCalculator $jfCalc */
        $jfCalc = app(JigyoFudosanDetailsCalculator::class);
        $payload = array_replace(
            $payload,
            $jfCalc->compute($payload, 'prev'),
            $jfCalc->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $jfCalc);

        /** @var SakimonoCalculator $sakimonoCalculator */
        $sakimonoCalculator = app(SakimonoCalculator::class);
        $payload = array_replace(
            $payload,
            $sakimonoCalculator->compute($payload, 'prev'),
            $sakimonoCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $sakimonoCalculator);

        /** KyuyoNenkin：Sakimono／Bunri 後に実行して、OTP（年金以外の合計）で年金雑所得を確定 */
        /** @var KyuyoNenkinCalculator $kyuyoNenkinCalculator */
        $kyuyoNenkinCalculator = app(KyuyoNenkinCalculator::class);
        $knCtx = [
            'kihu_year'        => (int)($data->kihu_year ?? 0),
            'guest_birth_date' => $this->normalizeBirthDateForContext($data->guest?->birth_date ?? null),
            'data'             => $data,
        ];
        $payload = $kyuyoNenkinCalculator->compute($payload, $knCtx);
        $this->assertProvidedKeys($payload, $kyuyoNenkinCalculator);

        /** @var SogoShotokuNettingCalculator $sogoShotokuCalculator */
        $sogoShotokuCalculator = app(SogoShotokuNettingCalculator::class);
        $payload = array_replace(
            $payload,
            $sogoShotokuCalculator->compute($payload, 'prev'),
            $sogoShotokuCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $sogoShotokuCalculator);

        /** @var SogoShotokuNettingStagesCalculator $sogoShotokuStagesCalculator */
        $sogoShotokuStagesCalculator = app(SogoShotokuNettingStagesCalculator::class);
        $payload = array_replace(
            $payload,
            $sogoShotokuStagesCalculator->compute($payload, 'prev'),
            $sogoShotokuStagesCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $sogoShotokuStagesCalculator);

        /** @var ResultToDetailsAliasCalculator $resultToDetailsAliasCalculator */
        $resultToDetailsAliasCalculator = app(ResultToDetailsAliasCalculator::class);

        $resultAliasContext = [
            'kihu_year' => $data->kihu_year ? (int) $data->kihu_year : self::MASTER_KIHU_YEAR,
            'guest_birth_date' => $this->normalizeBirthDateForContext($data->guest?->birth_date ?? null),
            'data' => $data,
        ];

        $payload = array_replace(
            $payload,
            $resultToDetailsAliasCalculator->compute($payload, $resultAliasContext),
        );
        $this->assertProvidedKeys($payload, $resultToDetailsAliasCalculator);

        /**
         * ▼ 分離譲渡（短期/長期）の損益通算は「最後に」確定させる
         *   - ここで BunriNetting をもう一度実行して、行レベルの
         *     before_tsusan_*,after_2jitsusan_*,joto_shotoku_* を確定版で上書きする
         *   - この後に他の Calculator は走らないので、以降 payload 内では
         *     BunriNetting の出力が常に SoT になる
         */
        /** @var BunriNettingCalculator $bunriNettingCalculator */
        $bunriNettingCalculator = app(BunriNettingCalculator::class);
        $payload = array_replace(
            $payload,
            $bunriNettingCalculator->compute($payload, 'prev'),
            $bunriNettingCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $bunriNettingCalculator);

        \Log::info('[applyAutoCalculatedFields after BunriNetting(final)]', [
            'before_tsusan_tanki_ippan_prev'    => $payload['before_tsusan_tanki_ippan_prev']    ?? null,
            'after_2jitsusan_tanki_ippan_prev'  => $payload['after_2jitsusan_tanki_ippan_prev']  ?? null,
            'joto_shotoku_tanki_ippan_prev'     => $payload['joto_shotoku_tanki_ippan_prev']     ?? null,
            'joto_shotoku_tanki_gokei_prev'     => $payload['joto_shotoku_tanki_gokei_prev']     ?? null,
        ]);

        // ▼ tsusango_%_% を DB 保存用 payload にも確定させる
        //   仕様：tsusango_%_% = max(0, after_2jitsusan_%_%)
        foreach (['prev', 'curr'] as $period) {
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                $after2Key   = sprintf('after_2jitsusan_%s_%s', $suffix, $period);
                $tsusangoKey = sprintf('tsusango_%s_%s',       $suffix, $period);
                $val         = (int) ($payload[$after2Key] ?? 0);
                $payload[$tsusangoKey] = max(0, $val);
            }

            // 一時所得も 0 下限で揃える
            $after2IchijiKey   = sprintf('after_2jitsusan_ichiji_%s', $period);
            $tsusangoIchijiKey = sprintf('tsusango_ichiji_%s',        $period);
            $val = (int) ($payload[$after2IchijiKey] ?? 0);
            $payload[$tsusangoIchijiKey] = max(0, $val);
        }

        /** @var BunriKabutekiNettingCalculator $bunriKabutekiNettingCalculator */
        $bunriKabutekiNettingCalculator = app(BunriKabutekiNettingCalculator::class);
        $payload = array_replace(
            $payload,
            $bunriKabutekiNettingCalculator->compute($payload, 'prev'),
            $bunriKabutekiNettingCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $bunriKabutekiNettingCalculator);

        \Log::info('[applyAutoCalculatedFields final snapshot]', [
            'before_tsusan_tanki_ippan_prev'   => $payload['before_tsusan_tanki_ippan_prev']   ?? null,
            'after_2jitsusan_tanki_ippan_prev' => $payload['after_2jitsusan_tanki_ippan_prev'] ?? null,
            'joto_shotoku_tanki_ippan_prev'    => $payload['joto_shotoku_tanki_ippan_prev']    ?? null,
            'tsusango_tanki_ippan_prev'        => $payload['tsusango_tanki_ippan_prev']        ?? null,
        ]);

        return $payload;
    }
    
    private function normalizeBirthDateForContext(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
        }

        return null;
    }

    private function assertProvidedKeys(array $payload, ProvidesKeys $service): void
    {
        if (! config('app.debug')) {
            return;
        }

        $missing = [];
        foreach ($service::provides() as $key) {
            if (! array_key_exists($key, $payload)) {
                $missing[] = $key;
            }
        }

        if ($missing === []) {
            return;
        }

        $class = $service::class;
        $message = sprintf('%s missing keys: %s', $class, implode(', ', $missing));
        Log::warning($message);

        $existing = session()->get('warning');
        $combined = $existing ? $existing . PHP_EOL . $message : $message;
        session()->flash('warning', $combined);
    }
}