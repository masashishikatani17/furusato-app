<?php

namespace App\Http\Controllers\Tax;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\Support\FurusatoMasterSheet;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\FurusatoInputRequest;
use App\Http\Requests\Tax\FurusatoSyoriRequest;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Models\FurusatoSyoriSetting;
use App\Services\Tax\FurusatoMasterService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use DateTimeInterface;

final class FurusatoController extends Controller
{
    private const MASTER_KIHU_YEAR = 2025;

    private const BUNRI_CHOKI_SHOTOKU_FIELDS = [
        'bunri_shotoku_choki_tokutei_shotoku_prev',
        'bunri_shotoku_choki_tokutei_shotoku_curr',
        'bunri_shotoku_choki_keika_shotoku_prev',
        'bunri_shotoku_choki_keika_shotoku_curr',
    ];

    private const FUDOSAN_LABEL_FIELDS = [
        'fudosan_keihi_label_01',
        'fudosan_keihi_label_02',
        'fudosan_keihi_label_03',
        'fudosan_keihi_label_04',
        'fudosan_keihi_label_05',
        'fudosan_keihi_label_06',
        'fudosan_keihi_label_07',
    ];

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

    private const JIGYO_EIGYO_LABEL_FIELDS = [
        'jigyo_eigyo_keihi_label_01',
        'jigyo_eigyo_keihi_label_02',
        'jigyo_eigyo_keihi_label_03',
        'jigyo_eigyo_keihi_label_04',
        'jigyo_eigyo_keihi_label_05',
        'jigyo_eigyo_keihi_label_06',
        'jigyo_eigyo_keihi_label_07',
    ];

    private const JINTEKI_DIFF_MAP = [
        'kafu' => [
            'shotoku' => 'kojo_kafu_shotoku',
            'jumin' => 'kojo_kafu_jumin',
        ],
        'hitorioya' => [
            'shotoku' => 'kojo_hitorioya_shotoku',
            'jumin' => 'kojo_hitorioya_jumin',
        ],
        'kinrogakusei' => [
            'shotoku' => 'kojo_kinrogakusei_shotoku',
            'jumin' => 'kojo_kinrogakusei_jumin',
        ],
        'shogaisyo' => [
            'shotoku' => 'kojo_shogaisyo_shotoku',
            'jumin' => 'kojo_shogaisyo_jumin',
        ],
        'haigusha' => [
            'shotoku' => 'kojo_haigusha_shotoku',
            'jumin' => 'kojo_haigusha_jumin',
        ],
        'haigusha_tokubetsu' => [
            'shotoku' => 'kojo_haigusha_tokubetsu_shotoku',
            'jumin' => 'kojo_haigusha_tokubetsu_jumin',
        ],
        'fuyo' => [
            'shotoku' => 'kojo_fuyo_shotoku',
            'jumin' => 'kojo_fuyo_jumin',
        ],
        'tokutei_shinzoku' => [
            'shotoku' => 'kojo_tokutei_shinzoku_shotoku',
            'jumin' => 'kojo_tokutei_shinzoku_jumin',
        ],
        'kiso' => [
            'shotoku' => 'shotokuzei_kojo_kiso',
            'jumin' => 'juminzei_kojo_kiso',
        ],
    ];

    private const BUNRI_PLACEHOLDER_MESSAGE = 'この内訳画面は準備中です。必要な情報が確定次第、入力欄を追加します。';

    public function index(Request $req)
    {
        $dataId = $req->integer('data_id') ?: null;
        if ($dataId) {
            session(['selected_data_id' => $dataId]);
        }

        $context = $this->makeInputContext($req, $dataId);
        $inputsForView = $context['outInputs'] ?? $context['savedInputs'];
        $context['out'] = ['inputs' => $inputsForView];
        unset($context['outInputs']);

        $session = session();
        if ($session->has('furusato_results')) {
            $context['results'] = (array) $session->get('furusato_results');
        } elseif ($dataId) {
            $context['results'] = $this->getStoredFurusatoResults($dataId);
        }

        if ($session->has('show_furusato_result')) {
            $context['showResult'] = (bool) $session->get('show_furusato_result');
        } else {
            $context['showResult'] = $context['results'] !== [];
        }
        unset($context['savedInputs']);

        return view('tax.furusato.input', $context);
    }

    public function calc(FurusatoInputRequest $req, FurusatoCalcService $svc)
    {
        $validated = $req->validated();
        $dataId = $validated['data_id'] ?? null;

        $dto = $req->toDto();
        $out = [
            'inputs' => $dto->toArray(),
        ];

        if ($req->wantsJson()) {
            return response()->json($out);
        }

        session()->flash('_old_input', $req->except(['_token']));
        $context = $this->makeInputContext($req, $dataId);
        $baseInputs = $context['outInputs'] ?? $context['savedInputs'];
        $context['out'] = ['inputs' => array_replace($baseInputs, $dto->toArray())];
        unset($context['outInputs']);
        unset($context['savedInputs']);
        
        return view('tax.furusato.input', $context);
    }

    private function resolveBunriFlag(?int $dataId): int
    {
        if (! $dataId) {
            return 0;
        }

        $payload = FurusatoSyoriSetting::query()
            ->where('data_id', $dataId)
            ->value('payload');

        if (! is_array($payload)) {
            return 0;
        }

        $flag = $payload['bunri_flag'] ?? 0;

        return (int) ($flag ? 1 : 0);
    }

