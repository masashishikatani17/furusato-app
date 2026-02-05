<?php

namespace App\Application\UseCases\Tax;

use Illuminate\Support\Arr;
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
    /**
     * デバッグログ（デフォルト無効）
     * - .env: FURUSATO_DEBUG_LOG=1 のときのみ出す
     */
    private function dbg(string $message, array $context = []): void
    {
        if ((string) env('FURUSATO_DEBUG_LOG', '0') !== '1') {
            return;
        }
        Log::debug($message, $context);
    }

    // NOTE: 表示・帳票の整合を崩さないため、DB保存直前に必要なミラーを強制する。
    
    private const MASTER_KIHU_YEAR = 2025;
    
    private const PERIODS = ['prev', 'curr'];

    /** @var array<int, object> */
    private array $calculators;

    /**
     * 第一表（総合課税）の「住民税列＝所得税列」ミラー（DB確定）
     *
     * - input.blade.php では表示都合でJS/ビュー側コピーがあるが、
     *   PDF/帳票は FurusatoResult.payload（DB）を参照するため、サーバ側で確定させる。
     * - “最終payload”に対して適用する（後段Calculatorに再上書きされないようにする）
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function applySogoColumnMirrors(array $payload): array
    {
        // 方針：
        // - 第一表（総合課税）で「税目共通として扱う」行は、DBでも住民税列＝所得税列に確定させる
        // - ただし、住民税固有の入力がある控除/税額系（kojo_*/tax_*）はここでは触らない
        //
        // 対象（現時点の input.blade.php の総合課税ブロックに合わせる）：
        //  - 所得：事業(営業等/農業)・不動産・利子・配当
        //  - 収入：事業(営業等/農業)・不動産・配当
        //
        // ※ kyuyo/zatsu は Calculator が両税目キーを出しているため、現状は対象外（必要なら後で追加可）
        $bases = [
            // 所得（総合課税）
            'shotoku_jigyo_eigyo',
            'shotoku_jigyo_nogyo',
            'shotoku_fudosan',
            'shotoku_rishi',
            'shotoku_haito',
            // 収入（総合課税）
            'syunyu_jigyo_eigyo',
            'syunyu_jigyo_nogyo',
            'syunyu_fudosan',
            'syunyu_haito',
        ];

        foreach (self::PERIODS as $p) {
            foreach ($bases as $base) {
                $src = sprintf('%s_shotoku_%s', $base, $p);
                $dst = sprintf('%s_jumin_%s',   $base, $p);
                if (!array_key_exists($src, $payload)) {
                    continue;
                }
                $payload[$dst] = (int) $this->toInt0($payload[$src]);
            }

            // ============================================================
            // ▼ 総合譲渡・一時：帳票が最優先で参照する合算キーをDBに必ず確定する
            //
            // 帳票側は shotoku_joto_ichiji_{shotoku|jumin}_{p} を優先参照するため、
            // ここを生成しないと “画面は合っているが帳票がズレる” が起きる。
            // ============================================================
            $tanki = (int) $this->toInt0($payload[sprintf('shotoku_joto_tanki_sogo_%s', $p)] ?? 0);
            $choki = (int) $this->toInt0($payload[sprintf('shotoku_joto_choki_sogo_%s', $p)] ?? 0);
            $ichiji = (int) $this->toInt0($payload[sprintf('shotoku_ichiji_%s', $p)] ?? 0);
            $sum = $tanki + $choki + max(0, $ichiji);

            $payload[sprintf('shotoku_joto_ichiji_shotoku_%s', $p)] = $sum;
            $payload[sprintf('shotoku_joto_ichiji_jumin_%s',  $p)] = $sum;
        }
        return $payload;
    }

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
        $this->dbg('[Recalc payload after saveDiff]', [
            'before_tsusan_tanki_ippan_prev'    => $payload['before_tsusan_tanki_ippan_prev'] ?? null,
            'after_2jitsusan_tanki_ippan_prev'  => $payload['after_2jitsusan_tanki_ippan_prev'] ?? null,
            'joto_shotoku_tanki_ippan_prev'     => $payload['joto_shotoku_tanki_ippan_prev'] ?? null,
        ]);
        $calculatorCtx = array_merge($ctx, ['syori_settings' => $syoriSettings]);
        $builtCtx = $this->buildContext($data, $calculatorCtx);
        $shouldFlashResults = $builtCtx['should_flash_results'] ?? true;
        unset($builtCtx['should_flash_results']);
        $finalPayload = $this->runCalculators($payload, $builtCtx);
        $finalPayload = $this->applySogoColumnMirrors($finalPayload);

        $this->dbg('[Recalc payload after runCalculators]', [
            'before_tsusan_tanki_ippan_prev'    => $finalPayload['before_tsusan_tanki_ippan_prev'] ?? null,
            'after_2jitsusan_tanki_ippan_prev'  => $finalPayload['after_2jitsusan_tanki_ippan_prev'] ?? null,
            'joto_shotoku_tanki_ippan_prev'     => $finalPayload['joto_shotoku_tanki_ippan_prev'] ?? null,
        ]);

        $this->dbg('[DBG human-adjusted final]', [
            'tb_sogo_jumin_prev'           => $finalPayload['tb_sogo_jumin_prev'] ?? null,
            'tb_sogo_jumin_curr'           => $finalPayload['tb_sogo_jumin_curr'] ?? null,
            'human_diff_sum_prev'          => $finalPayload['human_diff_sum_prev'] ?? null,
            'human_diff_sum_curr'          => $finalPayload['human_diff_sum_curr'] ?? null,
            'human_adjusted_taxable_prev'  => $finalPayload['human_adjusted_taxable_prev'] ?? null,
            'human_adjusted_taxable_curr'  => $finalPayload['human_adjusted_taxable_curr'] ?? null,
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
            $payload[$key] = $value;
        }

        return [$payload, $labels];
    }

    private function persistResults(Data $data, array $payload, array $ctx, ?int $userId, bool $shouldFlashResults): void
    {
        $details = $this->resultCalculator->buildDetails($payload, $ctx);

        // ▼ 追加：上限探索＋①〜④スナップショットを「再計算時に1回だけ」生成して results に同梱する
        //   - 画面表示やPDF帳票で毎回dry-runしないためのSoT
        $furusatoUpper = null;
        $furusatoUpperScenarios = null;
        try {
            /** @var FurusatoPracticalUpperLimitService $upperSvc */
            $upperSvc = app(FurusatoPracticalUpperLimitService::class);
            $furusatoUpper = $upperSvc->compute($payload, $ctx);

            $yMax = is_array($furusatoUpper) ? (int)($furusatoUpper['y_max_total'] ?? 0) : 0;
            /** @var FurusatoScenarioTaxSummaryService $scSvc */
            $scSvc = app(FurusatoScenarioTaxSummaryService::class);
            $furusatoUpperScenarios = $scSvc->build($payload, $ctx, $yMax);
        } catch (\Throwable $e) {
            Log::warning('[furusato.upper] compute failed in recalc', [
                'data_id' => (int)$data->id,
                'err' => $e->getMessage(),
            ]);
            $furusatoUpper = null;
            $furusatoUpperScenarios = null;
        }
        $results = [
            'details' => $details,
            'payload' => $payload,
            // TODO: Remove the legacy key once all consumers migrate to `payload`.
            'upper' => $payload,
            // 新規：保存済みSoT（画面・帳票はここを読む）
            'furusato_upper' => $furusatoUpper,
            'furusato_upper_scenarios' => $furusatoUpperScenarios,
            'furusato_upper_generated_at' => now()->toIso8601String(),
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

        // pipeline順はデバッグ用途のみ（通常は出さない）
        $this->dbg('[furusato.pipeline] order=[' . implode(', ', $sortedIds) . ']');

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
 
        // ============================================================
        // ▼ 不動産：土地等を取得するための負債利子（fudosan_fusairishi_*）を
        //    所得金額（fudosan_shotoku_*）から控除し、0下限でサーバ確定する。
        //
        // 方針：
        //  - クライアント（details JS）で作られた fudosan_shotoku_* は信用しない
        //  - 収入/経費/専従者/青色控除からサーバで再計算し、そこから fusairishi を差し引く
        //  - 以降の alias/netting/sums/tb_* はこの確定値のみを参照する
        // ============================================================
        $payload = $this->applyFudosanFusairishiAdjustment($payload);
        /** @var DetailsSourceAliasCalculator $detailsAliasCalculator */
        $detailsAliasCalculator = app(DetailsSourceAliasCalculator::class);
        $payload = array_replace(
            $payload,
            $detailsAliasCalculator->compute($payload, 'prev'),
            $detailsAliasCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $detailsAliasCalculator);

        // ============================================================
        // ▼ 第一表（総合課税）の「住民税列＝所得税列」ミラーをDBへ確定（PDFズレ防止）
        //
        // 背景：
        //  - input.blade.php は表示都合でJS/ビュー側で「住民税=所得税」に見せている
        //  - しかしPDFは FurusatoResult.payload（DB）を参照するため、DB側でも確定が必要
        //
        // 方針：
        //  - details（内訳）で入力される/表示が税目共通の “総合課税” 系は、jumin を shotoku に揃える
        //  - 住民税側だけ編集可能な控除/税額系（tax_* / kojo_* など）はここでは触らない
        // ============================================================
        // NOTE: ミラーは handle() の最終 payload にのみ適用する（上書き順を明確化するため）

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

        $this->dbg('[applyAutoCalculatedFields after BunriNetting(final)]', [
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

        $this->dbg('[applyAutoCalculatedFields final snapshot]', [
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
 
     /**
     * 不動産所得のサーバ確定：
     *   fudosan_shotoku_pure = (収入 − 必要経費合計 − 専従者給与 − 青色申告特別控除額)
     *   fudosan_shotoku      = max(0, fudosan_shotoku_pure − 土地等取得の負債利子)
     *
     * - POSTされてきた fudosan_shotoku_* は採用しない（改ざん/ズレ防止）
     * - fudosan_shunyu_* / fudosan_syunyu_* の揺れは吸収
     */
    private function applyFudosanFusairishiAdjustment(array $payload): array
    {
        foreach (['prev', 'curr'] as $p) {
            // 収入（揺れ吸収）
            $shunyu = $this->toInt0($payload["fudosan_syunyu_{$p}"] ?? ($payload["fudosan_shunyu_{$p}"] ?? null));
 
            // 必要経費合計（1..7 + sonota）
            $keihi = 0;
            for ($i = 1; $i <= 7; $i++) {
                $keihi += $this->toInt0($payload["fudosan_keihi_{$i}_{$p}"] ?? null);
            }
            $keihi += $this->toInt0($payload["fudosan_keihi_sonota_{$p}"] ?? null);
 
            $senju = $this->toInt0($payload["fudosan_senjuusha_kyuyo_{$p}"] ?? null);
            $aoi   = $this->toInt0($payload["fudosan_aoi_tokubetsu_kojo_gaku_{$p}"] ?? null);
 
            $fusairishi = $this->toInt0($payload["fudosan_fusairishi_{$p}"] ?? null);
 
             // ▼ 仕様：
             //   base = 収入 − 必要経費 − 専従者給与
             //   - base > 0 のときのみ「青色控除」「負債利子」を差し引ける（0下限）
             //   - base <= 0 のときは差し引けない（所得は base のまま＝マイナス可）
             $base = $shunyu - $keihi - $senju;
 
             if ($base > 0) {
                 // 0を下回らない範囲でだけ控除する
                 $afterAoi = max(0, $base - $aoi);
                 $adjusted = max(0, $afterAoi - $fusairishi);
             } else {
                 $adjusted = $base;
             }
 
            // ★ SoT 上書き
            $payload["fudosan_shotoku_{$p}"] = $adjusted;
        }
 
        return $payload;
    }
 
    private function toInt0(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));
            if ($value === '' || $value === '－' || $value === '-') {
                return 0;
            }
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || is_numeric($value)) {
            return (int) floor((float) $value);
        }
        return 0;
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