<?php

namespace App\Application\UseCases\Tax;

use App\Domain\Tax\Support\PayloadNormalizer;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoSyoriSetting;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\FurusatoMasterService;
use App\Services\Tax\Kojo\HaigushaKojoService;
use App\Services\Tax\Kojo\JintekiKojoService;
use App\Services\Tax\Kojo\KifukinShotokuKojoService;
use App\Services\Tax\Kojo\KihonService;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
use App\Services\Tax\Result\Rate\TokureiRateService;
use Illuminate\Support\Facades\Log;

class RecalculateFurusatoPayload
{
    private const MASTER_KIHU_YEAR = 2025;

    private const KOJO_FIELD_OVERRIDES = [
        'kojo_kiso' => [
            'shotoku' => 'shotokuzei_kojo_kiso_%s',
            'jumin' => 'juminzei_kojo_kiso_%s',
        ],
        'kojo_kifukin' => [
            'shotoku' => 'shotokuzei_kojo_kifukin_%s',
            'jumin' => 'juminzei_kojo_kifukin_%s',
        ],
        'kojo_shogaisha' => [
            'shotoku' => 'kojo_shogaisyo_shotoku_%s',
            'jumin' => 'kojo_shogaisyo_jumin_%s',
        ],
    ];

    /** @var array<int, object> */
    private array $calculators;