    private function makeInputContext(Request $request, ?int $dataId): array
    {
        $bunriFlag = 0;
        $kihuYear = null;
        $warekiPrev = null;
        $warekiCurr = null;
        $savedInputs = [];
        $data = null;
        $syoriSettings = [];
        $showSeparatedNetting = false;

        if ($dataId) {
            $data = $this->findDataForInput($request, $dataId);

            $syoriSettings = $this->getSyoriSettings($dataId);
            $prevOn = (int) ($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;
            $currOn = (int) ($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;
            $showSeparatedNetting = $prevOn || $currOn;

            $bunriFlag = $this->resolveBunriFlag($dataId);

            if ($data && $data->kihu_year) {
                $kihuYear = (int) $data->kihu_year;
                $warekiPrev = $this->toWarekiYear($kihuYear - 1);
                $warekiCurr = $this->toWarekiYear($kihuYear);
            }

            $stored = FurusatoInput::query()
                ->where('data_id', $dataId)
                ->value('payload');

            if (is_array($stored)) {
                $savedInputs = $stored;
                $this->normalizeJotoIchijiKeys($savedInputs);
                $this->normalizeFudosanSyunyuKeys($savedInputs);
                $this->normalizeBunriChokiSyunyuKeys($savedInputs);
                $this->normalizeBunriChokiShotokuKeys($savedInputs);
                $this->normalizeBunriIncomeShotokuKeys($savedInputs);
                $this->normalizeKojoRenamedKeys($savedInputs);
            }
        }

        $companyId = $request->user()?->company_id;
        if ($companyId === null && $data) {
            $companyId = $data->company_id;
        }
        $companyId = $companyId !== null ? (int) $companyId : null;

        $shotokuRates = app(FurusatoMasterService::class)
            ->getShotokuRates(self::MASTER_KIHU_YEAR, $companyId);

        $jintekiDiff = $this->computeJintekiDiff($savedInputs);

        $periods = ['prev', 'curr'];
        $humanAdjusted = [];
        $humanAdjustedDisplay = [];
        foreach ($periods as $period) {
            $taxableBase  = $this->resolveTaxableBase($savedInputs, $syoriSettings, $period);
            $humanDiffSum = (int) ($jintekiDiff['sum'][$period] ?? 0);

            $raw = $taxableBase - $humanDiffSum;
            $humanAdjusted[$period] = $raw;
            $humanAdjustedDisplay[$period] = $this->floorToThousands(max(0, $raw));
        }
        $jintekiDiff['adjusted_taxable'] = [
            'prev' => $humanAdjustedDisplay['prev'],
            'curr' => $humanAdjustedDisplay['curr'],
        ];

        $calculatorYear = (int) ($kihuYear ?? self::MASTER_KIHU_YEAR);
        $calculatorCtx = [
            'master_kihu_year' => self::MASTER_KIHU_YEAR,
            'kihu_year' => $calculatorYear,
            'company_id' => $companyId,
            'syori_settings' => $syoriSettings,
        ];

        $sanrinBasePrev = $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_sanrin_shotoku_prev'] ?? null));
        $sanrinBaseCurr = $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_sanrin_shotoku_curr'] ?? null));
        $taishokuBasePrev = $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_taishoku_shotoku_prev'] ?? null));
        $taishokuBaseCurr = $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_taishoku_shotoku_curr'] ?? null));

        $hasSanrinPrev = $sanrinBasePrev > 0;
        $hasSanrinCurr = $sanrinBaseCurr > 0;
        $hasTaishokuPrev = $taishokuBasePrev > 0;
        $hasTaishokuCurr = $taishokuBaseCurr > 0;

        $hasBunriPrev = $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_tanki_shotoku_prev'] ?? null)) > 0
            || $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_choki_shotoku_prev'] ?? null)) > 0
            || $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_haito_shotoku_prev'] ?? null)) > 0
            || $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_joto_shotoku_prev'] ?? null)) > 0;
        $hasBunriCurr = $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_tanki_shotoku_curr'] ?? null)) > 0
            || $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_choki_shotoku_curr'] ?? null)) > 0
            || $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_haito_shotoku_curr'] ?? null)) > 0
            || $this->valueOrZero($this->toNullableInt($savedInputs['bunri_kazeishotoku_joto_shotoku_curr'] ?? null)) > 0;

        $previewPayload = array_replace($savedInputs, [
            'human_adjusted_taxable_prev' => $humanAdjusted['prev'],
            'human_adjusted_taxable_curr' => $humanAdjusted['curr'],
        ]);

        /** @var TokureiRateCalculator $tokureiCalculator */
        $tokureiCalculator = app(TokureiRateCalculator::class);
        $previewPayload = $tokureiCalculator->compute($previewPayload, $calculatorCtx);

        /** @var BunriSeparatedMinRateCalculator $bunriMinCalculator */
        $bunriMinCalculator = app(BunriSeparatedMinRateCalculator::class);
        $previewPayload = $bunriMinCalculator->compute($previewPayload, $calculatorCtx);

        $floorToThousands = static function (int $value): int {
            return $value > 0 ? intdiv($value, 1000) * 1000 : 0;
        };

        foreach (['prev', 'curr'] as $period) {
            $isSeparated = (int) ($syoriSettings["bunri_flag_{$period}"] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;

            if ($isSeparated) {
                $bunriBaseShotoku = (int) ($previewPayload["bunri_kazeishotoku_sogo_shotoku_{$period}"] ?? 0);
                $previewPayload["tax_kazeishotoku_shotoku_{$period}"] = $floorToThousands($bunriBaseShotoku);

                $kazeiSogoJumin = (int) ($previewPayload["kazeisoushotoku_{$period}"] ?? 0);
                $previewPayload["tax_kazeishotoku_jumin_{$period}"] = $floorToThousands($kazeiSogoJumin);
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $short = (int) ($previewPayload[sprintf('shotoku_joto_tanki_%s', $period)] ?? 0);
            $long = (int) (
                $previewPayload[sprintf('shotoku_joto_choki_sogo_%s', $period)]
                ?? $previewPayload[sprintf('shotoku_joto_choki_%s', $period)]
                ?? 0
            );
            $oneRaw = $previewPayload[sprintf('after_3jitsusan_ichiji_%s', $period)]
                ?? $previewPayload[sprintf('shotoku_ichiji_%s', $period)]
                ?? 0;
            $one = max(0, (int) $oneRaw);

            $previewPayload[sprintf('shotoku_ichiji_%s', $period)] = $one;

            $total = max(0, $short) + max(0, $long) + $one;

            $previewPayload[sprintf('shotoku_joto_ichiji_shotoku_%s', $period)] = $total;
            $previewPayload[sprintf('shotoku_joto_ichiji_jumin_%s', $period)] = $total;
        }

        /** @var FurusatoResultCalculator $resultCalculator */
        $resultCalculator = app(FurusatoResultCalculator::class);
        $previewDetails = $resultCalculator->buildDetails($previewPayload, $calculatorCtx);

        $tokureiStandardRate = [
            'prev' => isset($previewDetails['prev']['AA50']) && $previewDetails['prev']['AA50'] !== null
                ? round($previewDetails['prev']['AA50'] * 100, 3)
                : null,
            'curr' => isset($previewDetails['curr']['AA50']) && $previewDetails['curr']['AA50'] !== null
                ? round($previewDetails['curr']['AA50'] * 100, 3)
                : null,
        ];

        $tokureiComputedPercent = [
            'standard_prev' => $tokureiStandardRate['prev'],
            'standard_curr' => $tokureiStandardRate['curr'],
            'ninety_prev' => 90.000,
            'ninety_curr' => 90.000,
            'sanrin_prev' => $previewPayload['tokurei_rate_sanrin_div5_prev'] ?? null,
            'sanrin_curr' => $previewPayload['tokurei_rate_sanrin_div5_curr'] ?? null,
            'taishoku_prev' => $previewPayload['tokurei_rate_taishoku_prev'] ?? null,
            'taishoku_curr' => $previewPayload['tokurei_rate_taishoku_curr'] ?? null,
            'adopted_prev' => $previewPayload['tokurei_rate_adopted_prev'] ?? null,
            'adopted_curr' => $previewPayload['tokurei_rate_adopted_curr'] ?? null,
            'bunri_min_prev' => $previewPayload['tokurei_rate_bunri_min_prev'] ?? null,
            'bunri_min_curr' => $previewPayload['tokurei_rate_bunri_min_curr'] ?? null,
            'final_prev' => isset($previewDetails['prev']['AA56']) && $previewDetails['prev']['AA56'] !== null
                ? round($previewDetails['prev']['AA56'] * 100, 3)
                : null,
            'final_curr' => isset($previewDetails['curr']['AA56']) && $previewDetails['curr']['AA56'] !== null
                ? round($previewDetails['curr']['AA56'] * 100, 3)
                : null,
        ];

        $previewResults = [
            'details' => $previewDetails,
            'upper' => $previewPayload,
        ];
        
        $context = [
            'dataId' => $dataId,
            'bunriFlag' => $bunriFlag,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'savedInputs' => $savedInputs,
            'results' => [],
            'showResult' => false,
            'shotokuRates' => $shotokuRates,
            'syoriSettings' => $syoriSettings,
            'showSeparatedNetting' => $showSeparatedNetting,
            'jintekiDiff' => $jintekiDiff,
            'tokureiStandardRate' => $tokureiStandardRate,
            'tokureiComputedPercent' => $tokureiComputedPercent,
            'tokureiEnabled' => [
                'sanrin_prev' => $hasSanrinPrev,
                'sanrin_curr' => $hasSanrinCurr,
                'taishoku_prev' => $hasTaishokuPrev,
                'taishoku_curr' => $hasTaishokuCurr,
                'bunri_prev' => $hasBunriPrev,
                'bunri_curr' => $hasBunriCurr,
            ],
        ];

        $session = session();
        if ($session->has('furusato_results')) {
            $context['results'] = (array) $session->get('furusato_results');
        } elseif ($dataId) {
            $context['results'] = $this->getStoredFurusatoResults($dataId);
        }

        if (($context['results'] ?? []) === []) {
            $context['results'] = $previewResults;
        }

        $resultsUpper = [];
        if (isset($context['results']['upper']) && is_array($context['results']['upper'])) {
            $resultsUpper = $context['results']['upper'];
        }

        $context['outInputs'] = $this->buildInputsForView(
            $savedInputs,
            $previewPayload,
            $syoriSettings,
            $resultsUpper,
        );

        return $context;
    }

    /**
     * @param  array<string, mixed>  $savedInputs
     * @param  array<string, mixed>  $previewPayload
     * @param  array<string, mixed>  $syoriSettings
     * @return array<string, mixed>
     */
    private function buildInputsForView(
        array $savedInputs,
        array $previewPayload,
        array $syoriSettings,
        array $resultsUpper = [],
    ): array
    {
        $inputsForView = $savedInputs;
        $resultUpper = $resultsUpper;

        $lookup = function (array $candidates) use ($resultUpper, $previewPayload, $savedInputs): ?int {
            foreach ($candidates as $key) {
                if (array_key_exists($key, $resultUpper) && $resultUpper[$key] !== null) {
                    $value = $this->toNullableInt($resultUpper[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }

                if (array_key_exists($key, $previewPayload) && $previewPayload[$key] !== null) {
                    $value = $this->toNullableInt($previewPayload[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }

                if (array_key_exists($key, $savedInputs) && $savedInputs[$key] !== null && $savedInputs[$key] !== '') {
                    $value = $this->toNullableInt($savedInputs[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $assign = function (string $destination, array $candidates, ?callable $transform = null) use (&$inputsForView, $lookup): void {
            $value = $lookup($candidates);
            if ($value === null) {
                return;
            }

            $inputsForView[$destination] = $transform ? $transform($value) : $value;
        };

        $mirrorMany = function (array $destinations, array $candidates, ?callable $transform = null) use (&$inputsForView, $lookup): void {
            $value = $lookup($candidates);
            if ($value === null) {
                return;
            }

            $value = $transform ? $transform($value) : $value;

            foreach ($destinations as $destination) {
                $inputsForView[$destination] = $value;
            }
        };

        $hasResult = function (string $key) use ($resultUpper): bool {
            if (! array_key_exists($key, $resultUpper)) {
                return false;
            }

            return $this->toNullableInt($resultUpper[$key]) !== null;
        };

        $mirrorFallbackEnabled = (bool) config('app.furusato_mirror_fallback');

        foreach (['prev', 'curr'] as $period) {
            $isSeparated = (int) ($syoriSettings[sprintf('bunri_flag_%s', $period)] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;

            $kShot = sprintf('bunri_shotoku_taishoku_shotoku_%s', $period);
            $kJmn = sprintf('bunri_shotoku_taishoku_jumin_%s', $period);

            $srcServerShot = $lookup([$kShot]);
            $srcServerJmn = $lookup([$kJmn]);

            $srcTaishoku = $lookup([sprintf('shotoku_taishoku_%s', $period)])
                ?? $lookup([sprintf('after_2jitsusan_taishoku_%s', $period)])
                ?? 0;
            $srcTaishoku = $this->valueOrZero($srcTaishoku);

            if (! array_key_exists($kShot, $inputsForView)) {
                $inputsForView[$kShot] = $srcServerShot !== null
                    ? $this->valueOrZero($srcServerShot)
                    : $srcTaishoku;
            }

            if (! array_key_exists($kJmn, $inputsForView)) {
                $inputsForView[$kJmn] = $srcServerJmn !== null
                    ? $this->valueOrZero($srcServerJmn)
                    : $srcTaishoku;
            }

            $shotokuTanki = $this->valueOrZero($lookup([
                sprintf('shotoku_joto_tanki_%s', $period),
                sprintf('after_3jitsusan_joto_tanki_%s', $period),
            ]));

            $shotokuChoki = $lookup([
                sprintf('shotoku_joto_choki_sogo_%s', $period),
                sprintf('shotoku_joto_choki_%s', $period),
            ]);

            if ($shotokuChoki === null) {
                $after3Long = $lookup([sprintf('after_3jitsusan_joto_choki_%s', $period)]);
                if ($after3Long !== null) {
                    $shotokuChoki = intdiv($this->valueOrZero($after3Long), 2);
                }
            }

            $shotokuChoki = $this->valueOrZero($shotokuChoki);

            $shotokuIchiji = $lookup([sprintf('shotoku_ichiji_%s', $period)]);

            if ($shotokuIchiji === null) {
                $after3Ichiji = $lookup([sprintf('after_3jitsusan_ichiji_%s', $period)]);
                if ($after3Ichiji !== null) {
                    $shotokuIchiji = intdiv($this->valueOrZero($after3Ichiji), 2);
                }
            }

            $shotokuIchiji = $this->valueOrZero($shotokuIchiji);

            $sumShotoku = (int) $shotokuTanki + (int) $shotokuChoki + (int) $shotokuIchiji;

            $sumShotokuKey = sprintf('shotoku_joto_ichiji_shotoku_%s', $period);
            if (! array_key_exists($sumShotokuKey, $inputsForView) || $inputsForView[$sumShotokuKey] === null || $inputsForView[$sumShotokuKey] === '') {
                $sumShotokuValue = $lookup([$sumShotokuKey]);
                $inputsForView[$sumShotokuKey] = $sumShotokuValue !== null ? (int) $sumShotokuValue : (int) $sumShotoku;
            }

            $sumJuminKey = sprintf('shotoku_joto_ichiji_jumin_%s', $period);
            if (! array_key_exists($sumJuminKey, $inputsForView) || $inputsForView[$sumJuminKey] === null || $inputsForView[$sumJuminKey] === '') {
                $sumJuminValue = $lookup([$sumJuminKey]);
                $inputsForView[$sumJuminKey] = $sumJuminValue !== null ? (int) $sumJuminValue : (int) $sumShotoku;
            }

            $assign(
                sprintf('tsusango_joto_tanki_%s', $period),
                [
                    sprintf('tsusango_joto_tanki_%s', $period),
                    sprintf('after_3jitsusan_joto_tanki_%s', $period),
                ],
            );

            $assign(
                sprintf('tsusango_joto_choki_%s', $period),
                [
                    sprintf('tsusango_joto_choki_%s', $period),
                    sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                    sprintf('after_3jitsusan_joto_choki_%s', $period),
                ],
            );

            $assign(
                sprintf('tsusango_ichiji_%s', $period),
                [
                    sprintf('tsusango_ichiji_%s', $period),
                    sprintf('after_3jitsusan_ichiji_%s', $period),
                ],
                static fn ($v) => max(0, (int) $v),
            );

            $bunriShotokuKey = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
            $bunriJuminKey = sprintf('bunri_sogo_gokeigaku_jumin_%s', $period);

            $isSeparated = (int) ($syoriSettings[sprintf('bunri_flag_%s', $period)] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;

            if ($isSeparated) {
                $V = fn (string $name): int => $this->valueOrZero($lookup([$name]));
                $long = $this->valueOrZero($lookup([
                    sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                    sprintf('after_3jitsusan_joto_choki_%s', $period),
                ]));

                $separatedSum =
                    $V(sprintf('after_3jitsusan_joto_tanki_%s', $period)) +
                    $long +
                    $V(sprintf('after_3jitsusan_ichiji_%s', $period)) +
                    $V(sprintf('after_3jitsusan_sanrin_%s', $period)) +
                    $V(sprintf('after_3jitsusan_taishoku_%s', $period));

                $inputsForView[$bunriShotokuKey] = $separatedSum;
                $inputsForView[$bunriJuminKey] = $separatedSum;

                if (! $mirrorFallbackEnabled) {
                    continue;
                }

                $shotokuTaxKey = sprintf('tax_kazeishotoku_shotoku_%s', $period);
                if (! array_key_exists($shotokuTaxKey, $inputsForView)) {
                    $source = $lookup([$shotokuTaxKey])
                        ?? $lookup([sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period)]);

                    if ($source !== null) {
                        $inputsForView[$shotokuTaxKey] = $this->floorToThousands((int) $source);
                    }
                }

                $juminTaxKey = sprintf('tax_kazeishotoku_jumin_%s', $period);
                if (! array_key_exists($juminTaxKey, $inputsForView)) {
                    $source = $lookup([$juminTaxKey])
                        ?? $lookup([sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period)])
                        ?? $lookup([sprintf('kazeisoushotoku_%s', $period)]);

                    if ($source !== null) {
                        $inputsForView[$juminTaxKey] = $this->floorToThousands((int) $source);
                    }
                }

                if (! array_key_exists($shotokuTaxKey, $inputsForView)) {
                    $base = $lookup([sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period)]);
                    $base = $base !== null ? (int) $base : 0;

                    $add = $lookup([sprintf('shotoku_joto_ichiji_shotoku_%s', $period)]);
                    if ($add === null) {
                        $tanki = $this->valueOrZero($lookup([sprintf('shotoku_joto_tanki_%s', $period)]));
                        $choki = $this->valueOrZero($lookup([
                            sprintf('shotoku_joto_choki_sogo_%s', $period),
                            sprintf('shotoku_joto_choki_%s', $period),
                        ]));
                        $ichiji = $this->valueOrZero($lookup([sprintf('shotoku_ichiji_%s', $period)]));
                        $add = (int) ($tanki + $choki + $ichiji);
                    } else {
                        $add = (int) $add;
                    }

                    $inputsForView[$shotokuTaxKey] = $this->floorToThousands($base + $add);
                }

                continue;
            }
            
            $mirrorMany(
                [
                    sprintf('syunyu_joto_tanki_shotoku_%s', $period),
                    sprintf('syunyu_joto_tanki_jumin_%s', $period),
                ],
                [sprintf('syunyu_joto_tanki_%s', $period)],
            );
            $mirrorMany(
                [
                    sprintf('syunyu_joto_choki_shotoku_%s', $period),
                    sprintf('syunyu_joto_choki_jumin_%s', $period),
                ],
                [sprintf('syunyu_joto_choki_%s', $period)],
            );
            $mirrorMany(
                [
                    sprintf('syunyu_ichiji_shotoku_%s', $period),
                    sprintf('syunyu_ichiji_jumin_%s', $period),
                ],
                [sprintf('syunyu_ichiji_%s', $period)],
            );
            
            $assign(
                sprintf('sashihiki_joto_tanki_sogo_%s', $period),
                [sprintf('sashihiki_joto_tanki_%s', $period)],
            );
            $assign(
                sprintf('sashihiki_joto_choki_sogo_%s', $period),
                [sprintf('sashihiki_joto_choki_%s', $period)],
            );

            $assign(
                sprintf('tsusango_joto_choki_sogo_%s', $period),
                [
                    sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                    sprintf('tsusango_joto_choki_sogo_%s', $period),
                ],
            );
            $assign(
                sprintf('tokubetsukojo_joto_tanki_%s', $period),
                [sprintf('tokubetsukojo_joto_tanki_%s', $period)],
            );
            $assign(
                sprintf('tokubetsukojo_joto_choki_%s', $period),
                [sprintf('tokubetsukojo_joto_choki_%s', $period)],
            );
            $assign(
                sprintf('tokubetsukojo_ichiji_%s', $period),
                [sprintf('tokubetsukojo_ichiji_%s', $period)],
            );

            $assign(
                sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period),
                [sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period)],
            );
            $assign(
                sprintf('after_joto_ichiji_tousan_joto_choki_%s', $period),
                [sprintf('after_joto_ichiji_tousan_joto_choki_%s', $period)],
            );
            $assign(
                sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period),
                [sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period)],
            );
            $assign(
                sprintf('after_joto_ichiji_tousan_ichiji_%s', $period),
                [sprintf('after_joto_ichiji_tousan_ichiji_%s', $period)],
            );

            $mirrorMany(
                [
                    sprintf('tsusanmae_joto_tanki_sogo_%s', $period),
                    sprintf('tsusanmae_joto_tanki_%s', $period),
                ],
                [
                    sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $period),
                    sprintf('tsusanmae_joto_tanki_sogo_%s', $period),
                    sprintf('tsusanmae_joto_tanki_%s', $period),
                ],
            );
            $mirrorMany(
                [
                    sprintf('tsusanmae_joto_choki_sogo_%s', $period),
                    sprintf('tsusanmae_joto_choki_%s', $period),
                ],
                [
                    sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period),
                    sprintf('after_joto_ichiji_tousan_joto_choki_%s', $period),
                    sprintf('tsusanmae_joto_choki_sogo_%s', $period),
                    sprintf('tsusanmae_joto_choki_%s', $period),
                ],
            );
            $mirrorMany(
                [
                    sprintf('tsusanmae_ichiji_%s', $period),
                    sprintf('tsusanmae_joto_ichiji_%s', $period),
                ],
                [
                    sprintf('after_joto_ichiji_tousan_ichiji_%s', $period),
                    sprintf('tsusanmae_ichiji_%s', $period),
                    sprintf('tsusanmae_joto_ichiji_%s', $period),
                ],
            );

            $syunyuSanrinValue = $lookup([sprintf('syunyu_sanrin_%s', $period)]);
            $syunyuSanrinValue = $syunyuSanrinValue !== null ? $syunyuSanrinValue : 0;
            $inputsForView[sprintf('bunri_syunyu_sanrin_shotoku_%s', $period)] = $syunyuSanrinValue;
            $inputsForView[sprintf('bunri_syunyu_sanrin_jumin_%s', $period)] = $syunyuSanrinValue;

            $assign(
                sprintf('after_1jitsusan_sanrin_%s', $period),
                [
                    sprintf('after_1jitsusan_sanrin_%s', $period),
                    sprintf('sashihiki_sanrin_%s', $period),
                ],
            );
            $assign(sprintf('after_3jitsusan_sanrin_%s', $period), [sprintf('after_3jitsusan_sanrin_%s', $period)]);
            $assign(sprintf('shotoku_sanrin_%s', $period), [sprintf('shotoku_sanrin_%s', $period)]);

            $assign(sprintf('shotoku_keijo_%s', $period), [sprintf('shotoku_keijo_%s', $period)]);
            $assign(sprintf('shotoku_joto_tanki_%s', $period), [sprintf('shotoku_joto_tanki_%s', $period)]);
            $assign(
                sprintf('shotoku_joto_choki_%s', $period),
                [
                    sprintf('shotoku_joto_choki_%s', $period),
                    sprintf('shotoku_joto_choki_sogo_%s', $period),
                ],
            );
            $assign(
                sprintf('shotoku_joto_choki_sogo_%s', $period),
                [
                    sprintf('shotoku_joto_choki_sogo_%s', $period),
                    sprintf('shotoku_joto_choki_%s', $period),
                ],
            );
            $assign(sprintf('shotoku_ichiji_%s', $period), [sprintf('shotoku_ichiji_%s', $period)]);
            $assign(sprintf('shotoku_taishoku_%s', $period), [sprintf('shotoku_taishoku_%s', $period)]);

            $assign(
                sprintf('after_2jitsusan_taishoku_%s', $period),
                [
                    sprintf('after_2jitsusan_taishoku_%s', $period),
                    sprintf('bunri_shotoku_taishoku_shotoku_%s', $period),
                ],
            );

            $bunriKeys = [
                sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period),
                sprintf('bunri_sogo_gokeigaku_jumin_%s', $period),
                sprintf('bunri_sashihiki_gokei_shotoku_%s', $period),
                sprintf('bunri_sashihiki_gokei_jumin_%s', $period),
                sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period),
                sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period),
            ];

            $bunriResultsAvailable = true;
            foreach ($bunriKeys as $key) {
                $assign($key, [$key]);

                if (! $hasResult($key) && $lookup([$key]) === null) {
                    $bunriResultsAvailable = false;
                }
            }

            $shotokuKey = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            $juminKey = sprintf('tax_kazeishotoku_jumin_%s', $period);

            if (! array_key_exists($shotokuKey, $inputsForView)) {
                $assign($shotokuKey, [$shotokuKey]);
            }

            if (! array_key_exists($juminKey, $inputsForView)) {
                $assign($juminKey, [$juminKey]);
            }

            $hasShotokuResult = $hasResult($shotokuKey);
            $hasJuminResult = $hasResult($juminKey);

            $sumShotokuKey = sprintf('shotoku_joto_ichiji_shotoku_%s', $period);
            $sumJuminKey = sprintf('shotoku_joto_ichiji_jumin_%s', $period);

            $assign($sumShotokuKey, [$sumShotokuKey]);
            $assign($sumJuminKey, [$sumJuminKey]);

            if ($mirrorFallbackEnabled && $resultUpper === []) {
                if ($isSeparated) {
                    $bunriShotokuKey = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
                    $bunriJuminKey = sprintf('bunri_sogo_gokeigaku_jumin_%s', $period);

                    $hasBunriSum = $lookup([$bunriShotokuKey]) !== null
                        || $lookup([$bunriJuminKey]) !== null;

                    if (! $hasBunriSum) {
                        $valueOrZero = fn (string $name): int => $this->valueOrZero($lookup([$name]));

                        $sum = $valueOrZero(sprintf('after_3jitsusan_joto_tanki_%s', $period))
                            + $this->valueOrZero($lookup([
                                sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                                sprintf('after_3jitsusan_joto_choki_%s', $period),
                            ]))
                            + $valueOrZero(sprintf('after_3jitsusan_ichiji_%s', $period))
                            + $valueOrZero(sprintf('after_3jitsusan_sanrin_%s', $period))
                            + $valueOrZero(sprintf('after_3jitsusan_taishoku_%s', $period));

                        $inputsForView[$bunriShotokuKey] = $sum;
                        $inputsForView[$bunriJuminKey] = $sum;
                    }

                    if (! $bunriResultsAvailable) {
                        $after3Short = $this->valueOrZero($lookup([
                            sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period),
                            sprintf('after_3jitsusan_joto_tanki_%s', $period),
                        ]));
                        $after3Long = $this->valueOrZero($lookup([
                            sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                            sprintf('after_3jitsusan_joto_choki_%s', $period),
                        ]));
                        $after3Ichiji = $this->valueOrZero($lookup([sprintf('after_3jitsusan_ichiji_%s', $period)]));
                        $after3Sanrin = $this->valueOrZero($lookup([sprintf('after_3jitsusan_sanrin_%s', $period)]));
                        $after3Taishoku = $this->valueOrZero($lookup([sprintf('after_3jitsusan_taishoku_%s', $period)]));

                        $separatedSum = $after3Short + $after3Long + $after3Ichiji + $after3Sanrin + $after3Taishoku;

                        $bunriShotokuKey = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
                        $bunriJuminKey = sprintf('bunri_sogo_gokeigaku_jumin_%s', $period);

                        $inputsForView[$bunriShotokuKey] = $separatedSum;
                        $inputsForView[$bunriJuminKey] = $separatedSum;

                        $kojoShotoku = $this->valueOrZero($lookup([
                            sprintf('kojo_gokei_shotoku_%s', $period),
                        ]));
                        $kojoJumin = $this->valueOrZero($lookup([
                            sprintf('kojo_gokei_jumin_%s', $period),
                        ]));

                        $bunriSashihikiShotoku = min($kojoShotoku, $separatedSum);
                        $bunriSashihikiJumin = min($kojoJumin, $separatedSum);
                        $bunriKazeishotokuShotoku = $this->floorToThousands(max(0, $separatedSum - $bunriSashihikiShotoku));
                        $bunriKazeishotokuJumin = $this->floorToThousands(max(0, $separatedSum - $bunriSashihikiJumin));

                        $bunriSashihikiShotokuKey = sprintf('bunri_sashihiki_gokei_shotoku_%s', $period);
                        $bunriSashihikiJuminKey = sprintf('bunri_sashihiki_gokei_jumin_%s', $period);
                        $bunriKazeiShotokuKey = sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period);
                        $bunriKazeiJuminKey = sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period);

                        foreach ([
                            $bunriSashihikiShotokuKey => $bunriSashihikiShotoku,
                            $bunriSashihikiJuminKey => $bunriSashihikiJumin,
                            $bunriKazeiShotokuKey => $bunriKazeishotokuShotoku,
                            $bunriKazeiJuminKey => $bunriKazeishotokuJumin,
                        ] as $key => $value) {
                            if (! array_key_exists($key, $inputsForView)) {
                                $inputsForView[$key] = $value;
                            }
                        }

                        if (! $hasJuminResult && ! array_key_exists($juminKey, $inputsForView)) {
                            $inputsForView[$juminKey] = $bunriKazeishotokuJumin;
                        }
                    }

                    if (! $hasShotokuResult && ! array_key_exists($shotokuKey, $inputsForView)) {
                        $shotokuKeijo = $this->valueOrZero($lookup([sprintf('shotoku_keijo_%s', $period)]));
                        $shotokuJotoTanki = $this->valueOrZero($lookup([sprintf('shotoku_joto_tanki_%s', $period)]));
                        $shotokuJotoChoki = $this->valueOrZero($lookup([
                            sprintf('shotoku_joto_choki_sogo_%s', $period),
                            sprintf('shotoku_joto_choki_%s', $period),
                        ]));
                        $shotokuIchiji = $this->valueOrZero($lookup([sprintf('shotoku_ichiji_%s', $period)]));
                        $kojoShotoku = $this->valueOrZero($lookup([
                            sprintf('kojo_gokei_shotoku_%s', $period),
                        ]));

                        $sumShotoku = $shotokuKeijo + $shotokuJotoTanki + $shotokuJotoChoki + $shotokuIchiji;
                        $roundedShotoku = $this->floorToThousands(max(0, $sumShotoku - $kojoShotoku));

                        $inputsForView[$shotokuKey] = $roundedShotoku;
                    }

                    if (! $hasJuminResult && ! array_key_exists($juminKey, $inputsForView)) {
                        $bunriKazeiJuminKey = sprintf('bunri_kazeishotoku_sogo_jumin_%s', $period);
                        if (array_key_exists($bunriKazeiJuminKey, $inputsForView)) {
                            $inputsForView[$juminKey] = $this->valueOrZero($inputsForView[$bunriKazeiJuminKey]);
                        }
                    }

                    if (! array_key_exists($sumShotokuKey, $inputsForView)) {
                        $sumJotoIchiji = 0;
                        foreach (['shotoku_joto_tanki_', 'shotoku_joto_choki_', 'shotoku_ichiji_'] as $prefix) {
                            $sumJotoIchiji += (int) ($inputsForView[$prefix . $period] ?? 0);
                        }
                        $inputsForView[$sumShotokuKey] = $sumJotoIchiji;
                    }

                    if (! array_key_exists($sumJuminKey, $inputsForView)) {
                        $sumJuminPayload = $lookup([$sumJuminKey]);
                        $inputsForView[$sumJuminKey] = $sumJuminPayload !== null
                            ? $this->valueOrZero($sumJuminPayload)
                            : ($inputsForView[$sumShotokuKey] ?? 0);
                    }

                    continue;
                }

                $shotokuKeijo = $this->valueOrZero($lookup([sprintf('shotoku_keijo_%s', $period)]));
                $shotokuJotoTanki = $this->valueOrZero($lookup([sprintf('shotoku_joto_tanki_%s', $period)]));
                $shotokuJotoChoki = $this->valueOrZero($lookup([
                    sprintf('shotoku_joto_choki_sogo_%s', $period),
                    sprintf('shotoku_joto_choki_%s', $period),
                ]));
                $shotokuIchiji = $this->valueOrZero($lookup([sprintf('shotoku_ichiji_%s', $period)]));

                $sumShotoku = $shotokuKeijo + $shotokuJotoTanki + $shotokuJotoChoki + $shotokuIchiji;

                $shotokuKojo = $this->valueOrZero($lookup([
                    sprintf('kojo_gokei_shotoku_%s', $period),
                    sprintf('kojo_gokei_jumin_%s', $period),
                ]));
                $juminKojo = $this->valueOrZero($lookup([
                    sprintf('kojo_gokei_jumin_%s', $period),
                    sprintf('kojo_gokei_shotoku_%s', $period),
                ]));

                $roundedShotoku = $this->floorToThousands(max(0, $sumShotoku - $shotokuKojo));
                $roundedJumin = $this->floorToThousands(max(0, $sumShotoku - $juminKojo));

                if (! $hasShotokuResult && ! array_key_exists($shotokuKey, $inputsForView)) {
                    $inputsForView[$shotokuKey] = $roundedShotoku;
                }

                if (! $hasJuminResult && ! array_key_exists($juminKey, $inputsForView)) {
                    $inputsForView[$juminKey] = $roundedJumin;
                }

                if (! array_key_exists($sumShotokuKey, $inputsForView)) {
                    $inputsForView[$sumShotokuKey] = $sumShotoku;
                }

                if (! array_key_exists($sumJuminKey, $inputsForView)) {
                    $inputsForView[$sumJuminKey] = $sumShotoku;
                }
            }

            if ($isSeparated) {
                continue;
            }

            $shotokuKeijo = $this->valueOrZero($lookup([sprintf('shotoku_keijo_%s', $period)]));
            $shotokuJotoTanki = $this->valueOrZero($lookup([sprintf('shotoku_joto_tanki_%s', $period)]));
            $shotokuJotoChoki = $this->valueOrZero($lookup([
                sprintf('shotoku_joto_choki_sogo_%s', $period),
                sprintf('shotoku_joto_choki_%s', $period),
            ]));
            $shotokuIchiji = $this->valueOrZero($lookup([sprintf('shotoku_ichiji_%s', $period)]));

            $ichijiNonNeg = max(0, $shotokuIchiji);
            $sumS = $shotokuKeijo + $shotokuJotoTanki + $shotokuJotoChoki + $ichijiNonNeg;

            $kojoShotoku = $this->valueOrZero($lookup([sprintf('kojo_gokei_shotoku_%s', $period)]));
            $kojoJumin = $this->valueOrZero($lookup([
                sprintf('kojo_gokei_jumin_%s', $period),
                sprintf('kojo_gokei_shotoku_%s', $period),
            ]));

            $taxShotoku = $this->floorToThousands(max(0, $sumS - $kojoShotoku));
            $taxJumin = $this->floorToThousands(max(0, $sumS - $kojoJumin));

            $keyShotoku = sprintf('tax_kazeishotoku_shotoku_%s', $period);
            if (! array_key_exists($keyShotoku, $inputsForView)) {
                $inputsForView[$keyShotoku] = $lookup([$keyShotoku]) ?? $taxShotoku;
            }

            $keyJumin = sprintf('tax_kazeishotoku_jumin_%s', $period);
            if (! array_key_exists($keyJumin, $inputsForView)) {
                $inputsForView[$keyJumin] = $lookup([$keyJumin]) ?? $taxJumin;
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $afterThreeMap = [
                sprintf('after_3jitsusan_keijo_%s', $period) => [sprintf('after_3jitsusan_keijo_%s', $period)],
                sprintf('after_3jitsusan_joto_tanki_%s', $period) => [sprintf('after_3jitsusan_joto_tanki_%s', $period)],
                sprintf('after_3jitsusan_joto_choki_sogo_%s', $period) => [sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)],
                sprintf('after_3jitsusan_joto_choki_%s', $period) => [sprintf('after_3jitsusan_joto_choki_%s', $period)],
                sprintf('after_3jitsusan_ichiji_%s', $period) => [sprintf('after_3jitsusan_ichiji_%s', $period)],
                sprintf('after_3jitsusan_sanrin_%s', $period) => [sprintf('after_3jitsusan_sanrin_%s', $period)],
                sprintf('after_3jitsusan_taishoku_%s', $period) => [sprintf('after_3jitsusan_taishoku_%s', $period)],
            ];

            foreach ($afterThreeMap as $destination => $candidates) {
                $assign($destination, $candidates);
            }

            $value = $lookup([sprintf('shotoku_gokei_%s', $period)]);
            if ($value !== null) {
                $inputsForView[sprintf('shotoku_gokei_%s', $period)] = $value;
            }

            $tsusanmaeKeijoKey = sprintf('tsusanmae_keijo_%s', $period);
            $tsusanmaeKeijo = $lookup([$tsusanmaeKeijoKey]);
            if ($tsusanmaeKeijo === null) {
                $tsusanmaeKeijo = 0;
                $keijoSources = [
                    'shotoku_jigyo_eigyo_shotoku' => false,
                    'shotoku_jigyo_nogyo_shotoku' => false,
                    'shotoku_fudosan_shotoku' => false,
                    'shotoku_haito_shotoku' => true,
                    'shotoku_rishi_shotoku' => true,
                    'shotoku_kyuyo_shotoku' => true,
                    'shotoku_zatsu_nenkin_shotoku' => true,
                    'shotoku_zatsu_gyomu_shotoku' => true,
                    'shotoku_zatsu_sonota_shotoku' => true,
                ];

                foreach ($keijoSources as $prefix => $nonNegative) {
                    $value = $lookup([sprintf('%s_%s', $prefix, $period)]);
                    if ($value === null) {
                        continue;
                    }

                    $amount = $value;
                    if ($nonNegative) {
                        $amount = max(0, $amount);
                    }

                    $tsusanmaeKeijo += $amount;
                }
            }

            if ($tsusanmaeKeijo !== null) {
                $inputsForView[$tsusanmaeKeijoKey] = $tsusanmaeKeijo;
            }
        }

        return $inputsForView;
    }

