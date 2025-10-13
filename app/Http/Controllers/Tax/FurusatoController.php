<?php

namespace App\Http\Controllers\Tax;

use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\Support\FurusatoMasterSheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\FurusatoInputRequest;
use App\Http\Requests\Tax\FurusatoSyoriRequest;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Models\FurusatoSyoriSetting;
use App\Models\TokureiRate;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\FurusatoMasterService;
use App\Services\Tax\Kojo\HaigushaKojoService;
use App\Services\Tax\Kojo\JintekiKojoService;
use App\Services\Tax\Kojo\KifukinShotokuKojoService;
use App\Services\Tax\Kojo\KihonService;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
use App\Services\Tax\Result\FurusatoResultService;
use App\Services\Tax\Result\Rate\TokureiRateService;
use App\Services\Tax\Result\Support\PayloadAccessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

final class FurusatoController extends Controller
{
    private const MASTER_KIHU_YEAR = 2025;

    private const BUNRI_CHOKI_SHOTOKU_FIELDS = [
        'bunri_choki_tokutei_under_shotoku_prev',
        'bunri_choki_tokutei_under_shotoku_curr',
        'bunri_choki_tokutei_over_shotoku_prev',
        'bunri_choki_tokutei_over_shotoku_curr',
        'bunri_choki_keika_under_shotoku_prev',
        'bunri_choki_keika_under_shotoku_curr',
        'bunri_choki_keika_over_shotoku_prev',
        'bunri_choki_keika_over_shotoku_curr',
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

    public function index(Request $req)
    {
        $dataId = $req->integer('data_id') ?: null;
        if ($dataId) {
            session(['selected_data_id' => $dataId]);
        }

        $context = $this->makeInputContext($req, $dataId);
        $context['out'] = ['inputs' => $context['savedInputs']];

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
        $context['out'] = ['inputs' => array_replace($context['savedInputs'], $dto->toArray())];
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

        if ($dataId) {
            $data = $this->findDataForInput($request, $dataId);

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
                $this->normalizeBunriChokiShotokuKeys($savedInputs);
                $this->normalizeKojoRenamedKeys($savedInputs);
            }
        }

        $companyId = $request->user()?->company_id;
        if ($companyId === null && $data) {
            $companyId = $data->company_id;
        }
        $companyId = $companyId !== null ? (int) $companyId : null;

        $yearForRate = (int) ($kihuYear ?? self::MASTER_KIHU_YEAR);
        $companyIdForRate = $companyId;

        $shotokuRates = app(FurusatoMasterService::class)
            ->getShotokuRates(self::MASTER_KIHU_YEAR, $companyId);

        $jintekiDiff = $this->computeJintekiDiff($savedInputs);

        $taxablePrev = $this->valueOrZero($this->toNullableInt($savedInputs['tax_kazeishotoku_shotoku_prev'] ?? null));
        $taxableCurr = $this->valueOrZero($this->toNullableInt($savedInputs['tax_kazeishotoku_shotoku_curr'] ?? null));

        $jintekiSumPrev = $this->valueOrZero($this->toNullableInt($jintekiDiff['sum']['prev'] ?? null));
        $jintekiSumCurr = $this->valueOrZero($this->toNullableInt($jintekiDiff['sum']['curr'] ?? null));

        $adjustedTaxablePrev = max(0, $taxablePrev - $jintekiSumPrev);
        $adjustedTaxableCurr = max(0, $taxableCurr - $jintekiSumCurr);

        $jintekiDiff['adjusted_taxable'] = [
            'prev' => $adjustedTaxablePrev,
            'curr' => $adjustedTaxableCurr,
        ];

        $targetYear = (int) ($kihuYear ?? self::MASTER_KIHU_YEAR);

        $tokureiStandardRate = [
            'prev' => $this->lookupTokureiStandardRate($adjustedTaxablePrev, $companyId, $targetYear),
            'curr' => $this->lookupTokureiStandardRate($adjustedTaxableCurr, $companyId, $targetYear),
        ];

        $sanrinBasePrev = PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_sanrin_shotoku_prev') ?? 0;
        $sanrinBaseCurr = PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_sanrin_shotoku_curr') ?? 0;
        $taishokuBasePrev = PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_taishoku_shotoku_prev') ?? 0;
        $taishokuBaseCurr = PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_taishoku_shotoku_curr') ?? 0;

        $hasSanrinPrev = $sanrinBasePrev > 0;
        $hasSanrinCurr = $sanrinBaseCurr > 0;
        $hasTaishokuPrev = $taishokuBasePrev > 0;
        $hasTaishokuCurr = $taishokuBaseCurr > 0;

        $hasBunriPrev = (
            (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_tanki_shotoku_prev') ?? 0) > 0
            || (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_choki_shotoku_prev') ?? 0) > 0
            || (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_haito_shotoku_prev') ?? 0) > 0
            || (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_joto_shotoku_prev') ?? 0) > 0
        );
        $hasBunriCurr = (
            (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_tanki_shotoku_curr') ?? 0) > 0
            || (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_choki_shotoku_curr') ?? 0) > 0
            || (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_haito_shotoku_curr') ?? 0) > 0
            || (PayloadAccessor::intOrNull($savedInputs, 'bunri_kazeishotoku_joto_shotoku_curr') ?? 0) > 0
        );

        $sanrinPrevPct = $hasSanrinPrev
            ? $this->calcSanrinDiv5Percent($savedInputs, 'prev', $yearForRate, $companyIdForRate)
            : null;
        $sanrinCurrPct = $hasSanrinCurr
            ? $this->calcSanrinDiv5Percent($savedInputs, 'curr', $yearForRate, $companyIdForRate)
            : null;

        $taishokuPrevPct = $hasTaishokuPrev
            ? $this->calcTaishokuPercent($savedInputs, 'prev', $yearForRate, $companyIdForRate)
            : null;
        $taishokuCurrPct = $hasTaishokuCurr
            ? $this->calcTaishokuPercent($savedInputs, 'curr', $yearForRate, $companyIdForRate)
            : null;

        $adoptedPrevPct = ($sanrinPrevPct !== null || $taishokuPrevPct !== null)
            ? $this->calcAdoptedPercent($sanrinPrevPct, $taishokuPrevPct)
            : null;
        $adoptedCurrPct = ($sanrinCurrPct !== null || $taishokuCurrPct !== null)
            ? $this->calcAdoptedPercent($sanrinCurrPct, $taishokuCurrPct)
            : null;

        $bunriMinPrevPct = $hasBunriPrev ? $this->calcBunriMinPercent($savedInputs, 'prev') : null;
        $bunriMinCurrPct = $hasBunriCurr ? $this->calcBunriMinPercent($savedInputs, 'curr') : null;

        $stdPrevPct = $tokureiStandardRate['prev'] ?? null;
        $stdCurrPct = $tokureiStandardRate['curr'] ?? null;

        $finalPrevPct = $this->calcFinalPercent(
            $savedInputs,
            'prev',
            $yearForRate,
            $companyIdForRate,
            $stdPrevPct,
            $adoptedPrevPct,
            $bunriMinPrevPct,
            $adjustedTaxablePrev,
        );
        $finalCurrPct = $this->calcFinalPercent(
            $savedInputs,
            'curr',
            $yearForRate,
            $companyIdForRate,
            $stdCurrPct,
            $adoptedCurrPct,
            $bunriMinCurrPct,
            $adjustedTaxableCurr,
        );

        $tokureiComputedPercent = [
            'standard_prev' => $stdPrevPct,
            'standard_curr' => $stdCurrPct,
            'ninety_prev' => 90.000,
            'ninety_curr' => 90.000,
            'sanrin_prev' => $sanrinPrevPct,
            'sanrin_curr' => $sanrinCurrPct,
            'taishoku_prev' => $taishokuPrevPct,
            'taishoku_curr' => $taishokuCurrPct,
            'adopted_prev' => $adoptedPrevPct,
            'adopted_curr' => $adoptedCurrPct,
            'bunri_min_prev' => $bunriMinPrevPct,
            'bunri_min_curr' => $bunriMinCurrPct,
            'final_prev' => $finalPrevPct,
            'final_curr' => $finalCurrPct,
        ];

        /** @var FurusatoResultService $resultService */
        $resultService = app(FurusatoResultService::class);
        $previewResults = $resultService->buildFromPayload($targetYear, $companyId, $savedInputs);

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

        return $context;
    }

    /**
     * TokureiRate の標準レンジから lower ≤ amount の最大一致で tokurei_deduction_rate(%) を返す。
     *
     * @param  int  $amount  課税総所得-人的控除差調整額などの金額（千円未満は切り捨て済み想定）
     */
    private function tokureiPercentForAmount(int $kihuYear, ?int $companyId, int $amount): ?float
    {
        /** @var TokureiRateService $svc */
        $svc = app(TokureiRateService::class);
        $rows = $svc->getRows($kihuYear, $companyId);
        if ($amount <= 0) {
            return null;
        }

        $rate = $svc->lowerBoundRate($amount, $rows);

        return $rate !== null ? round($rate * 100, 3) : null;
    }

    /** 千円未満切捨て（負値は 0 とみなす） */
    private function floorToThousands(mixed $n): int
    {
        $v = (int) floor((float) ($n ?? 0));
        if ($v <= 0) {
            return 0;
        }

        return $v - ($v % 1000);
    }

    /**
     * 特例率 bundle を再計算し、payload 更新用の配列を返す（百分率）。無効時は null（キーは入れる）。
     *
     * @param  array<string, mixed>  $basePayload
     * @return array<string, float|null>
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
            $candidates = [];
            $stdKey = sprintf('tokurei_rate_standard_%s', $period);
            $standardRaw = $basePayload[$stdKey] ?? null;
            if (is_string($standardRaw)) {
                $standardRaw = trim($standardRaw);
                if ($standardRaw === '') {
                    $standardRaw = null;
                }
            }
            if ($standardRaw !== null) {
                $candidates[] = (float) $standardRaw;
            }

            $candidates[] = 90.000;

            $adopted = $result[sprintf('tokurei_rate_adopted_%s', $period)];
            if ($adopted !== null) {
                $candidates[] = $adopted;
            }

            $bunri = $result[sprintf('tokurei_rate_bunri_min_%s', $period)];
            if ($bunri !== null) {
                $candidates[] = $bunri;
            }

            if ($candidates !== []) {
                $result[sprintf('tokurei_rate_final_%s', $period)] = min($candidates);
            }
        }

        return $result;
    }

    /**
     * 山林(1/5)ベースの特例控除率（百分率）を求める。
     */
    private function calcSanrinDiv5Percent(array $payload, string $period, int $kihuYear, ?int $companyId): ?float
    {
        $key = sprintf('bunri_kazeishotoku_sanrin_shotoku_%s', $period);
        $raw = PayloadAccessor::intOrNull($payload, $key);
        if ($raw === null || $raw <= 0) {
            return null;
        }

        $divided = $this->floorToThousands($raw / 5.0);
        if ($divided <= 0) {
            return null;
        }

        return $this->tokureiPercentForAmount($kihuYear, $companyId, $divided);
    }

    private function calcTaishokuPercent(array $payload, string $period, int $kihuYear, ?int $companyId): ?float
    {
        $key = sprintf('bunri_kazeishotoku_taishoku_shotoku_%s', $period);
        $raw = PayloadAccessor::intOrNull($payload, $key);
        if ($raw === null || $raw <= 0) {
            return null;
        }

        $amount = $this->floorToThousands($raw);
        if ($amount <= 0) {
            return null;
        }

        return $this->tokureiPercentForAmount($kihuYear, $companyId, $amount);
    }

    private function calcAdoptedPercent(?float $sanrinPct, ?float $taishokuPct): ?float
    {
        if ($sanrinPct === null && $taishokuPct === null) {
            return null;
        }

        $a = $sanrinPct ?? 100.000;
        $b = $taishokuPct ?? 100.000;

        return min($a, $b);
    }

    private function calcBunriMinPercent(array $payload, string $period): ?float
    {
        $tankiKey = sprintf('bunri_kazeishotoku_tanki_shotoku_%s', $period);
        $tanki = PayloadAccessor::intOrNull($payload, $tankiKey) ?? 0;
        if ($tanki > 0) {
            return 59.370;
        }

        $others = [
            sprintf('bunri_kazeishotoku_choki_shotoku_%s', $period),
            sprintf('bunri_kazeishotoku_haito_shotoku_%s', $period),
            sprintf('bunri_kazeishotoku_joto_shotoku_%s', $period),
        ];

        foreach ($others as $key) {
            $value = PayloadAccessor::intOrNull($payload, $key) ?? 0;
            if ($value > 0) {
                return 74.685;
            }
        }

        return null;
    }

    private function calcFinalPercent(
        array $payload,
        string $period,
        int $kihuYear,
        ?int $companyId,
        ?float $standardPct,
        ?float $adoptedPct,
        ?float $bunriMinPct,
        int $adjustedTaxable
    ): ?float {
        $taxShotoku = PayloadAccessor::intOrNull($payload, sprintf('tax_kazeishotoku_shotoku_%s', $period)) ?? 0;
        $sanrinShotoku = PayloadAccessor::intOrNull($payload, sprintf('bunri_kazeishotoku_sanrin_shotoku_%s', $period)) ?? 0;
        $taishokuShotoku = PayloadAccessor::intOrNull($payload, sprintf('bunri_kazeishotoku_taishoku_shotoku_%s', $period)) ?? 0;

        if ($taxShotoku === 0 && $sanrinShotoku === 0 && $taishokuShotoku === 0) {
            return 90.000;
        }

        $candidates = [];

        if ($standardPct !== null) {
            $candidates[] = $standardPct;
        }

        $humanDiffSumKey = sprintf('human_diff_sum_%s', $period);
        $humanDiffSum = PayloadAccessor::intOrNull($payload, $humanDiffSumKey) ?? 0;
        $aa49IsNegative = $adjustedTaxable === 0 && (($taxShotoku - $humanDiffSum) < 0);
        if ($aa49IsNegative && $sanrinShotoku === 0 && $taishokuShotoku === 0) {
            $candidates[] = 90.000;
        }

        if ($adoptedPct !== null) {
            $candidates[] = $adoptedPct;
        }

        if ($bunriMinPct !== null) {
            $candidates[] = $bunriMinPct;
        }

        if ($candidates === []) {
            return null;
        }

        return min($candidates);
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

    private function lookupTokureiStandardRate(int $amount, ?int $companyId, int $kihuYear): ?float
    {
        $x = max(0, $amount);

        $row = TokureiRate::query()
            ->where('kihu_year', $kihuYear)
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id');

                if ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->whereNotNull('lower')
            ->where('lower', '<=', $x)
            ->orderByDesc('lower')
            ->first();

        return $row ? (float) $row->tokurei_deduction_rate : null;
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

    public function save(Request $request, FurusatoResultService $resultService): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $updates = $request->except([
            '_token',
            'data_id',
            'redirect_to',
            'show_result',
            'origin_tab',
            'origin_anchor',
        ]);
        $this->validateBunriChokiShotokuInputs($request);

        $existing = $this->getFurusatoInputPayload($data);
        $merged = array_replace($existing, $updates);

        $kihuYear = self::MASTER_KIHU_YEAR;
        $companyId = $request->user()?->company_id;
        $companyId = $companyId !== null ? (int) $companyId : null;

        $tokureiBundle = $this->computeTokureiPercentBundle($merged, $kihuYear, $companyId);
        $updates = array_replace($updates, $tokureiBundle);

        $this->updateFurusatoInputPayload($data, $updates);

        $goto = (string) $request->input('redirect_to', '');
        $shouldShowResult = $request->boolean('show_result') || $goto === '' || $goto === 'input';

        if ($shouldShowResult) {
            $payload = $this->getFurusatoInputPayload($data);
            $kihuYear = self::MASTER_KIHU_YEAR;
            $companyId = $request->user()?->company_id;
            $companyId = $companyId !== null ? (int) $companyId : null;
            $results = $resultService->buildFromPayload($kihuYear, $companyId, $payload);

            $this->storeFurusatoResults($data, $results);

            session()->flash('furusato_results', $results);
            session()->flash('show_furusato_result', true);
        }

        $routeParams = ['data_id' => $data->id];

        $originQuery = $this->buildOriginQuery($request);

        switch ($goto) {
            case 'syori':
                return redirect()->route('furusato.syori', $routeParams)->with('success', '保存しました');
            case 'master':
                return redirect()->route('furusato.master', $routeParams)->with('success', '保存しました');
            case 'jigyo':
                return redirect()->route('furusato.details.jigyo', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'fudosan':
                return redirect()->route('furusato.details.fudosan', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'kihukin_details':
                return redirect()->route('furusato.details.kihukin', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'joto_ichiji':
                return redirect()->route('furusato.details.joto_ichiji', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'kojo_seimei_jishin':
                return redirect()->route('furusato.details.kojo_seimei_jishin', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'kojo_jinteki':
                return redirect()->route('furusato.details.kojo_jinteki', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'kojo_iryo':
                return redirect()->route('furusato.details.kojo_iryo', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            default:
                return redirect()->route('furusato.input', $routeParams)->with('success', '保存しました');
        }
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

    public function saveJigyoEigyoDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $labelUpdates = $this->validateAndNormalizeLabels($req, self::JIGYO_EIGYO_LABEL_FIELDS);

        $payload = $this->sanitizeDetailPayload($req->except(array_merge(
            ['_token', 'data_id', 'origin_tab', 'origin_anchor'],
            self::JIGYO_EIGYO_LABEL_FIELDS,
        )));

        $calculations = $this->calculateJigyoEigyo($payload);
        $payload = array_replace($payload, $calculations);

        $payload['syunyu_jigyo_eigyo_shotoku_prev'] = $this->valueOrZero($payload['jigyo_eigyo_uriage_prev'] ?? null);
        $payload['syunyu_jigyo_eigyo_shotoku_curr'] = $this->valueOrZero($payload['jigyo_eigyo_uriage_curr'] ?? null);
        $payload['shotoku_jigyo_eigyo_shotoku_prev'] = (int) ($payload['jigyo_eigyo_shotoku_prev'] ?? 0);
        $payload['shotoku_jigyo_eigyo_shotoku_curr'] = (int) ($payload['jigyo_eigyo_shotoku_curr'] ?? 0);

        $this->updateFurusatoInputPayload($data, $payload, $labelUpdates);

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

    public function saveFudosanDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $labelUpdates = $this->validateAndNormalizeLabels($req, self::FUDOSAN_LABEL_FIELDS);

        $payload = $this->sanitizeDetailPayload($req->except(array_merge(
            ['_token', 'data_id', 'origin_tab', 'origin_anchor'],
            self::FUDOSAN_LABEL_FIELDS,
        )));

        $calculations = $this->calculateFudosan($payload);
        $payload = array_replace($payload, $calculations);

        $payload['syunyu_fudosan_shotoku_prev'] = $this->valueOrZero($payload['fudosan_shunyu_prev'] ?? null);
        $payload['syunyu_fudosan_shotoku_curr'] = $this->valueOrZero($payload['fudosan_shunyu_curr'] ?? null);

        $adjPrev = (int) ($payload['fudosan_shotoku_prev'] ?? 0);
        if ($adjPrev < 0) {
            $adjPrev += $this->valueOrZero($payload['fudosan_fusairishi_prev'] ?? null);
        }

        $adjCurr = (int) ($payload['fudosan_shotoku_curr'] ?? 0);
        if ($adjCurr < 0) {
            $adjCurr += $this->valueOrZero($payload['fudosan_fusairishi_curr'] ?? null);
        }

        $payload['shotoku_fudosan_shotoku_prev'] = $adjPrev;
        $payload['shotoku_fudosan_shotoku_curr'] = $adjCurr;

        $this->updateFurusatoInputPayload($data, $payload, $labelUpdates);

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function kihukinDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = FurusatoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $out = ['inputs' => is_array($payload) ? $payload : []];

        return view('tax.furusato.details.kihukin_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
        ]);
    }

    public function saveKihukinDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'redirect_to', 'origin_tab', 'origin_anchor']));

        $this->updateFurusatoInputPayload($data, $payload);

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function jotoIchijiDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.joto_ichiji_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
        ]);
    }

    public function saveJotoIchijiDetails(Request $req): RedirectResponse
    {
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

        foreach (['prev', 'curr'] as $period) {
            $shunyu = $this->valueOrZero($payload[sprintf('joto_ichiji_shunyu_%s', $period)] ?? null);
            $keihi = $this->valueOrZero($payload[sprintf('joto_ichiji_keihi_%s', $period)] ?? null);
            $sashihiki = $shunyu - $keihi;
            $payload[sprintf('joto_ichiji_sashihiki_%s', $period)] = $sashihiki;
            $payload[sprintf('shotoku_joto_ichiji_shotoku_%s', $period)] = $sashihiki;
            $payload[sprintf('shotoku_joto_ichiji_jumin_%s', $period)] = $sashihiki;
        }

        $this->updateFurusatoInputPayload($data, $payload);

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

    public function saveKojoSeimeiJishinDetails(Request $req): RedirectResponse
    {
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

        $seimeiPrevKeys = [
            'kojo_seimei_shin_prev',
            'kojo_seimei_kyu_prev',
            'kojo_seimei_nenkin_shin_prev',
            'kojo_seimei_nenkin_kyu_prev',
            'kojo_seimei_kaigo_iryo_prev',
        ];
        $seimeiCurrKeys = [
            'kojo_seimei_shin_curr',
            'kojo_seimei_kyu_curr',
            'kojo_seimei_nenkin_shin_curr',
            'kojo_seimei_nenkin_kyu_curr',
            'kojo_seimei_kaigo_iryo_curr',
        ];

        $jishinPrevKeys = [
            'kojo_jishin_prev',
            'kojo_kyuchoki_songai_prev',
        ];
        $jishinCurrKeys = [
            'kojo_jishin_curr',
            'kojo_kyuchoki_songai_curr',
        ];

        $payload['kojo_seimei_gokei_prev'] = array_reduce($seimeiPrevKeys, function (int $carry, string $key) use ($payload): int {
            return $carry + $this->valueOrZero($payload[$key] ?? null);
        }, 0);

        $payload['kojo_seimei_gokei_curr'] = array_reduce($seimeiCurrKeys, function (int $carry, string $key) use ($payload): int {
            return $carry + $this->valueOrZero($payload[$key] ?? null);
        }, 0);

        $payload['kojo_jishin_gokei_prev'] = array_reduce($jishinPrevKeys, function (int $carry, string $key) use ($payload): int {
            return $carry + $this->valueOrZero($payload[$key] ?? null);
        }, 0);

        $payload['kojo_jishin_gokei_curr'] = array_reduce($jishinCurrKeys, function (int $carry, string $key) use ($payload): int {
            return $carry + $this->valueOrZero($payload[$key] ?? null);
        }, 0);

        $this->updateFurusatoInputPayload($data, $payload);

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

    public function saveKojoJintekiDetails(Request $req): RedirectResponse
    {
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

        $this->updateFurusatoInputPayload($data, $payload);

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

    public function saveKojoIryoDetails(Request $req): RedirectResponse
    {
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

        [$shotokuGokeiPrev, $shotokuGokeiCurr] = $this->resolveShotokuGokei($data->id);
        $shotokuGokeiMap = [
            'prev' => $shotokuGokeiPrev,
            'curr' => $shotokuGokeiCurr,
        ];

        foreach ($shotokuGokeiMap as $period => $shotokuGokei) {
            $shiharaiKey = sprintf('kojo_iryo_shiharai_%s', $period);
            $hotenKey = sprintf('kojo_iryo_hotengaku_%s', $period);
            $sashihikiKey = sprintf('kojo_iryo_sashihiki_%s', $period);
            $shotokuGokeiKey = sprintf('kojo_iryo_shotoku_gokei_%s', $period);
            $shotoku5pctKey = sprintf('kojo_iryo_shotoku_5pct_%s', $period);
            $minThresholdKey = sprintf('kojo_iryo_min_threshold_%s', $period);
            $kojogakuKey = sprintf('kojo_iryo_kojogaku_%s', $period);
            $shotokuKey = sprintf('kojo_iryo_shotoku_%s', $period);
            $juminKey = sprintf('kojo_iryo_jumin_%s', $period);

            $shiharai = $this->valueOrZero($payload[$shiharaiKey] ?? null);
            $hoten = $this->valueOrZero($payload[$hotenKey] ?? null);
            $sashihiki = $shiharai - $hoten;
            if ($shotokuGokei >= 0) {
                $shotoku5pct = intdiv($shotokuGokei, 20);
            } else {
                $shotoku5pct = -intdiv(abs($shotokuGokei) + 19, 20);
            }
            $minThreshold = min($shotoku5pct, 100000);
            $kojogaku = max($sashihiki - $minThreshold, 0);

            $payload[$sashihikiKey] = $sashihiki;
            $payload[$shotokuGokeiKey] = $shotokuGokei;
            $payload[$shotoku5pctKey] = $shotoku5pct;
            $payload[$minThresholdKey] = $minThreshold;
            $payload[$kojogakuKey] = $kojogaku;
            $payload[$shotokuKey] = $kojogaku;
            $payload[$juminKey] = $kojogaku;
        }

        foreach (['kojo_iryo_shishutsu_prev', 'kojo_iryo_shishutsu_curr', 'kojo_iryo_hojokin_prev', 'kojo_iryo_hojokin_curr'] as $legacyKey) {
            $payload[$legacyKey] = null;
        }

        $this->updateFurusatoInputPayload($data, $payload);

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

    public function syoriSave(FurusatoSyoriRequest $request): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $validated = $request->validated();

        $payload = array_intersect_key($validated, $this->syoriDefaultPayload());
        $payload = $this->applyStandardRates($payload);

        $userId = (int) auth()->id();

        FurusatoSyoriSetting::unguarded(function () use ($data, $payload, $userId): void {
            $record = FurusatoSyoriSetting::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->data_id = $data->id;
                $record->company_id = $data->company_id;
                $record->group_id = $data->group_id;
                $record->created_by = $userId ?: null;
            }

            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;
            $record->payload = $payload;
            $record->updated_by = $userId ?: null;

            $record->save();
        });

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

        $this->normalizeJotoIchijiKeys($payload);
        $this->normalizeBunriChokiShotokuKeys($payload);
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

        $rules = array_fill_keys(self::BUNRI_CHOKI_SHOTOKU_FIELDS, ['bail', 'nullable', 'integer', 'min:0']);

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
    
    private function normalizeBunriChokiShotokuKeys(array &$payload): void
    {
        $types = ['tokutei', 'keika'];
        $periods = ['prev', 'curr'];

        foreach ($types as $type) {
            foreach ($periods as $period) {
                $legacyKey = sprintf('bunri_choki_%s_shotoku_%s', $type, $period);
                $underKey = sprintf('bunri_choki_%s_under_shotoku_%s', $type, $period);
                $overKey = sprintf('bunri_choki_%s_over_shotoku_%s', $type, $period);

                $legacyExists = array_key_exists($legacyKey, $payload);
                $legacyValue = $legacyExists ? $this->toNullableInt($payload[$legacyKey]) : null;

                $underExists = array_key_exists($underKey, $payload);
                $underValue = $underExists ? $this->toNullableInt($payload[$underKey]) : null;

                if ($legacyExists && $underValue === null) {
                    $underValue = $legacyValue;
                }

                if ($underExists || $underValue !== null) {
                    $payload[$underKey] = $underValue;
                }

                if ($legacyExists) {
                    unset($payload[$legacyKey]);
                }

                if (array_key_exists($overKey, $payload)) {
                    $payload[$overKey] = $this->toNullableInt($payload[$overKey]);
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
        $params = ['data_id' => $data->id, 'tab' => 'input'];

        $redirect = redirect()
            ->route('furusato.input', $params)
            ->with('success', $message)
            ->with('return_tab', 'input');

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
            $shunyu = $this->valueOrZero($inputs[sprintf('fudosan_shunyu_%s', $period)] ?? null);

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

    private function updateFurusatoInputPayload(Data $data, array $updates, array $labelUpdates = []): void
    {
        $userId = (int) auth()->id();

        FurusatoInput::unguarded(function () use ($data, $updates, $labelUpdates, $userId): void {
            $this->normalizeJotoIchijiKeys($updates);
            $this->normalizeBunriChokiShotokuKeys($updates);
            $this->normalizeKojoRenamedKeys($updates, true);

            $record = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->fill([
                    'data_id'    => $data->id,
                    'company_id' => $data->company_id,
                    'group_id'   => $data->group_id,
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
            $this->normalizeJotoIchijiKeys($current);
            $this->normalizeBunriChokiShotokuKeys($current);
            $this->normalizeKojoRenamedKeys($current, true);
            $payload = array_replace($current, $updates);
            $this->normalizeBunriChokiShotokuKeys($payload);
            $this->normalizeKojoRenamedKeys($payload, true);
            $settings = $this->getSyoriSettings($data->id);
            $payload = $this->applyAutoCalculatedFields($data, $payload, $settings);
            $this->normalizeKojoRenamedKeys($payload, true);
            $record->payload = $payload;
            $record->updated_by = $userId ?: null;

            $record->save();
        });
    }

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
     * @param array<int, array{lower:int, upper:int|null, rate:float, deduction_amount:int}> $rates
     */
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

    private function resolveAuthorizedDataOrFail(Request $request, string $ability = 'view'): Data
    {
        $id = (int) ($request->input('data_id') ?? $request->query('data_id'));
        abort_unless($id > 0, 422, 'data_id が指定されていません。');

        $data = Data::with('guest')->findOrFail($id);
        $me = $request->user();

        abort_unless($me, 401);

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

        abort_unless($me, 401);

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