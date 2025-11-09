<?php

namespace App\Application\UseCases\Tax;

use App\Domain\Tax\Calculators\DetailsSourceAliasCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use App\Domain\Tax\Calculators\ResultToDetailsAliasCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\SakimonoCalculator;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Models\FurusatoSyoriSetting;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
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
        $calculatorCtx = array_merge($ctx, ['syori_settings' => $syoriSettings]);
        $builtCtx = $this->buildContext($data, $calculatorCtx);
        $shouldFlashResults = $builtCtx['should_flash_results'] ?? true;
        unset($builtCtx['should_flash_results']);
        $finalPayload = $this->runCalculators($payload, $builtCtx);

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

            $payload[$key] = $value;
        }

        return [$payload, $labels];
    }

    private function persistResults(Data $data, array $payload, array $ctx, ?int $userId, bool $shouldFlashResults): void
    {
        $details = $this->resultCalculator->buildDetails($payload, $ctx);
        $results = [
            'details' => $details,
            'payload' => $payload,
            // TODO: Remove the legacy key once all consumers migrate to `payload`.
            'upper' => $payload,
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

            $syoriSettings = $this->getSyoriSettings($data->id);

            $payload = $this->applyAutoCalculatedFields($data, $merged, $syoriSettings);

            $record->payload = $payload;
            $record->updated_by = $userId ?: null;
            $record->save();

            $payload = is_array($record->payload) ? $record->payload : [];
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
        $ctx['data'] = $data;
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
        
        /** @var SeitotoKihukinTokubetsuService $seitotoService */
        $seitotoService = app(SeitotoKihukinTokubetsuService::class);
        $payload = array_replace($payload, $seitotoService->compute($payload));
        $this->assertProvidedKeys($payload, $seitotoService);

        /** @var DetailsSourceAliasCalculator $detailsAliasCalculator */
        $detailsAliasCalculator = app(DetailsSourceAliasCalculator::class);
        $payload = array_replace(
            $payload,
            $detailsAliasCalculator->compute($payload, 'prev'),
            $detailsAliasCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $detailsAliasCalculator);
 
        /** @var SakimonoCalculator $sakimonoCalculator */
        $sakimonoCalculator = app(SakimonoCalculator::class);
        $payload = array_replace(
            $payload,
            $sakimonoCalculator->compute($payload, 'prev'),
            $sakimonoCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $sakimonoCalculator);

        /** @var BunriNettingCalculator $bunriNettingCalculator */
        $bunriNettingCalculator = app(BunriNettingCalculator::class);
        $payload = array_replace(
            $payload,
            $bunriNettingCalculator->compute($payload, 'prev'),
            $bunriNettingCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $bunriNettingCalculator);

        /** @var BunriKabutekiNettingCalculator $bunriKabutekiNettingCalculator */
        $bunriKabutekiNettingCalculator = app(BunriKabutekiNettingCalculator::class);
        $payload = array_replace(
            $payload,
            $bunriKabutekiNettingCalculator->compute($payload, 'prev'),
            $bunriKabutekiNettingCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $bunriKabutekiNettingCalculator);

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

    /**
     * @return array<string, mixed>
     */
    private function getSyoriSettings(int $dataId): array
    {
        $payload = FurusatoSyoriSetting::query()
            ->where('data_id', $dataId)
            ->value('payload');

        $default = $this->syoriDefaultPayload();

        if (is_array($payload)) {
            $payload = array_replace($default, array_intersect_key($payload, $default));
        } else {
            $payload = $default;
        }

        return $this->applyStandardRates($payload);
    }

    private function syoriDefaultPayload(): array
    {
        return [
            'detail_mode_prev' => 1,
            'detail_mode_curr' => 1,
            'bunri_flag_prev' => 0,
            'bunri_flag_curr' => 0,
            'one_stop_flag_prev' => 1,
            'one_stop_flag_curr' => 1,
            'shitei_toshi_flag_prev' => 0,
            'shitei_toshi_flag_curr' => 0,
            'pref_standard_rate' => 0.04,
            'muni_standard_rate' => 0.06,
            'pref_applied_rate_prev' => 0.04,
            'pref_applied_rate_curr' => 0.04,
            'muni_applied_rate_prev' => 0.06,
            'muni_applied_rate_curr' => 0.06,
            'pref_equal_share_prev' => 1500,
            'pref_equal_share_curr' => 1500,
            'muni_equal_share_prev' => 3500,
            'muni_equal_share_curr' => 3500,
            'other_taxes_amount_prev' => 0,
            'other_taxes_amount_curr' => 0,
            // Legacy keys for backward compatibility
            'detail_mode' => 1,
            'bunri_flag' => 0,
            'one_stop_flag' => 1,
            'shitei_toshi_flag' => 0,
            'pref_applied_rate' => 0.04,
            'muni_applied_rate' => 0.06,
            'pref_equal_share' => 1500,
            'muni_equal_share' => 3500,
            'other_taxes_amount' => 0,
        ];
    }

    private function applyStandardRates(array $payload): array
    {
        $detailPrev = (int) ($payload['detail_mode_prev'] ?? $payload['detail_mode'] ?? 1);
        $detailCurr = (int) ($payload['detail_mode_curr'] ?? $payload['detail_mode'] ?? $detailPrev);

        $bunriPrev = (int) ($payload['bunri_flag_prev'] ?? $payload['bunri_flag'] ?? 0);
        $bunriCurr = (int) ($payload['bunri_flag_curr'] ?? $payload['bunri_flag'] ?? $bunriPrev);

        $oneStopPrev = (int) ($payload['one_stop_flag_prev'] ?? $payload['one_stop_flag'] ?? 1);
        $oneStopCurr = (int) ($payload['one_stop_flag_curr'] ?? $payload['one_stop_flag'] ?? $oneStopPrev);

        $shiteiPrev = (int) ($payload['shitei_toshi_flag_prev'] ?? $payload['shitei_toshi_flag'] ?? 0);
        $shiteiCurr = (int) ($payload['shitei_toshi_flag_curr'] ?? $payload['shitei_toshi_flag'] ?? $shiteiPrev);
        $shiteiForStandard = $shiteiCurr;

        if ($shiteiForStandard === 1) {
            $prefStandard = 0.02;
            $muniStandard = 0.08;
        } else {
            $prefStandard = 0.04;
            $muniStandard = 0.06;
        }

        $prefAppliedPrev = $payload['pref_applied_rate_prev'] ?? $payload['pref_applied_rate'] ?? null;
        if ($prefAppliedPrev === null) {
            $prefAppliedPrev = $prefStandard;
        }

        $prefAppliedCurr = $payload['pref_applied_rate_curr'] ?? $payload['pref_applied_rate'] ?? null;
        if ($prefAppliedCurr === null) {
            $prefAppliedCurr = $prefAppliedPrev;
        }

        $muniAppliedPrev = $payload['muni_applied_rate_prev'] ?? $payload['muni_applied_rate'] ?? null;
        if ($muniAppliedPrev === null) {
            $muniAppliedPrev = $muniStandard;
        }

        $muniAppliedCurr = $payload['muni_applied_rate_curr'] ?? $payload['muni_applied_rate'] ?? null;
        if ($muniAppliedCurr === null) {
            $muniAppliedCurr = $muniAppliedPrev;
        }

        $prefEqualPrev = (int) ($payload['pref_equal_share_prev'] ?? $payload['pref_equal_share'] ?? 1500);
        $prefEqualCurr = (int) ($payload['pref_equal_share_curr'] ?? $payload['pref_equal_share'] ?? $prefEqualPrev);

        $muniEqualPrev = (int) ($payload['muni_equal_share_prev'] ?? $payload['muni_equal_share'] ?? 3500);
        $muniEqualCurr = (int) ($payload['muni_equal_share_curr'] ?? $payload['muni_equal_share'] ?? $muniEqualPrev);

        $otherTaxesPrev = (int) ($payload['other_taxes_amount_prev'] ?? $payload['other_taxes_amount'] ?? 0);
        $otherTaxesCurr = (int) ($payload['other_taxes_amount_curr'] ?? $payload['other_taxes_amount'] ?? $otherTaxesPrev);

        $payload['pref_standard_rate'] = (float) $prefStandard;
        $payload['muni_standard_rate'] = (float) $muniStandard;

        $payload['detail_mode_prev'] = $detailPrev;
        $payload['detail_mode_curr'] = $detailCurr;
        $payload['detail_mode'] = $detailPrev;

        $payload['bunri_flag_prev'] = $bunriPrev;
        $payload['bunri_flag_curr'] = $bunriCurr;
        $payload['bunri_flag'] = $bunriPrev;

        $payload['one_stop_flag_prev'] = $oneStopPrev;
        $payload['one_stop_flag_curr'] = $oneStopCurr;
        $payload['one_stop_flag'] = $oneStopPrev;

        $payload['shitei_toshi_flag_prev'] = $shiteiPrev;
        $payload['shitei_toshi_flag_curr'] = $shiteiCurr;
        $payload['shitei_toshi_flag'] = $shiteiPrev;

        $payload['pref_applied_rate_prev'] = (float) $prefAppliedPrev;
        $payload['pref_applied_rate_curr'] = (float) $prefAppliedCurr;
        $payload['pref_applied_rate'] = (float) $prefAppliedPrev;

        $payload['muni_applied_rate_prev'] = (float) $muniAppliedPrev;
        $payload['muni_applied_rate_curr'] = (float) $muniAppliedCurr;
        $payload['muni_applied_rate'] = (float) $muniAppliedPrev;

        $payload['pref_equal_share_prev'] = $prefEqualPrev;
        $payload['pref_equal_share_curr'] = $prefEqualCurr;
        $payload['pref_equal_share'] = $prefEqualPrev;

        $payload['muni_equal_share_prev'] = $muniEqualPrev;
        $payload['muni_equal_share_curr'] = $muniEqualCurr;
        $payload['muni_equal_share'] = $muniEqualPrev;

        $payload['other_taxes_amount_prev'] = $otherTaxesPrev;
        $payload['other_taxes_amount_curr'] = $otherTaxesCurr;
        $payload['other_taxes_amount'] = $otherTaxesPrev;

        return $payload;
    }
}