    public function __construct(
        private readonly PayloadNormalizer $normalizer,
        iterable $calculators
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

        $payload = $this->saveDiff($data, $payloadUpdates, $labelUpdates, $userId);
        $finalPayload = $this->runCalculators($payload, $this->buildContext($data, $ctx));

        $this->persistFinalPayload($data, $finalPayload, $userId);

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

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>  $labelUpdates
     * @return array<string, mixed>
     */
    private function saveDiff(Data $data, array $updates, array $labelUpdates, ?int $userId): array
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

            $kihuYear = self::MASTER_KIHU_YEAR;
            $companyId = $data->company_id !== null ? (int) $data->company_id : null;
            $merged = array_replace($merged, $this->computeTokureiPercentBundle($merged, $kihuYear, $companyId));

            $settings = $this->getSyoriSettings($data->id);
            $payload = $this->applyAutoCalculatedFields($data, $merged, $settings);

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

        return $ctx;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeTokureiPercentBundle(array $basePayload, int $kihuYear, ?int $companyId): array
    {
        $result = [
            'tokurei_rate_sanrin_div5_prev' => null,
            'tokurei_rate_sanrin_div5_curr' => null,
            'tokurei_rate_taishoku_prev' => null,
            'tokurei_rate_taishoku_curr' => null,
            'tokurei_rate_adopted_prev' => null,
            'tokurei_rate_adopted_curr' => null,
            'tokurei_rate_bunri_min_prev' => null,
            'tokurei_rate_bunri_min_curr' => null,
            'tokurei_rate_final_prev' => null,
            'tokurei_rate_final_curr' => null,
        ];

        foreach (['prev', 'curr'] as $period) {
            $sanrinKey = sprintf('bunri_kazeishotoku_sanrin_shotoku_%s', $period);
            $sanrinRaw = (int) ($basePayload[$sanrinKey] ?? 0);
            if ($sanrinRaw > 0) {
                $div5 = $this->floorToThousands($sanrinRaw / 5);
                if ($div5 > 0) {
                    $rate = $this->tokureiPercentForAmount($kihuYear, $companyId, $div5);
                    $result[sprintf('tokurei_rate_sanrin_div5_%s', $period)] = $rate;
                }
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $taishokuKey = sprintf('bunri_kazeishotoku_taishoku_shotoku_%s', $period);
            $taishokuRaw = (int) ($basePayload[$taishokuKey] ?? 0);
            if ($taishokuRaw > 0) {
                $amount = $this->floorToThousands($taishokuRaw);
                if ($amount > 0) {
                    $rate = $this->tokureiPercentForAmount($kihuYear, $companyId, $amount);
                    $result[sprintf('tokurei_rate_taishoku_%s', $period)] = $rate;
                }
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $a = $result[sprintf('tokurei_rate_sanrin_div5_%s', $period)];
            $b = $result[sprintf('tokurei_rate_taishoku_%s', $period)];
            if ($a !== null || $b !== null) {
                $result[sprintf('tokurei_rate_adopted_%s', $period)] = min($a ?? 100.000, $b ?? 100.000);
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $short = (int) ($basePayload[sprintf('bunri_kazeishotoku_tanki_shotoku_%s', $period)] ?? 0);
            $long = (int) ($basePayload[sprintf('bunri_kazeishotoku_choki_shotoku_%s', $period)] ?? 0);
            $haito = (int) ($basePayload[sprintf('bunri_kazeishotoku_haito_shotoku_%s', $period)] ?? 0);
            $joto = (int) ($basePayload[sprintf('bunri_kazeishotoku_joto_shotoku_%s', $period)] ?? 0);
            $key = sprintf('tokurei_rate_bunri_min_%s', $period);
            if ($short > 0) {
                $result[$key] = 59.370;
            } elseif ($long > 0 || $haito > 0 || $joto > 0) {
                $result[$key] = 74.685;
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $values = array_filter([
                $result[sprintf('tokurei_rate_adopted_%s', $period)],
                $result[sprintf('tokurei_rate_bunri_min_%s', $period)],
            ], static fn ($value) => $value !== null);

            if ($values !== []) {
                $result[sprintf('tokurei_rate_final_%s', $period)] = min($values);
            }
        }

        return $result;
    }

    private function floorToThousands(mixed $value): int
    {
        $v = (int) floor((float) ($value ?? 0));
        if ($v <= 0) {
            return 0;
        }

        return $v - ($v % 1000);
    }

    private function tokureiPercentForAmount(int $kihuYear, ?int $companyId, int $amount): ?float
    {
        /** @var TokureiRateService $svc */
        $svc = app(TokureiRateService::class);
        if ($amount <= 0) {
            return null;
        }

        $rows = $svc->getRows($kihuYear, $companyId);
        $rate = $svc->lowerBoundRate($amount, $rows);

        return $rate !== null ? round($rate * 100, 3) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function applyAutoCalculatedFields(Data $data, array $payload, array $settings): array
    {
        $taxTypes = ['shotoku', 'jumin'];
        $periods = ['prev', 'curr'];
        $kojoShokeiBases = [
            'kojo_shakaihoken',
            'kojo_shokibo',
            'kojo_seimei',
            'kojo_jishin',
            'kojo_kafu',
            'kojo_hitorioya',
            'kojo_kinrogakusei',
            'kojo_shogaisha',
            'kojo_haigusha',
            'kojo_haigusha_tokubetsu',
            'kojo_fuyo',
            'kojo_tokutei_shinzoku',
            'kojo_kiso',
        ];
        $kojoGokeiExtras = ['kojo_zasson', 'kojo_iryo', 'kojo_kifukin'];

        /** @var FurusatoMasterService $masterService */
        $masterService = app(FurusatoMasterService::class);
        $companyId = $data->company_id !== null ? (int) $data->company_id : null;
        $shotokuRates = $masterService
            ->getShotokuRates(self::MASTER_KIHU_YEAR, $companyId)
            ->all();

        /** @var KifukinShotokuKojoService $kifukinService */
        $kifukinService = app(KifukinShotokuKojoService::class);
        $payload = array_replace($payload, $kifukinService->compute($payload, $settings));
        $this->assertProvidedKeys($payload, $kifukinService);

        /** @var KihonService $kihonService */
        $kihonService = app(KihonService::class);
        $payload = array_replace($payload, $kihonService->computeKisoKojo($payload, (int) ($data->kihu_year ?? 0)));
        $this->assertProvidedKeys($payload, $kihonService);

        /** @var JintekiKojoService $jintekiService */
        $jintekiService = app(JintekiKojoService::class);
        $payload = array_replace(
            $payload,
            $jintekiService->compute($payload, (int) ($data->kihu_year ?? 0))
        );
        $this->assertProvidedKeys($payload, $jintekiService);

        /** @var HaigushaKojoService $haigushaService */
        $haigushaService = app(HaigushaKojoService::class);
        $payload = array_replace($payload, $haigushaService->compute($payload));
        $this->assertProvidedKeys($payload, $haigushaService);

        foreach ($taxTypes as $tax) {
            foreach ($periods as $period) {
                $shokei = 0;
                foreach ($kojoShokeiBases as $base) {
                    $key = $this->formatKojoFieldName($base, $tax, $period);
                    $shokei += $this->valueOrZero($this->toNullableInt($payload[$key] ?? null));
                }
                $payload[sprintf('kojo_shokei_%s_%s', $tax, $period)] = $shokei;

                $gokei = $shokei;
                foreach ($kojoGokeiExtras as $base) {
                    $key = $this->formatKojoFieldName($base, $tax, $period);
                    $gokei += $this->valueOrZero($this->toNullableInt($payload[$key] ?? null));
                }
                $payload[sprintf('kojo_gokei_%s_%s', $tax, $period)] = $gokei;
            }
        }

        foreach ($periods as $period) {
            $shotokuKey = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            $shotokuAmount = $this->valueOrZero($this->toNullableInt($payload[$shotokuKey] ?? null));
            $payload[sprintf('tax_zeigaku_shotoku_%s', $period)] = $this->calculateShotokuTaxAmount($shotokuRates, $shotokuAmount);

            $juminKey = sprintf('tax_kazeishotoku_jumin_%s', $period);
            $juminAmount = max(0, $this->valueOrZero($this->toNullableInt($payload[$juminKey] ?? null)));
            $payload[sprintf('tax_zeigaku_jumin_%s', $period)] = (int) ($juminAmount * 0.1);
        }

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

    private function formatKojoFieldName(string $base, string $tax, string $period): string
    {
        $override = self::KOJO_FIELD_OVERRIDES[$base][$tax] ?? null;

        if ($override) {
            return sprintf($override, $period);
        }

        return sprintf('%s_%s_%s', $base, $tax, $period);
    }

    private function calculateShotokuTaxAmount(array $rates, int $taxable): int
    {
        $amount = max(0, $taxable);

        foreach ($rates as $rate) {
            $lower = (int) ($rate['lower'] ?? 0);
            $upper = array_key_exists('upper', $rate) ? $rate['upper'] : null;

            if ($amount < $lower) {
                continue;
            }

            if ($upper !== null && $amount > $upper) {
                continue;
            }

            $rateDecimal = (float) ($rate['rate'] ?? 0) / 100;
            $deduction = (int) ($rate['deduction_amount'] ?? 0);
            $value = $amount * $rateDecimal - $deduction;

            return (int) $value;
        }

        return 0;
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

    private function valueOrZero(?int $value): int
    {
        return $value ?? 0;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }
}