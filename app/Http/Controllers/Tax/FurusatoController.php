<?php

namespace App\Http\Controllers\Tax;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\DetailsSourceAliasCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\KojoSeimeiJishinCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\Support\FurusatoMasterSheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\FurusatoInputRequest;
use App\Http\Requests\Tax\FurusatoSyoriRequest;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Models\FurusatoSyoriSetting;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\FurusatoMasterService;
use App\Services\Tax\Kojo\HaigushaKojoService;
use App\Services\Tax\Kojo\JintekiKojoService;
use App\Services\Tax\Kojo\KifukinShotokuKojoService;
use App\Services\Tax\Kojo\KihonService;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
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

        $calculatorYear = (int) ($kihuYear ?? self::MASTER_KIHU_YEAR);
        $calculatorCtx = [
            'master_kihu_year' => self::MASTER_KIHU_YEAR,
            'kihu_year' => $calculatorYear,
            'company_id' => $companyId,
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

        $previewPayload = $savedInputs;

        /** @var TokureiRateCalculator $tokureiCalculator */
        $tokureiCalculator = app(TokureiRateCalculator::class);
        $previewPayload = $tokureiCalculator->compute($previewPayload, $calculatorCtx);

        /** @var BunriSeparatedMinRateCalculator $bunriMinCalculator */
        $bunriMinCalculator = app(BunriSeparatedMinRateCalculator::class);
        $previewPayload = $bunriMinCalculator->compute($previewPayload, $calculatorCtx);

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
        FurusatoResultCalculator $resultCalculator,
    ): RedirectResponse
    {
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

            $ctx = [];
            $user = $request->user();
            if ($user) {
                $ctx['user_id'] = (int) $user->id;
            }

            $ctx['guest_birth_date'] = $this->normalizeBirthDateForContext($data->guest?->birth_date ?? null);

            $recalculateUseCase->handle($data, $updates, $ctx);

            return redirect()->route('furusato.input', ['data_id' => $data->id])->with('success', '保存しました');
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

        $this->updateFurusatoInputPayload($data, $updates);

        $goto = (string) $request->input('redirect_to', '');
        $shouldShowResult = $request->boolean('show_result') || $goto === '' || $goto === 'input';

        if ($shouldShowResult) {
            $payload = $this->getFurusatoInputPayload($data);
            $kihuYear = self::MASTER_KIHU_YEAR;
            $companyId = $request->user()?->company_id;
            $companyId = $companyId !== null ? (int) $companyId : null;
            $details = $resultCalculator->buildDetails($payload, [
                'master_kihu_year' => self::MASTER_KIHU_YEAR,
                'kihu_year' => $kihuYear,
                'company_id' => $companyId,
                'guest_birth_date' => $this->normalizeBirthDateForContext($data->guest?->birth_date ?? null),
            ]);
            $results = [
                'details' => $details,
                'upper' => $payload,
            ];

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
            case 'bunri_joto':
                return redirect()->route('furusato.details.bunri_joto', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'bunri_kabuteki':
                return redirect()->route('furusato.details.bunri_kabuteki', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'bunri_sakimono':
                return redirect()->route('furusato.details.bunri_sakimono', array_merge($routeParams, $originQuery))->with('success', '保存しました');
            case 'bunri_sanrin':
                return redirect()->route('furusato.details.bunri_sanrin', array_merge($routeParams, $originQuery))->with('success', '保存しました');
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

        foreach (['prev', 'curr'] as $period) {
            $uriage = $this->valueOrZero($this->toNullableInt($payload[sprintf('jigyo_eigyo_uriage_%s', $period)] ?? null));
            foreach (['shotoku', 'jumin'] as $tax) {
                $payload[sprintf('syunyu_jigyo_eigyo_%s_%s', $tax, $period)] = $uriage;
            }

            $shotoku = (int) ($this->toNullableInt($payload[sprintf('jigyo_eigyo_shotoku_%s', $period)] ?? null) ?? 0);
            foreach (['shotoku', 'jumin'] as $tax) {
                $payload[sprintf('shotoku_jigyo_eigyo_%s_%s', $tax, $period)] = $shotoku;
            }
        }

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

        $this->normalizeFudosanSyunyuKeys($payload);

        $calculations = $this->calculateFudosan($payload);
        $payload = array_replace($payload, $calculations);

        foreach (['prev', 'curr'] as $period) {
            $shunyuValue = $this->toNullableInt($payload[sprintf('fudosan_syunyu_%s', $period)] ?? null);
            $shunyu = $this->valueOrZero($shunyuValue);
            foreach (['shotoku', 'jumin'] as $tax) {
                $payload[sprintf('syunyu_fudosan_%s_%s', $tax, $period)] = $shunyu;
            }

            $shotokuValue = $this->toNullableInt($payload[sprintf('fudosan_shotoku_%s', $period)] ?? null);
            $shotoku = (int) ($shotokuValue ?? 0);
            if ($shotoku < 0) {
                $shotoku += $this->valueOrZero($this->toNullableInt($payload[sprintf('fudosan_fusairishi_%s', $period)] ?? null));
            }

            foreach (['shotoku', 'jumin'] as $tax) {
                $payload[sprintf('shotoku_fudosan_%s_%s', $tax, $period)] = $shotoku;
            }
        }
        
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

        $incomeBases = ['syunyu_joto_tanki', 'syunyu_joto_choki', 'syunyu_ichiji'];
        $shotokuBases = ['shotoku_joto_tanki', 'shotoku_joto_choki', 'shotoku_ichiji'];

        foreach (['prev', 'curr'] as $period) {
            foreach ($incomeBases as $base) {
                $value = $this->valueOrZero($this->toNullableInt($payload[sprintf('%s_%s', $base, $period)] ?? null));
                foreach (['shotoku', 'jumin'] as $tax) {
                    $payload[sprintf('%s_%s_%s', $base, $tax, $period)] = $value;
                }
            }

            foreach ($shotokuBases as $base) {
                $value = (int) ($this->toNullableInt($payload[sprintf('%s_%s', $base, $period)] ?? null) ?? 0);
                foreach (['shotoku', 'jumin'] as $tax) {
                    $payload[sprintf('%s_%s_%s', $base, $tax, $period)] = $value;
                }
            }
        }

        $this->updateFurusatoInputPayload($data, $payload);

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

    public function saveBunriJotoDetails(Request $req): RedirectResponse
    {
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

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));
        $this->recalculateBunriJoto($payload);
        $this->mirrorBunriJotoDetailsToInput($payload);
        $this->updateFurusatoInputPayload($data, $payload);

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

    public function saveBunriKabutekiDetails(Request $req): RedirectResponse
    {
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
                    $rules[sprintf('%s_%s_%s', $prefix, $row['key'], $period)] = ['bail', 'nullable', 'integer', 'min:0'];
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
        $this->recalculateBunriKabuteki($payload);
        $this->mirrorBunriKabutekiDetailsToInput($payload);
        $this->updateFurusatoInputPayload($data, $payload);

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

    public function saveBunriSakimonoDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $rules = [];
        foreach (['syunyu_sakimono', 'keihi_sakimono', 'kurikoshi_sakimono'] as $base) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));
        $this->recalculateBunriSakimono($payload);
        $this->mirrorBunriSakimonoDetailsToInput($payload);
        $this->updateFurusatoInputPayload($data, $payload);

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function bunriSanrinDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.bunri_sanrin_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriSanrinDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $rules = [];
        foreach (['syunyu_sanrin', 'keihi_sanrin', 'tokubetsukojo_sanrin'] as $base) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));
        $this->recalculateBunriSanrin($payload);
        $this->mirrorBunriSanrinDetailsToInput($payload);
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

        $calculator = app(KojoSeimeiJishinCalculator::class);
        $computedPrev = $calculator->compute($payload, 'prev');
        $computedCurr = $calculator->compute($payload, 'curr');

        $payload = array_replace($payload, $computedPrev, $computedCurr);

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

    private function mirrorBunriJotoDetailsToInput(array &$payload): void
    {
        $mirrorSources = [
            'syunyu_joto_ippan' => 'bunri_syunyu_tanki_ippan',
            'syunyu_joto_keigen' => 'bunri_syunyu_tanki_keigen',
            'syunyu_choki_ippan' => 'bunri_syunyu_choki_ippan',
            'syunyu_choki_tokutei' => 'bunri_syunyu_choki_tokutei',
            'syunyu_choki_keika' => 'bunri_syunyu_choki_keika',
            'joto_shotoku_tanki_ippan' => 'bunri_shotoku_tanki_ippan',
            'joto_shotoku_tanki_keigen' => 'bunri_shotoku_tanki_keigen',
            'joto_shotoku_choki_ippan' => 'bunri_shotoku_choki_ippan',
            'joto_shotoku_choki_tokutei' => 'bunri_shotoku_choki_tokutei',
            'joto_shotoku_choki_keika' => 'bunri_shotoku_choki_keika',
            'joto_shotoku_tanki_gokei' => 'bunri_kazeishotoku_tanki',
            'joto_shotoku_choki_gokei' => 'bunri_kazeishotoku_choki',
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach ($mirrorSources as $sourceBase => $mirrorBase) {
                $value = $this->toNullableInt($payload[sprintf('%s_%s', $sourceBase, $period)] ?? null);

                $payload[sprintf('%s_shotoku_%s', $mirrorBase, $period)] = $value;
                $payload[sprintf('%s_jumin_%s', $mirrorBase, $period)] = $value;
            }
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

    private function mirrorBunriKabutekiDetailsToInput(array &$payload): void
    {
        $mirrorSources = [
            'syunyu_ippan_joto' => 'bunri_syunyu_ippan_kabuteki_joto',
            'syunyu_jojo_joto' => 'bunri_syunyu_jojo_kabuteki_joto',
            'syunyu_jojo_haito' => 'bunri_syunyu_jojo_kabuteki_haito',
            'shotoku_after_kurikoshi_ippan_joto' => 'bunri_shotoku_ippan_kabuteki_joto',
            'shotoku_after_kurikoshi_jojo_joto' => 'bunri_shotoku_jojo_kabuteki_joto',
            'shotoku_after_kurikoshi_jojo_haito' => 'bunri_shotoku_jojo_kabuteki_haito',
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach ($mirrorSources as $sourceBase => $mirrorBase) {
                $value = $this->toNullableInt($payload[sprintf('%s_%s', $sourceBase, $period)] ?? null);

                foreach (['shotoku', 'jumin'] as $tax) {
                    $payload[sprintf('%s_%s_%s', $mirrorBase, $tax, $period)] = $value;
                }
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

    private function mirrorBunriSakimonoDetailsToInput(array &$payload): void
    {
        $mirrorSources = [
            'syunyu_sakimono' => 'bunri_syunyu_sakimono',
            'shotoku_sakimono_after_kurikoshi' => 'bunri_shotoku_sakimono',
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach ($mirrorSources as $sourceBase => $mirrorBase) {
                $value = $this->toNullableInt($payload[sprintf('%s_%s', $sourceBase, $period)] ?? null);

                foreach (['shotoku', 'jumin'] as $tax) {
                    $payload[sprintf('%s_%s_%s', $mirrorBase, $tax, $period)] = $value;
                }
            }
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

    private function mirrorBunriSanrinDetailsToInput(array &$payload): void
    {
        $mirrorSources = [
            'syunyu_sanrin' => 'bunri_syunyu_sanrin',
            'shotoku_sanrin' => 'bunri_shotoku_sanrin',
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach ($mirrorSources as $sourceBase => $mirrorBase) {
                $value = $this->toNullableInt($payload[sprintf('%s_%s', $sourceBase, $period)] ?? null);

                foreach (['shotoku', 'jumin'] as $tax) {
                    $payload[sprintf('%s_%s_%s', $mirrorBase, $tax, $period)] = $value;
                }
            }
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

    private function updateFurusatoInputPayload(Data $data, array $updates, array $labelUpdates = []): void
    {
        $userId = (int) auth()->id();

        FurusatoInput::unguarded(function () use ($data, $updates, $labelUpdates, $userId): void {
            $this->normalizeJotoIchijiKeys($updates);
            $this->normalizeFudosanSyunyuKeys($updates);
            $this->normalizeBunriChokiSyunyuKeys($updates);
            $this->normalizeBunriChokiShotokuKeys($updates);
            $this->normalizeBunriIncomeShotokuKeys($updates);
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
            $this->normalizeFudosanSyunyuKeys($current);
            $this->normalizeBunriChokiSyunyuKeys($current);
            $this->normalizeBunriChokiShotokuKeys($current);
            $this->normalizeBunriIncomeShotokuKeys($current);
            $this->normalizeKojoRenamedKeys($current, true);
            $payload = array_replace($current, $updates);
            $this->normalizeFudosanSyunyuKeys($payload);
            $this->normalizeBunriChokiSyunyuKeys($payload);
            $this->normalizeBunriChokiShotokuKeys($payload);
            $this->normalizeBunriIncomeShotokuKeys($payload);
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

        /** @var KojoSeimeiJishinCalculator $seimeiJishinCalculator */
        $seimeiJishinCalculator = app(KojoSeimeiJishinCalculator::class);
        $payload = array_replace(
            $payload,
            $seimeiJishinCalculator->compute($payload, 'prev'),
            $seimeiJishinCalculator->compute($payload, 'curr'),
        );
        $this->assertProvidedKeys($payload, $seimeiJishinCalculator);

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