    private function computeJintekiDiff(array $payload): array
    {
        $periods = ['prev', 'curr'];
        $diffs = [];
        $totals = array_fill_keys($periods, 0);

        foreach (self::JINTEKI_DIFF_MAP as $key => $entry) {
            foreach ($periods as $period) {
                $shotokuKey = sprintf('%s_%s', $entry['shotoku'], $period);
                $juminKey = sprintf('%s_%s', $entry['jumin'], $period);

                $shotoku = $this->valueOrZero($this->toNullableInt($payload[$shotokuKey] ?? null));
                $jumin = $this->valueOrZero($this->toNullableInt($payload[$juminKey] ?? null));

                $diff = $shotoku - $jumin;
                $diffs[$key][$period] = $diff;
                $totals[$period] += $diff;
            }
        }

        $diffs['sum'] = [
            'prev' => $totals['prev'],
            'curr' => $totals['curr'],
        ];

        return $diffs;
    }

    private function findDataForInput(Request $request, int $dataId): ?Data
    {
        if ($request->user()) {
            return $this->resolveCompanyScopedDataOrFail($request);
        }

        return Data::find($dataId);
    }

    private function toWarekiYear(int $year): string
    {
        if ($year >= 2019) {
            return sprintf('令和%d年', $year - 2018);
        }

        return sprintf('平成%d年', $year - 1988);
    }

