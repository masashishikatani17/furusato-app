<?php

namespace App\Application\UseCases\Tax;

use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Models\FurusatoSyoriSetting;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
use Illuminate\Support\Facades\Log;

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
     * @param  array<string, mixed>  $ctx
     * @return array{payload: array<string, mixed>}
     */
    public function handle(Data $data, array $inputDiff, array $ctx = []): array
    {
        $normalizedDiff = $this->normalizer->normalize($inputDiff);
        [$payloadUpdates, $labelUpdates] = $this->partitionUpdates($normalizedDiff);
        $userId = isset($ctx['user_id']) ? (int) $ctx['user_id'] : null;

        $syoriSettings = $this->getSyoriSettings($data->id);

        $payload = $this->saveDiff($data, $payloadUpdates, $labelUpdates, $syoriSettings, $userId);
        $calculatorCtx = array_merge($ctx, ['syori_settings' => $syoriSettings]);
        $builtCtx = $this->buildContext($data, $calculatorCtx);
        $finalPayload = $this->runCalculators($payload, $builtCtx);

        $this->persistFinalPayload($data, $finalPayload, $userId);

        $this->persistResults($data, $finalPayload, $builtCtx, $userId);

        session()->flash('show_furusato_result', true);

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

    private function persistResults(Data $data, array $payload, array $ctx, ?int $userId): void
    {
        $details = $this->resultCalculator->buildDetails($payload, $ctx);
        $results = [
            'details' => $details,
            'upper' => $payload,
        ];

        $this->storeResults($data, $results, $userId);

        session()->flash('furusato_results', $results);
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
     * @return array<string, mixed>
     */
    private function saveDiff(Data $data, array $updates, array $labelUpdates, array $syoriSettings, ?int $userId): array
    {
        $payload = [];

        FurusatoInput::unguarded(function () use (&$payload, $data, $updates, $labelUpdates, $userId): void {
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

            $payload = $this->applyAutoCalculatedFields($data, $merged, $syoriSettings);

            $record->payload = $payload;
            $record->updated_by = $userId ?: null;
            $record->save();

            $payload = is_array($record->payload) ? $record->payload : [];
        });

        return $payload;
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

            $nodes[$id] = [
                'calculator' => $calculator,
                'order' => (int) $order,
                'before' => array_values(array_filter($before, 'is_string')),
                'after' => array_values(array_filter($after, 'is_string')),
            ];
        }

        $edges = [];
        $inDegree = [];
        foreach ($nodes as $id => $meta) {
            $edges[$id] = [];
            $inDegree[$id] = 0;
        }

        foreach ($nodes as $id => $meta) {
            foreach ($meta['after'] as $afterId) {
                if (! isset($nodes[$afterId])) {
                    continue;
                }

                $edges[$afterId][] = $id;
                $inDegree[$id]++;
            }

            foreach ($meta['before'] as $beforeId) {
                if (! isset($nodes[$beforeId])) {
                    continue;
                }

                $edges[$id][] = $beforeId;
                $inDegree[$beforeId]++;
            }
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
            if (config('app.debug')) {
                Log::warning('RecalculateFurusatoPayload: calculator ordering contains a cycle.');
            }

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
        unset($data, $settings);
        
        /** @var SeitotoKihukinTokubetsuService $seitotoService */
        $seitotoService = app(SeitotoKihukinTokubetsuService::class);
        $payload = array_replace($payload, $seitotoService->compute($payload));
        $this->assertProvidedKeys($payload, $seitotoService);

        return $payload;
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

        return is_array($payload) ? $payload : [];
    }
}