    private function toWarekiShortYear(int $year): string
    {
        if ($year >= 2019) {
            return sprintf('R%02d', $year - 2018);
        }

        if ($year >= 1989) {
            return sprintf('H%02d', $year - 1988);
        }

        if ($year >= 1926) {
            return sprintf('S%02d', $year - 1925);
        }

        return (string) $year;
    }

    public function save(
        Request $request,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        if ((int) $request->input('recalc_all') === 1) {
            $data = $this->resolveAuthorizedDataOrFail($request, 'update');

            $updates = $request->except([
                '_token',
                'data_id',
                'redirect_to',
                'show_result',
                'origin_tab',
                'origin_anchor',
                'recalc_all',
            ]);

            $this->validateBunriChokiShotokuInputs($request);

            $this->performFullRecalculation($request, $data, $updates, $recalculateUseCase);

            $goto = (string) $request->input('redirect_to', 'input');
            if ($goto === '') {
                $goto = 'input';
            }

            return $this->redirectAfterGoto($request, $data, $goto, '再計算が完了しました');
        }

        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $updates = $request->except([
            '_token',
            'data_id',
            'redirect_to',
            'show_result',
            'origin_tab',
            'origin_anchor',
            'recalc_all',
        ]);
        $this->validateBunriChokiShotokuInputs($request);
        $goto = (string) $request->input('redirect_to', '');
        $shouldShowResult = $request->boolean('show_result') || $goto === '' || $goto === 'input';

        $this->runRecalculationPipeline(
            $request,
            $data,
            $updates,
            ['should_flash_results' => $shouldShowResult],
            $recalculateUseCase,
        );

        return $this->redirectAfterGoto($request, $data, $goto, '保存しました');
    }

    public function jigyoEigyoDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $inputRecord = FurusatoInput::query()
            ->where('data_id', $data->id)
            ->first();

        $payload = $inputRecord?->payload;
        $out = ['inputs' => is_array($payload) ? $payload : []];
        $storedLabels = $this->extractStoredLabels($inputRecord, self::JIGYO_EIGYO_LABEL_FIELDS);

        return view('tax.furusato.details.jigyo_eigyo_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
            'storedLabels' => $storedLabels,
        ]);
    }

    public function saveJigyoEigyoDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $labelUpdates = $this->validateAndNormalizeLabels($req, self::JIGYO_EIGYO_LABEL_FIELDS);

        $payload = $this->sanitizeDetailPayload($req->except(array_merge(
            ['_token', 'data_id', 'origin_tab', 'origin_anchor'],
            self::JIGYO_EIGYO_LABEL_FIELDS,
        )));

        $updatesForRecalc = array_merge($payload, $labelUpdates);

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'jigyo';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function fudosanDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $inputRecord = FurusatoInput::query()
            ->where('data_id', $data->id)
            ->first();

        $payload = $inputRecord?->payload;
        $out = ['inputs' => is_array($payload) ? $payload : []];
        $storedLabels = $this->extractStoredLabels($inputRecord, self::FUDOSAN_LABEL_FIELDS);

        return view('tax.furusato.details.fudosan_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
            'storedLabels' => $storedLabels,
        ]);
    }

    public function saveFudosanDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $labelUpdates = $this->validateAndNormalizeLabels($req, self::FUDOSAN_LABEL_FIELDS);

        $payload = $this->sanitizeDetailPayload($req->except(array_merge(
            ['_token', 'data_id', 'origin_tab', 'origin_anchor'],
            self::FUDOSAN_LABEL_FIELDS,
        )));

        $this->normalizeFudosanSyunyuKeys($payload);

        $updatesForRecalc = array_merge($payload, $labelUpdates);

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'fudosan';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function kifukinDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = FurusatoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $normalizer = app(PayloadNormalizer::class);
        $out = ['inputs' => is_array($payload) ? $normalizer->normalize($payload) : []];

        return view('tax.furusato.details.kifukin_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
        ]);
    }

    public function saveKifukinDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $categories = [
            'furusato',
            'kyodobokin_nisseki',
            'seito',
            'npo',
            'koueki',
            'kuni',
            'sonota',
        ];
        $periods = ['prev', 'curr'];
        $areas = ['pref', 'muni'];

        $rules = [];
        foreach ($categories as $category) {
            foreach ($periods as $period) {
                foreach ($areas as $area) {
                    $key = sprintf('juminzei_zeigakukojo_%s_%s_%s', $area, $category, $period);
                    $rules[$key] = ['bail', 'nullable', 'integer', 'min:0'];
                }
            }
        }

        if ($rules !== []) {
            Validator::make($req->only(array_keys($rules)), $rules)->validate();
        }

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'redirect_to', 'origin_tab', 'origin_anchor']));
        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'kifukin_details';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function jotoIchijiDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $context = $this->makeInputContext($req, $data->id);
        $inputsForView = $context['outInputs'] ?? $context['savedInputs'] ?? [];

        return view('tax.furusato.details.joto_ichiji_details', [
            'dataId' => $data->id,
            'kihuYear' => $context['kihuYear'] ?? ($data->kihu_year ? (int) $data->kihu_year : null),
            'warekiPrev' => $context['warekiPrev'] ?? ($data->kihu_year ? $this->toWarekiYear((int) $data->kihu_year - 1) : '前年'),
            'warekiCurr' => $context['warekiCurr'] ?? ($data->kihu_year ? $this->toWarekiYear((int) $data->kihu_year) : '当年'),
            'out' => ['inputs' => $inputsForView],
        ]);
    }

    public function saveJotoIchijiDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $bases = ['joto_ichiji_shunyu', 'joto_ichiji_keihi'];
        $rules = [];
        foreach ($bases as $base) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));
        $this->normalizeJotoIchijiKeys($payload);

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'joto_ichiji';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function bunriJotoDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.bunri_joto_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriJotoDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $rowKeys = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
        ];
        $editablePrefixes = ['syunyu', 'keihi', 'tsusango', 'tokubetsukojo'];
        $rules = [];

        foreach ($rowKeys as $rowKey) {
            foreach ($editablePrefixes as $prefix) {
                foreach (['prev', 'curr'] as $period) {
                    $rules[sprintf('%s_%s_%s', $prefix, $rowKey, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
                }
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('joto_choki_tokutei_sonshitsu_%s', $period)] = ['bail', 'nullable', 'integer', 'min:0'];
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));
        
        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'bunri_joto';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function bunriKabutekiDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.bunri_kabuteki_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriKabutekiDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $rows = [
            ['key' => 'ippan_joto', 'kurikoshi' => false],
            ['key' => 'jojo_joto', 'kurikoshi' => true],
            ['key' => 'jojo_haito', 'kurikoshi' => false],
        ];

        $rules = [];
        foreach ($rows as $row) {
            foreach (['syunyu', 'keihi', 'tsusango'] as $prefix) {
                foreach (['prev', 'curr'] as $period) {
                    $ruleKey = sprintf('%s_%s_%s', $prefix, $row['key'], $period);
                    $rule = ['bail', 'nullable', 'integer'];

                    $isIppanTsusango = $row['key'] === 'ippan_joto' && $prefix === 'tsusango';
                    if (! $isIppanTsusango) {
                        $rule[] = 'min:0';
                    }

                    $rules[$ruleKey] = $rule;
                }
            }

            if ($row['kurikoshi']) {
                foreach (['prev', 'curr'] as $period) {
                    $rules[sprintf('kurikoshi_%s_%s', $row['key'], $period)] = ['bail', 'nullable', 'integer', 'min:0'];
                }
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'bunri_kabuteki';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function bunriSakimonoDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.bunri_sakimono_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriSakimonoDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $rules = [];
        foreach (['syunyu_sakimono', 'keihi_sakimono', 'kurikoshi_sakimono'] as $base) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'bunri_sakimono';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function bunriSanrinDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $context = $this->makeInputContext($req, $data->id);
        $inputsForView = $context['outInputs'] ?? $context['savedInputs'] ?? [];

        return view('tax.furusato.details.bunri_sanrin_details', [
            'dataId' => $data->id,
            'kihuYear' => $context['kihuYear'] ?? ($data->kihu_year ? (int) $data->kihu_year : null),
            'warekiPrev' => $context['warekiPrev'] ?? ($data->kihu_year ? $this->toWarekiYear((int) $data->kihu_year - 1) : '前年'),
            'warekiCurr' => $context['warekiCurr'] ?? ($data->kihu_year ? $this->toWarekiYear((int) $data->kihu_year) : '当年'),
            'out' => ['inputs' => $inputsForView],
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriSanrinDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $rules = [];
        foreach (['syunyu_sanrin', 'keihi_sanrin', 'tokubetsukojo_sanrin'] as $base) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'bunri_sanrin';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function kojoSeimeiJishinDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.kojo_seimei_jishin_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
        ]);
    }

    public function saveKojoSeimeiJishinDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $inputKeys = [
            'kojo_seimei_shin_prev',
            'kojo_seimei_shin_curr',
            'kojo_seimei_kyu_prev',
            'kojo_seimei_kyu_curr',
            'kojo_seimei_nenkin_shin_prev',
            'kojo_seimei_nenkin_shin_curr',
            'kojo_seimei_nenkin_kyu_prev',
            'kojo_seimei_nenkin_kyu_curr',
            'kojo_seimei_kaigo_iryo_prev',
            'kojo_seimei_kaigo_iryo_curr',
            'kojo_seimei_gokei_prev',
            'kojo_seimei_gokei_curr',
            'kojo_jishin_prev',
            'kojo_jishin_curr',
            'kojo_kyuchoki_songai_prev',
            'kojo_kyuchoki_songai_curr',
            'kojo_jishin_gokei_prev',
            'kojo_jishin_gokei_curr',
        ];

        $rules = array_fill_keys($inputKeys, ['bail', 'nullable', 'integer', 'min:0']);

        Validator::make($req->only($inputKeys), $rules)->validate();

        $payload = Arr::only($req->all(), $inputKeys);
        $payload = $this->sanitizeDetailPayload($payload);

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'kojo_seimei_jishin';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor ?: 'kojo_seimei_jishin');
    }

    public function kojoJintekiDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.kojo_jinteki_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
        ]);
    }

    public function saveKojoJintekiDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $toggleFields = [
            'kojo_kafu_applicable_prev',
            'kojo_kafu_applicable_curr',
            'kojo_hitorioya_applicable_prev',
            'kojo_hitorioya_applicable_curr',
            'kojo_kinrogakusei_applicable_prev',
            'kojo_kinrogakusei_applicable_curr',
        ];

        $categoryFields = [
            'kojo_haigusha_category_prev',
            'kojo_haigusha_category_curr',
        ];

        $numericFields = [
            'kojo_shogaisha_count_prev',
            'kojo_shogaisha_count_curr',
            'kojo_tokubetsu_shogaisha_count_prev',
            'kojo_tokubetsu_shogaisha_count_curr',
            'kojo_doukyo_tokubetsu_shogaisha_count_prev',
            'kojo_doukyo_tokubetsu_shogaisha_count_curr',
            'kojo_haigusha_tokubetsu_gokeishotoku_prev',
            'kojo_haigusha_tokubetsu_gokeishotoku_curr',
            'kojo_fuyo_ippan_count_prev',
            'kojo_fuyo_ippan_count_curr',
            'kojo_fuyo_tokutei_count_prev',
            'kojo_fuyo_tokutei_count_curr',
            'kojo_fuyo_roujin_doukyo_count_prev',
            'kojo_fuyo_roujin_doukyo_count_curr',
            'kojo_fuyo_roujin_sonota_count_prev',
            'kojo_fuyo_roujin_sonota_count_curr',
            'kojo_tokutei_shinzoku_1_shotoku_prev',
            'kojo_tokutei_shinzoku_1_shotoku_curr',
            'kojo_tokutei_shinzoku_2_shotoku_prev',
            'kojo_tokutei_shinzoku_2_shotoku_curr',
            'kojo_tokutei_shinzoku_3_shotoku_prev',
            'kojo_tokutei_shinzoku_3_shotoku_curr',
        ];

        $rules = [];
        foreach ($numericFields as $field) {
            $rules[$field] = ['bail', 'nullable', 'integer', 'min:0'];
        }

        foreach ($toggleFields as $field) {
            $rules[$field] = ['bail', 'nullable', 'in:〇,×'];
        }

        foreach ($categoryFields as $field) {
            $rules[$field] = ['bail', 'nullable', 'in:ippan,roujin,none'];
        }

        $validated = Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = [];

        foreach ($numericFields as $field) {
            $payload[$field] = $this->toNullableInt($validated[$field] ?? $req->input($field));
        }

        foreach ($toggleFields as $field) {
            $value = $validated[$field] ?? null;
            $payload[$field] = $value === null || $value === '' ? null : $value;
        }

        foreach ($categoryFields as $field) {
            $value = $validated[$field] ?? null;
            $payload[$field] = $value === null || $value === '' ? null : $value;
        }

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'kojo_jinteki';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor ?: 'kojo_jinteki');
    }

    public function kojoIryoDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiShortYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiShortYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        $legacyMappings = [
            'kojo_iryo_shishutsu_prev' => 'kojo_iryo_shiharai_prev',
            'kojo_iryo_shishutsu_curr' => 'kojo_iryo_shiharai_curr',
            'kojo_iryo_hojokin_prev' => 'kojo_iryo_hotengaku_prev',
            'kojo_iryo_hojokin_curr' => 'kojo_iryo_hotengaku_curr',
        ];

        foreach ($legacyMappings as $legacy => $current) {
            if (! array_key_exists($current, $payload) && array_key_exists($legacy, $payload)) {
                $payload[$current] = $payload[$legacy];
            }
        }

        [$shotokuGokeiPrev, $shotokuGokeiCurr] = $this->resolveShotokuGokei($data->id);
        $payload['kojo_iryo_shotoku_gokei_prev'] = $shotokuGokeiPrev;
        $payload['kojo_iryo_shotoku_gokei_curr'] = $shotokuGokeiCurr;

        return view('tax.furusato.details.kojo_iryo_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
        ]);
    }

    public function saveKojoIryoDetails(
        Request $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $inputFields = [
            'kojo_iryo_shiharai_prev',
            'kojo_iryo_shiharai_curr',
            'kojo_iryo_hotengaku_prev',
            'kojo_iryo_hotengaku_curr',
        ];

        $rules = array_fill_keys($inputFields, ['bail', 'nullable', 'integer', 'min:0']);
        Validator::make($req->only($inputFields), $rules)->validate();

        $payload = $this->sanitizeDetailPayload(Arr::only($req->all(), $inputFields));

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            $this->performFullRecalculation($req, $data, $updatesForRecalc, $recalculateUseCase);

            $goto = (string) $req->input('redirect_to', '');
            if ($goto === '' || $goto === 'input') {
                $goto = 'kojo_iryo';
            }

            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor ?: 'kojo_iryo');
    }

    public function syoriIndex(Request $request)
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        $setting = FurusatoSyoriSetting::query()->where('data_id', $data->id)->first();
        $payload = $this->syoriDefaultPayload();

        if ($setting && is_array($setting->payload)) {
            $payload = array_replace($payload, array_intersect_key($setting->payload, $payload));
        }

        $payload = $this->applyStandardRates($payload);

        return view('tax.furusato.syori_menu', [
            'dataId' => $data->id,
            'settings' => $payload,
        ]);
    }

    public function syoriSave(
        FurusatoSyoriRequest $request,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $validated = $request->validated();

        $payload = array_intersect_key($validated, $this->syoriDefaultPayload());
        $payload = $this->applyStandardRates($payload);

        $userId = (int) auth()->id();

        FurusatoSyoriSetting::unguarded(function () use ($data, $payload, $userId): void {
            $record = FurusatoSyoriSetting::firstOrNew([
                'data_id' => $data->id,
            ]);

            $record->data_id = $data->id;
            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;

            if (! $record->exists) {
                $record->created_by = $userId ?: null;
            }

            $record->payload = $payload;
            $record->updated_by = $userId ?: null;

            $record->saveOrFail();
        });

        $this->performFullRecalculation($request, $data, [], $recalculateUseCase);

        $goto = (string) $request->input('redirect_to', '');
        $routeParams = ['data_id' => $data->id];

        if ($goto === 'input') {
            return redirect()->route('furusato.input', $routeParams)->with('success', '保存しました');
        }

        if ($goto === 'master') {
            return redirect()->route('furusato.master', $routeParams)->with('success', '保存しました');
        }

        if ($goto === 'data_master') {
            return redirect()->route('data.index', $routeParams)->with('success', '保存しました');
        }

        return redirect()->route('furusato.syori', $routeParams)->with('success', '保存しました');
    }

    public function master(Request $request)
    {
        $dataId = (int) ($request->query('data_id') ?? 0);
        if ($dataId <= 0) {
            return redirect()->route('furusato.index');
        }

        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('tax.furusato.master', [
            'dataId' => $data->id,
            'grid'   => FurusatoMasterSheet::grid(), // そのまま表現（A1:AA20）
        ]);
    }

    public function shotokuMaster(Request $request, FurusatoMasterService $masterService)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);
        $companyId = $request->user()?->company_id;
        
        return view('tax.furusato.master.shotoku_master', [
            'dataId' => $data->id,
            'rates' => $masterService->getShotokuRates(self::MASTER_KIHU_YEAR, $companyId),
        ]);
    }

    public function juminMaster(Request $request, FurusatoMasterService $masterService)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);
        $companyId = $request->user()?->company_id;
        
        return view('tax.furusato.master.jumin_master', [
            'dataId' => $data->id,
            'rates' => $masterService->getJuminRates(self::MASTER_KIHU_YEAR, $companyId),
        ]);
    }

    public function tokureiMaster(Request $request, FurusatoMasterService $masterService)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);
        $companyId = $request->user()?->company_id;
        
        return view('tax.furusato.master.tokurei_master', [
            'dataId' => $data->id,
            'rates' => $masterService->getTokureiRates(self::MASTER_KIHU_YEAR, $companyId),
        ]);
    }

    public function shinkokutokureiMaster(Request $request, FurusatoMasterService $masterService)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);
        $companyId = $request->user()?->company_id;
        
        return view('tax.furusato.master.shinkokutokurei_master', [
            'dataId' => $data->id,
            'rates' => $masterService->getShinkokutokureiRates(self::MASTER_KIHU_YEAR, $companyId),
        ]);
    }

    private function getFurusatoInputPayload(Data $data): array
    {
        $payload = optional(FurusatoInput::query()
            ->where('data_id', $data->id)
            ->first())->payload;

        if (! is_array($payload)) {
            return [];
        }

        $normalizer = app(PayloadNormalizer::class);
        $payload = $normalizer->normalize($payload);

        $this->normalizeJotoIchijiKeys($payload);
        $this->normalizeFudosanSyunyuKeys($payload);
        $this->normalizeBunriChokiSyunyuKeys($payload);
        $this->normalizeBunriChokiShotokuKeys($payload);
        $this->normalizeBunriIncomeShotokuKeys($payload);
        $this->normalizeKojoRenamedKeys($payload);

        return $payload;
    }

    private function getStoredFurusatoResults(int $dataId): array
    {
        $payload = FurusatoResult::query()
            ->where('data_id', $dataId)
            ->value('payload');

        return is_array($payload) ? $payload : [];
    }

    private function storeFurusatoResults(Data $data, array $results): void
    {
        $userId = (int) auth()->id();

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

    private function validateBunriChokiShotokuInputs(Request $request): void
    {
        if (self::BUNRI_CHOKI_SHOTOKU_FIELDS === []) {
            return;
        }

        $rules = array_fill_keys(self::BUNRI_CHOKI_SHOTOKU_FIELDS, ['bail', 'nullable', 'integer']);

        Validator::make($request->only(self::BUNRI_CHOKI_SHOTOKU_FIELDS), $rules)->validate();
    }

    /**
     * @return array{int, int}
     */
    private function resolveShotokuGokei(int $dataId): array
    {
        $payload = [];

        $sessionResults = session('furusato_results');
        if (is_array($sessionResults)) {
            $candidate = $sessionResults['upper'] ?? $sessionResults;
            if (is_array($candidate)) {
                $payload = $candidate;
            }
        }

        if ($payload === []) {
            $storedResults = $this->getStoredFurusatoResults($dataId);
            $candidate = $storedResults['upper'] ?? $storedResults;
            if (is_array($candidate)) {
                $payload = $candidate;
            }
        }

        if ($payload === []) {
            $payloadFromInput = FurusatoInput::query()
                ->where('data_id', $dataId)
                ->value('payload');

            if (is_array($payloadFromInput)) {
                $this->normalizeJotoIchijiKeys($payloadFromInput);
                $this->normalizeKojoRenamedKeys($payloadFromInput);
                $payload = $payloadFromInput;
            }
        }

        $prev = $this->valueOrZero($this->toNullableInt($payload['shotoku_gokei_shotoku_prev'] ?? null));
        $curr = $this->valueOrZero($this->toNullableInt($payload['shotoku_gokei_shotoku_curr'] ?? null));

        if ($this->resolveBunriFlag($dataId) === 1) {
            $prev += $this->valueOrZero($this->toNullableInt($payload['bunri_sogo_gokeigaku_shotoku_prev'] ?? null));
            $curr += $this->valueOrZero($this->toNullableInt($payload['bunri_sogo_gokeigaku_shotoku_curr'] ?? null));
        }

        return [$prev, $curr];
    }

    private function sanitizeDetailPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->toNullableInt($value);
        }

        return $payload;
    }

    private function recalculateBunriJoto(array &$payload): void
    {
        $rows = [
            ['key' => 'tanki_ippan', 'group' => 'tanki'],
            ['key' => 'tanki_keigen', 'group' => 'tanki'],
            ['key' => 'choki_ippan', 'group' => 'choki'],
            ['key' => 'choki_tokutei', 'group' => 'choki'],
            ['key' => 'choki_keika', 'group' => 'choki'],
        ];

        foreach (['prev', 'curr'] as $period) {
            $sums = ['tanki' => 0, 'choki' => 0];

            foreach ($rows as $row) {
                $base = sprintf('%s_%s', $row['key'], $period);

                $syunyu = $this->valueOrZero($payload[sprintf('syunyu_%s', $base)] ?? null);
                $keihi = $this->valueOrZero($payload[sprintf('keihi_%s', $base)] ?? null);
                $sashihiki = $syunyu - $keihi;
                $payload[sprintf('sashihiki_%s', $base)] = $sashihiki;

                $tsusango = $this->valueOrZero($payload[sprintf('tsusango_%s', $base)] ?? null);
                $tokubetsu = $this->valueOrZero($payload[sprintf('tokubetsukojo_%s', $base)] ?? null);
                $jotoShotoku = $tsusango - $tokubetsu;
                $payload[sprintf('joto_shotoku_%s', $base)] = $jotoShotoku;

                $sums[$row['group']] += $jotoShotoku;
            }

            $payload[sprintf('joto_shotoku_tanki_gokei_%s', $period)] = $sums['tanki'];
            $payload[sprintf('joto_shotoku_choki_gokei_%s', $period)] = $sums['choki'];
        }
    }

    private function recalculateBunriKabuteki(array &$payload): void
    {
        $rows = [
            ['key' => 'ippan_joto', 'kurikoshi' => false],
            ['key' => 'jojo_joto', 'kurikoshi' => true],
            ['key' => 'jojo_haito', 'kurikoshi' => false],
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach ($rows as $row) {
                $base = sprintf('%s_%s', $row['key'], $period);

                $syunyu = $this->valueOrZero($payload[sprintf('syunyu_%s', $base)] ?? null);
                $keihi = $this->valueOrZero($payload[sprintf('keihi_%s', $base)] ?? null);
                $shotoku = $syunyu - $keihi;
                $payload[sprintf('shotoku_%s', $base)] = $shotoku;

                $tsusango = $this->valueOrZero($payload[sprintf('tsusango_%s', $base)] ?? null);
                $kurikoshi = $row['kurikoshi']
                    ? $this->valueOrZero($payload[sprintf('kurikoshi_%s', $base)] ?? null)
                    : 0;
                $payload[sprintf('shotoku_after_kurikoshi_%s', $base)] = $tsusango - $kurikoshi;
            }
        }
    }

    private function recalculateBunriSakimono(array &$payload): void
    {
        foreach (['prev', 'curr'] as $period) {
            $syunyu = $this->valueOrZero($payload[sprintf('syunyu_sakimono_%s', $period)] ?? null);
            $keihi = $this->valueOrZero($payload[sprintf('keihi_sakimono_%s', $period)] ?? null);
            $shotoku = $syunyu - $keihi;
            $payload[sprintf('shotoku_sakimono_%s', $period)] = $shotoku;

            $kurikoshi = $this->valueOrZero($payload[sprintf('kurikoshi_sakimono_%s', $period)] ?? null);
            $payload[sprintf('shotoku_sakimono_after_kurikoshi_%s', $period)] = $shotoku - $kurikoshi;
        }
    }

    private function recalculateBunriSanrin(array &$payload): void
    {
        foreach (['prev', 'curr'] as $period) {
            $syunyu = $this->valueOrZero($payload[sprintf('syunyu_sanrin_%s', $period)] ?? null);
            $keihi = $this->valueOrZero($payload[sprintf('keihi_sanrin_%s', $period)] ?? null);
            $sashihiki = $syunyu - $keihi;
            $payload[sprintf('sashihiki_sanrin_%s', $period)] = $sashihiki;

            $tokubetsu = $this->valueOrZero($payload[sprintf('tokubetsukojo_sanrin_%s', $period)] ?? null);
            $payload[sprintf('shotoku_sanrin_%s', $period)] = $sashihiki - $tokubetsu;
        }
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
     * @param array<int, string> $fields
     * @return array<string, string|null>
     */
    private function validateAndNormalizeLabels(Request $request, array $fields): array
    {
        $inputs = [];
        foreach ($fields as $field) {
            $inputs[$field] = $request->input($field);
        }

        $rules = array_fill_keys($fields, ['bail', 'nullable', 'string', 'max:64']);

        Validator::make($inputs, $rules)->validate();

        $normalized = [];
        foreach ($fields as $field) {
            $value = $inputs[$field];

            if ($value === null) {
                $normalized[$field] = null;
                continue;
            }

            $trimmed = trim((string) $value);
            $normalized[$field] = $trimmed === '' ? null : $trimmed;
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, string|null>
     */
    private function extractStoredLabels(?FurusatoInput $record, array $fields): array
    {
        $labels = [];

        if (! $record) {
            return $labels;
        }

        foreach ($fields as $field) {
            $labels[$field] = $record->{$field};
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function buildOriginQuery(Request $request): array
    {
        $query = [];

        $tab = $request->input('origin_tab');
        if (is_string($tab) && trim($tab) === 'input') {
            $query['origin_tab'] = 'input';
        }

        $anchor = $this->sanitizeOriginAnchor($request->input('origin_anchor'));
        if ($anchor !== '') {
            $query['origin_anchor'] = $anchor;
        }

        return $query;
    }
    
    private function normalizeBunriChokiSyunyuKeys(array &$payload): void
    {
        $types = ['tokutei', 'keika'];
        $taxes = ['shotoku', 'jumin'];
        $periods = ['prev', 'curr'];

        foreach ($types as $type) {
            foreach ($taxes as $tax) {
                foreach ($periods as $period) {
                    $canonicalKey = sprintf('bunri_syunyu_choki_%s_%s_%s', $type, $tax, $period);
                    $canonicalExists = array_key_exists($canonicalKey, $payload);
                    $canonicalValue = $canonicalExists ? $this->toNullableInt($payload[$canonicalKey]) : null;

                    if ($canonicalExists) {
                        $payload[$canonicalKey] = $canonicalValue;
                    }

                    $legacyKeys = [
                        sprintf('bunri_syunyu_choki_%s_over_%s_%s', $type, $tax, $period),
                        sprintf('bunri_syunyu_choki_%s_under_%s_%s', $type, $tax, $period),
                    ];

                    $legacySum = null;
                    $hasLegacy = false;

                    foreach ($legacyKeys as $legacyKey) {
                        if (! array_key_exists($legacyKey, $payload)) {
                            continue;
                        }

                        $hasLegacy = true;
                        $value = $this->toNullableInt($payload[$legacyKey]) ?? 0;
                        $legacySum = ($legacySum ?? 0) + $value;
                        unset($payload[$legacyKey]);
                    }

                    if ($canonicalValue !== null) {
                        continue;
                    }

                    if ($hasLegacy) {
                        $payload[$canonicalKey] = $legacySum ?? 0;
                    }
                }
            }
        }
    }

    private function normalizeBunriChokiShotokuKeys(array &$payload): void
    {
        $types = ['tokutei', 'keika'];
        $taxes = ['shotoku', 'jumin'];
        $periods = ['prev', 'curr'];

        foreach ($types as $type) {
            foreach ($taxes as $tax) {
                foreach ($periods as $period) {
                    $canonicalKey = sprintf('bunri_shotoku_choki_%s_%s_%s', $type, $tax, $period);
                    $canonicalExists = array_key_exists($canonicalKey, $payload);
                    $canonicalValue = $canonicalExists ? $this->toNullableInt($payload[$canonicalKey]) : null;

                    if ($canonicalExists) {
                        $payload[$canonicalKey] = $canonicalValue;
                    }

                    $legacyKeys = [
                        sprintf('bunri_shotoku_choki_%s_over_%s_%s', $type, $tax, $period),
                        sprintf('bunri_shotoku_choki_%s_under_%s_%s', $type, $tax, $period),
                        sprintf('bunri_choki_%s_over_%s_%s', $type, $tax, $period),
                        sprintf('bunri_choki_%s_under_%s_%s', $type, $tax, $period),
                    ];

                    if ($tax === 'shotoku') {
                        $legacyKeys[] = sprintf('bunri_choki_%s_shotoku_%s', $type, $period);
                    }

                    $legacySum = null;
                    $hasLegacy = false;

                    foreach ($legacyKeys as $legacyKey) {
                        if (! array_key_exists($legacyKey, $payload)) {
                            continue;
                        }

                        $hasLegacy = true;
                        $value = $this->toNullableInt($payload[$legacyKey]) ?? 0;
                        $legacySum = ($legacySum ?? 0) + $value;
                        unset($payload[$legacyKey]);
                    }

                    if ($canonicalValue !== null) {
                        continue;
                    }

                    if ($hasLegacy) {
                        $payload[$canonicalKey] = $legacySum ?? 0;
                    }
                }
            }
        }
    }

    private function normalizeFudosanSyunyuKeys(array &$payload): void
    {
        foreach (['prev', 'curr'] as $period) {
            $canonicalKey = sprintf('fudosan_syunyu_%s', $period);
            $legacyKey = sprintf('fudosan_shunyu_%s', $period);

            $canonicalExists = array_key_exists($canonicalKey, $payload);
            if ($canonicalExists) {
                $payload[$canonicalKey] = $this->toNullableInt($payload[$canonicalKey]);
            }

            if (! array_key_exists($legacyKey, $payload)) {
                continue;
            }

            $legacyValue = $this->toNullableInt($payload[$legacyKey]);
            unset($payload[$legacyKey]);

            if ($canonicalExists && $payload[$canonicalKey] !== null) {
                continue;
            }

            $payload[$canonicalKey] = $legacyValue;
        }
    }

    private function normalizeBunriIncomeShotokuKeys(array &$payload): void
    {
        foreach (['prev', 'curr'] as $period) {
            $tokuteiJuminKey = sprintf('bunri_choki_tokutei_jumin_%s', $period);
            if (array_key_exists($tokuteiJuminKey, $payload)) {
                $value = $this->toNullableInt($payload[$tokuteiJuminKey]);
                $canonical = sprintf('bunri_shotoku_choki_tokutei_jumin_%s', $period);
                if (! array_key_exists($canonical, $payload)) {
                    $payload[$canonical] = $value;
                }
                unset($payload[$tokuteiJuminKey]);
            }

            $keikaJuminKey = sprintf('bunri_choki_keika_jumin_%s', $period);
            if (array_key_exists($keikaJuminKey, $payload)) {
                $value = $this->toNullableInt($payload[$keikaJuminKey]);
                $canonical = sprintf('bunri_shotoku_choki_keika_jumin_%s', $period);
                if (! array_key_exists($canonical, $payload)) {
                    $payload[$canonical] = $value;
                }
                unset($payload[$keikaJuminKey]);
            }
        }

        $parts = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
            'ippan_kabuteki_joto',
            'jojo_kabuteki_joto',
            'jojo_kabuteki_haito',
            'sakimono',
            'sanrin',
            'taishoku',
        ];

        foreach ($parts as $part) {
            foreach (['shotoku', 'jumin'] as $tax) {
                foreach (['prev', 'curr'] as $period) {
                    $canonicalKey = sprintf('bunri_shotoku_%s_%s_%s', $part, $tax, $period);

                    if (array_key_exists($canonicalKey, $payload)) {
                        $payload[$canonicalKey] = $this->toNullableInt($payload[$canonicalKey]);
                        continue;
                    }

                    $legacyKey = sprintf('bunri_%s_%s_%s', $part, $tax, $period);
                    if (! array_key_exists($legacyKey, $payload)) {
                        continue;
                    }

                    $payload[$canonicalKey] = $this->toNullableInt($payload[$legacyKey]);
                    unset($payload[$legacyKey]);
                }
            }
        }
    }

    private function normalizeJotoIchijiKeys(array &$payload): void
    {
        $mapping = [];

        foreach (['shotoku', 'jumin'] as $tax) {
            foreach (['prev', 'curr'] as $period) {
                $old = sprintf('shotoku_ichiji_%s_%s', $tax, $period);
                $new = sprintf('shotoku_joto_ichiji_%s_%s', $tax, $period);
                $mapping[$old] = $new;
            }
        }

        foreach ($mapping as $old => $new) {
            if (! array_key_exists($old, $payload)) {
                continue;
            }

            if (! array_key_exists($new, $payload)) {
                $payload[$new] = $payload[$old];
            }

            unset($payload[$old]);
        }
    }

    private function performFullRecalculation(
        Request $request,
        Data $data,
        array $updates,
        ?RecalculateFurusatoPayload $useCase = null
    ): void {
        $this->runRecalculationPipeline(
            $request,
            $data,
            $updates,
            ['should_flash_results' => true],
            $useCase,
        );
    }

    private function runRecalculationPipeline(
        Request $request,
        Data $data,
        array $updates,
        array $ctx = [],
        ?RecalculateFurusatoPayload $useCase = null
    ): array {
        $recalculateUseCase = $useCase ?? app(RecalculateFurusatoPayload::class);

        $ctx = array_merge(
            [
                'guest_birth_date' => $this->normalizeBirthDateForContext($data->guest?->birth_date ?? null),
            ],
            $ctx,
        );

        $user = $request->user();
        $userId = $user ? (int) $user->id : null;

        if ($userId !== null) {
            $ctx['user_id'] = $userId;
        }

        $result = $recalculateUseCase->handle($data, $updates, $ctx);

        $this->logRecalculation($data->id, $userId, array_keys($updates));

        return $result;
    }

    private function redirectAfterGoto(Request $request, Data $data, string $goto, string $message): RedirectResponse
    {
        $routeParams = ['data_id' => $data->id];
        $originQuery = $this->buildOriginQuery($request);
        $anchor = $this->sanitizeOriginAnchor($request->input('origin_anchor'));

        switch ($goto) {
            case 'syori':
                return redirect()->route('furusato.syori', $routeParams)->with('success', $message);
            case 'master':
                return redirect()->route('furusato.master', $routeParams)->with('success', $message);
            case 'jigyo':
                return redirect()->route('furusato.details.jigyo', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'fudosan':
                return redirect()->route('furusato.details.fudosan', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'kifukin_details':
                return redirect()->route('furusato.details.kifukin', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'joto_ichiji':
                return redirect()->route('furusato.details.joto_ichiji', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'kojo_seimei_jishin':
                return redirect()->route('furusato.details.kojo_seimei_jishin', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'kojo_jinteki':
                return redirect()->route('furusato.details.kojo_jinteki', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'kojo_iryo':
                return redirect()->route('furusato.details.kojo_iryo', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'bunri_joto':
                return redirect()->route('furusato.details.bunri_joto', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'bunri_kabuteki':
                return redirect()->route('furusato.details.bunri_kabuteki', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'bunri_sakimono':
                return redirect()->route('furusato.details.bunri_sakimono', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'bunri_sanrin':
                return redirect()->route('furusato.details.bunri_sanrin', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'input':
            case '':
                return $this->redirectToInputWithAnchor($data, $anchor, $message);
            default:
                return $this->redirectToInputWithAnchor($data, $anchor, $message);
        }
    }

    private function logRecalculation(int $dataId, ?int $userId, array $keys): void
    {
        $filtered = [];
        foreach ($keys as $key) {
            if (is_string($key) && $key !== '') {
                $filtered[] = $key;
            }
        }

        sort($filtered);

        $message = sprintf(
            '[Recalc] data_id=%d, user=%s, changed_keys=[%s]',
            $dataId,
            $userId !== null ? $userId : 'guest',
            implode(',', $filtered)
        );

        Log::info($message);
    }

    private function sanitizeOriginAnchor($anchor): string
    {
        if (! is_string($anchor)) {
            return '';
        }

        $anchor = trim($anchor);
        if ($anchor === '') {
            return '';
        }

        $filtered = preg_replace('/[^A-Za-z0-9_-]/', '', $anchor);

        return $filtered !== null ? $filtered : '';
    }

    private function redirectToInputWithAnchor(Data $data, string $anchor = '', string $message = '保存しました'): RedirectResponse
    {
        $redirect = redirect()->route('furusato.input', ['data_id' => $data->id])
                              ->with('success', $message);

        if ($anchor !== '') {
            $redirect->withFragment($anchor);
        }

        return $redirect;
    }

    private function calculateJigyoEigyo(array $inputs): array
    {
        $keihiFields = [
            'jigyo_eigyo_keihi_1',
            'jigyo_eigyo_keihi_2',
            'jigyo_eigyo_keihi_3',
            'jigyo_eigyo_keihi_4',
            'jigyo_eigyo_keihi_5',
            'jigyo_eigyo_keihi_6',
            'jigyo_eigyo_keihi_7',
            'jigyo_eigyo_keihi_sonota',
        ];

        $result = [];

        foreach (['prev', 'curr'] as $period) {
            $uriage = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_uriage_%s', $period)] ?? null);
            $urigenka = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_urigenka_%s', $period)] ?? null);
            $sashihiki1 = $uriage - $urigenka;
            $result[sprintf('jigyo_eigyo_sashihiki_1_%s', $period)] = $sashihiki1;

            $keihiTotal = 0;
            foreach ($keihiFields as $field) {
                $keihiTotal += $this->valueOrZero($inputs[sprintf('%s_%s', $field, $period)] ?? null);
            }
            $result[sprintf('jigyo_eigyo_keihi_gokei_%s', $period)] = $keihiTotal;

            $sashihiki2 = $sashihiki1 - $keihiTotal;
            $result[sprintf('jigyo_eigyo_sashihiki_2_%s', $period)] = $sashihiki2;

            $senjuusha = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_senjuusha_kyuyo_%s', $period)] ?? null);
            $mae = $sashihiki2 - $senjuusha;
            $result[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_mae_%s', $period)] = $mae;

            $tokubetsuKojo = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);
            $result[sprintf('jigyo_eigyo_shotoku_%s', $period)] = $mae - $tokubetsuKojo;
        }

        foreach ($result as $key => $value) {
            $result[$key] = (int) $value;
        }

        return $result;
    }

    private function calculateFudosan(array $inputs): array
    {
        $keihiFields = [
            'fudosan_keihi_1',
            'fudosan_keihi_2',
            'fudosan_keihi_3',
            'fudosan_keihi_4',
            'fudosan_keihi_5',
            'fudosan_keihi_6',
            'fudosan_keihi_7',
            'fudosan_keihi_sonota',
        ];

        $result = [];

        foreach (['prev', 'curr'] as $period) {
            $shunyuKey = sprintf('fudosan_syunyu_%s', $period);
            $legacyKey = sprintf('fudosan_shunyu_%s', $period);
            $shunyuSource = $inputs[$shunyuKey] ?? ($inputs[$legacyKey] ?? null);
            $shunyu = $this->valueOrZero($this->toNullableInt($shunyuSource));

            $keihiTotal = 0;
            foreach ($keihiFields as $field) {
                $keihiTotal += $this->valueOrZero($inputs[sprintf('%s_%s', $field, $period)] ?? null);
            }
            $result[sprintf('fudosan_keihi_gokei_%s', $period)] = $keihiTotal;

            $sashihiki = $shunyu - $keihiTotal;
            $result[sprintf('fudosan_sashihiki_%s', $period)] = $sashihiki;

            $senjuusha = $this->valueOrZero($inputs[sprintf('fudosan_senjuusha_kyuyo_%s', $period)] ?? null);
            $mae = $sashihiki - $senjuusha;
            $result[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $period)] = $mae;

            $tokubetsuKojo = $this->valueOrZero($inputs[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);
            $result[sprintf('fudosan_shotoku_%s', $period)] = $mae - $tokubetsuKojo;
        }

        foreach ($result as $key => $value) {
            $result[$key] = (int) $value;
        }

        return $result;
    }

    private function formatKojoFieldName(string $base, string $tax, string $period): string
    {
        $override = self::KOJO_FIELD_OVERRIDES[$base][$tax] ?? null;

        if ($override) {
            return sprintf($override, $period);
        }

        return sprintf('%s_%s_%s', $base, $tax, $period);
    }

    private function normalizeKojoRenamedKeys(array &$payload, bool $removeLegacy = false): void
    {
        $periods = ['prev', 'curr'];
        $mappings = [
            'shotokuzei_zeigakukojo_seitoto_tokubetsu_%s' => 'tax_seito_shotoku_%s',
            'juminzei_zeigakukojo_seitoto_tokubetsu_%s' => 'tax_seito_jumin_%s',
            'shotokuzei_kojo_kifukin_%s' => 'kojo_kifukin_shotoku_%s',
            'juminzei_kojo_kifukin_%s' => 'kojo_kifukin_jumin_%s',
            'shotokuzei_kojo_kiso_%s' => 'kojo_kiso_shotoku_%s',
            'juminzei_kojo_kiso_%s' => 'kojo_kiso_jumin_%s',
            'kojo_shogaisyo_shotoku_%s' => 'kojo_shogaisha_shotoku_%s',
            'kojo_shogaisyo_jumin_%s' => 'kojo_shogaisha_jumin_%s',
        ];

        foreach ($mappings as $canonicalFormat => $legacyFormat) {
            foreach ($periods as $period) {
                $canonicalKey = sprintf($canonicalFormat, $period);
                $legacyKey = sprintf($legacyFormat, $period);

                $canonicalExists = array_key_exists($canonicalKey, $payload);
                $legacyExists = array_key_exists($legacyKey, $payload);

                $canonicalValue = $canonicalExists ? $this->toNullableInt($payload[$canonicalKey]) : null;
                $legacyValue = $legacyExists ? $this->toNullableInt($payload[$legacyKey]) : null;

                $normalized = $canonicalValue;
                if ($normalized === null && $legacyExists) {
                    $normalized = $legacyValue;
                }

                if ($canonicalExists || $legacyExists) {
                    $payload[$canonicalKey] = $normalized;

                    if ($removeLegacy) {
                        unset($payload[$legacyKey]);
                    } else {
                        $payload[$legacyKey] = $normalized;
                    }
                }
            }
        }
    }

    private function getSyoriSettings(int $dataId): array
    {
        $payload = FurusatoSyoriSetting::query()
            ->where('data_id', $dataId)
            ->value('payload');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param iterable<int, array<string, mixed>|object> $rates
     */
    private function calculateShotokuTaxAmount(iterable $rates, int $taxable): int
    {
        $amount = max(0, $taxable);

        foreach ($rates as $rate) {
            $data = is_array($rate) ? $rate : (array) $rate;

            $lower = (int) ($data['lower'] ?? 0);
            $upper = array_key_exists('upper', $data) ? $data['upper'] : null;

            if ($amount < $lower) {
                continue;
            }

            if ($upper !== null && $amount > $upper) {
                continue;
            }

            $rateDecimal = (float) ($data['rate'] ?? 0) / 100;
            $deduction = (int) ($data['deduction_amount'] ?? 0);
            $value = $amount * $rateDecimal - $deduction;

            return (int) $value;
        }

        return 0;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    private function valueOrZero(?int $value): int
    {
        return $value ?? 0;
    }

    private function resolveTaxableBase(array $payload, array $syoriSettings, string $period): int
    {
        $flagKey = sprintf('bunri_flag_%s', $period);
        $flag = $syoriSettings[$flagKey] ?? ($syoriSettings['bunri_flag'] ?? 0);
        $isSeparated = (int) $flag === 1;

        $key = $isSeparated
            ? sprintf('bunri_kazeishotoku_sogo_shotoku_%s', $period)
            : sprintf('tax_kazeishotoku_shotoku_%s', $period);

        $raw = $this->toNullableInt($payload[$key] ?? null);

        if ($raw === null) {
            return 0;
        }

        return $raw;
    }

    private function floorToThousands(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return (int) (floor($value / 1000) * 1000);
    }

    private function resolveAuthorizedDataOrFail(Request $request, string $ability = 'view'): Data
    {
        $id = (int) ($request->input('data_id') ?? $request->query('data_id'));
        abort_unless($id > 0, 422, 'data_id が指定されていません。');

        $data = Data::with('guest')->findOrFail($id);
        $me = $request->user();

        if (! $me) {
            throw new AuthenticationException();
        }

        if ((int) $data->company_id !== (int) ($me->company_id ?? 0)) {
            abort(403);
        }

        $role = strtolower((string) ($me->role ?? ''));
        $isOwnerOrRegistrar = (method_exists($me, 'isOwner') && $me->isOwner()) || in_array($role, ['owner', 'registrar'], true);

        if (! $isOwnerOrRegistrar && (int) $data->group_id !== (int) ($me->group_id ?? 0)) {
            abort(403);
        }

        return $data;
    }

    private function resolveCompanyScopedDataOrFail(Request $request): Data
    {
        $id = (int) ($request->input('data_id') ?? $request->query('data_id'));
        abort_unless($id > 0, 422, 'data_id が指定されていません。');

        $data = Data::with('guest')->findOrFail($id);
        $me = $request->user();

        if (! $me) {
            throw new AuthenticationException();
        }


        if ((int) $data->company_id !== (int) ($me->company_id ?? 0)) {
            abort(403);
        }

        return $data;
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