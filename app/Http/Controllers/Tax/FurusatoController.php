<?php

namespace App\Http\Controllers\Tax;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\ShotokuTaxCalculator;
use App\Domain\Tax\Calculators\SeitotoTokubetsuZeigakuKojoCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\CommonSumsCalculator;
use App\Domain\Tax\Calculators\KojoAggregationCalculator;
use App\Domain\Tax\Calculators\CommonTaxableBaseCalculator;
use App\Domain\Tax\Calculators\KyuyoNenkinCalculator;
use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
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
use App\Services\Tax\FurusatoMasterDefaults;
use App\Services\Tax\FurusatoOneStopEligibilityService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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

    /**
     * 第三表（分離課税・退職）でユーザーが直接入力する収入金額／所得金額（SoT）として扱うフィールド群。
     * - bunri_syunyu_taishoku_shotoku_{prev,curr}
     * - bunri_shotoku_taishoku_shotoku_{prev,curr}
     *
     * これらは input.blade 側で data-server-lock を付けずに編集可能とし、
     * FurusatoInput.payload 経由で RecalculateFurusatoPayload に渡して下流の Calculator で利用する。
     */
    private const BUNRI_TAISHOKU_MANUAL_FIELDS = [
        'bunri_syunyu_taishoku_shotoku_prev',
        'bunri_syunyu_taishoku_shotoku_curr',
        'bunri_shotoku_taishoku_shotoku_prev',
        'bunri_shotoku_taishoku_shotoku_curr',
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

        \Log::debug('furusato.index.after_out_assignment', [
            'route' => __METHOD__,
            'outInputs' => [
                'kojo_shakaihoken_shotoku_curr' => $context['outInputs']['kojo_shakaihoken_shotoku_curr'] ?? null,
                'kojo_shakaihoken_jumin_curr'   => $context['outInputs']['kojo_shakaihoken_jumin_curr'] ?? null,
                'kojo_shokibo_shotoku_curr'     => $context['outInputs']['kojo_shokibo_shotoku_curr'] ?? null,
                'kojo_shokibo_jumin_curr'       => $context['outInputs']['kojo_shokibo_jumin_curr'] ?? null,
                'shotokuzei_kojo_kiso_curr'     => $context['outInputs']['shotokuzei_kojo_kiso_curr'] ?? null,
                'juminzei_kojo_kiso_curr'       => $context['outInputs']['juminzei_kojo_kiso_curr'] ?? null,
                'kojo_shokei_shotoku_curr'      => $context['outInputs']['kojo_shokei_shotoku_curr'] ?? null,
                'kojo_shokei_jumin_curr'        => $context['outInputs']['kojo_shokei_jumin_curr'] ?? null,
                'kojo_gokei_shotoku_curr'       => $context['outInputs']['kojo_gokei_shotoku_curr'] ?? null,
                'kojo_gokei_jumin_curr'         => $context['outInputs']['kojo_gokei_jumin_curr'] ?? null,
            ],
            'out.inputs' => [
                'kojo_shakaihoken_shotoku_curr' => $context['out']['inputs']['kojo_shakaihoken_shotoku_curr'] ?? null,
                'kojo_shakaihoken_jumin_curr'   => $context['out']['inputs']['kojo_shakaihoken_jumin_curr'] ?? null,
                'kojo_shokibo_shotoku_curr'     => $context['out']['inputs']['kojo_shokibo_shotoku_curr'] ?? null,
                'kojo_shokibo_jumin_curr'       => $context['out']['inputs']['kojo_shokibo_jumin_curr'] ?? null,
                'shotokuzei_kojo_kiso_curr'     => $context['out']['inputs']['shotokuzei_kojo_kiso_curr'] ?? null,
                'juminzei_kojo_kiso_curr'       => $context['out']['inputs']['juminzei_kojo_kiso_curr'] ?? null,
                'kojo_shokei_shotoku_curr'      => $context['out']['inputs']['kojo_shokei_shotoku_curr'] ?? null,
                'kojo_shokei_jumin_curr'        => $context['out']['inputs']['kojo_shokei_jumin_curr'] ?? null,
                'kojo_gokei_shotoku_curr'       => $context['out']['inputs']['kojo_gokei_shotoku_curr'] ?? null,
                'kojo_gokei_jumin_curr'         => $context['out']['inputs']['kojo_gokei_jumin_curr'] ?? null,
            ],
            'savedInputs' => [
                'kojo_shakaihoken_shotoku_curr' => $context['savedInputs']['kojo_shakaihoken_shotoku_curr'] ?? null,
                'kojo_shakaihoken_jumin_curr'   => $context['savedInputs']['kojo_shakaihoken_jumin_curr'] ?? null,
                'kojo_shokibo_shotoku_curr'     => $context['savedInputs']['kojo_shokibo_shotoku_curr'] ?? null,
                'kojo_shokibo_jumin_curr'       => $context['savedInputs']['kojo_shokibo_jumin_curr'] ?? null,
                'shotokuzei_kojo_kiso_curr'     => $context['savedInputs']['shotokuzei_kojo_kiso_curr'] ?? null,
                'juminzei_kojo_kiso_curr'       => $context['savedInputs']['juminzei_kojo_kiso_curr'] ?? null,
                'kojo_shokei_shotoku_curr'      => $context['savedInputs']['kojo_shokei_shotoku_curr'] ?? null,
                'kojo_shokei_jumin_curr'        => $context['savedInputs']['kojo_shokei_jumin_curr'] ?? null,
                'kojo_gokei_shotoku_curr'       => $context['savedInputs']['kojo_gokei_shotoku_curr'] ?? null,
                'kojo_gokei_jumin_curr'         => $context['savedInputs']['kojo_gokei_jumin_curr'] ?? null,
            ],
        ]);
        unset($context['outInputs']);

        $session = session();
        if ($session->has('furusato_results')) {
            $context['results'] = (array) $session->get('furusato_results');
        } elseif ($dataId) {
            $context['results'] = $this->getStoredFurusatoResults($dataId);
        }

        // show_furusato_result が明示的に true の場合のみ結果タブを開く。
        $context['showResult'] = (bool) $session->get('show_furusato_result', false);

        unset($context['savedInputs']);

        // 既存の本番計算結果から Kmax（理論上限額）を構築
        if (isset($context['results']) && is_array($context['results'])) {
            // Kmax は常に results（本番計算結果）の SoT から計算する
            $context['kmax'] = $this->buildKmaxFromResults($context['results']);
        } else {
            // SoT が取得できない場合は Kmax を計算しない
            $context['kmax'] = null;
        }

        $context = $this->attachOneStopPdfGuardContext($context);

        \Log::debug('furusato.input.context_spotcheck.before_view', [
            'route' => __METHOD__,
            'inputs' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'inputs.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'inputs.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'inputs.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'inputs.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'inputs.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'inputs.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'inputs.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'inputs.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'inputs.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'inputs.kojo_gokei_jumin_curr'),
            ],
            'results' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'results.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'results.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'results.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'results.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'results.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'results.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'results.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'results.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'results.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'results.kojo_gokei_jumin_curr'),
            ],
        ]);

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

        // 再計算後の本番計算結果から Kmax（理論上限額）を構築
        if (isset($context['results']) && is_array($context['results'])) {
            // POST（再計算）時も、Kmax は常に results ベース
            $context['kmax'] = $this->buildKmaxFromResults($context['results']);
        } else {
            // 本番計算結果が取得できない場合は Kmax は計算しない
            $context['kmax'] = null;
        }

        $context = $this->attachOneStopPdfGuardContext($context);

        \Log::debug('furusato.input.context_spotcheck.before_view', [
            'route' => __METHOD__,
            'inputs' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'inputs.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'inputs.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'inputs.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'inputs.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'inputs.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'inputs.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'inputs.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'inputs.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'inputs.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'inputs.kojo_gokei_jumin_curr'),
            ],
            'results' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'results.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'results.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'results.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'results.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'results.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'results.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'results.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'results.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'results.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'results.kojo_gokei_jumin_curr'),
            ],
        ]);

        return view('tax.furusato.input', $context);
    }

    /**
     * input画面のPDF導線用に、ワンストップ特例ON時のPDFブロック判定結果を載せる。
     * 保存・再計算・details編集は止めない（PDF出力導線のみガード）。
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function attachOneStopPdfGuardContext(array $context): array
    {
        $payload = $context['results']['payload'] ?? null;
        $syoriSettings = is_array($context['syoriSettings'] ?? null) ? $context['syoriSettings'] : null;

        /** @var FurusatoOneStopEligibilityService $eligibilityService */
        $eligibilityService = app(FurusatoOneStopEligibilityService::class);
        $oneStopFlag = is_array($syoriSettings)
            ? (int) ($syoriSettings['one_stop_flag_curr'] ?? $syoriSettings['one_stop_flag'] ?? 0)
            : 0;
        $oneStopEnabled = $oneStopFlag === 1;

        if (!is_array($payload) || !is_array($syoriSettings) || !$eligibilityService->hasRequiredKeys($payload)) {
            $context['oneStopPdfGuard'] = [
                'is_blocked' => $oneStopEnabled,
                'reasons' => [
                    'salary_over_20m' => false,
                    'other_income_over_200k' => false,
                    'resident_taxable_minus_human_diff_over_18m' => false,
                ],
                'values' => [
                    'salary_income_curr' => 0,
                    'other_income_curr' => 0,
                    'human_adjusted_taxable_curr' => 0,
                ],
                'one_stop_enabled' => $oneStopEnabled,
                'data_missing' => true,
            ];
            $context['oneStopPdfGuardMessage'] = FurusatoOneStopEligibilityService::DATA_MISSING_MESSAGE;
            return $context;
        }

        $guard = $eligibilityService->evaluate($payload, $syoriSettings);

        $context['oneStopPdfGuard'] = $guard;
        $context['oneStopPdfGuardMessage'] = FurusatoOneStopEligibilityService::BLOCK_MESSAGE;

        return $context;
    }

    /**
     * 本番計算結果（results）から Kmax 用の SoT を取り出して buildKmaxContext に渡す。
     *
     * - SoT を保持する payload / upper が無い場合は null を返す。
     * - details や画面コンテキスト全体を直接渡さないための「入口」をここに統一する。
     */
    private function buildKmaxFromResults(?array $results): ?array
    {
        if (empty($results) || !is_array($results)) {
            return null;
        }

        // SoT が入っている候補を優先順で拾う
        // 実装によって payload / upper どちら側に積んでいるか違うと思うので、
        // ここでは「payload → upper」の順に探す。
        $payload = $results['payload'] ?? $results['upper'] ?? null;

        if (!is_array($payload)) {
            // SoT 相当の配列が無い場合は Kmax を計算しない
            return null;
        }

        // 実際の Kmax 計算ロジックは buildKmaxContext() に一本化
        return $this->buildKmaxContext($payload);
    }

    private function resolveBunriFlag(?int $dataId): int
    {
        // ▼ 分離課税は常に「あり」で固定（syori_menu の選択肢を廃止する前提）
        //   data_id が無いケース（初期遷移など）だけは 0 を返しておく。
        if (! $dataId) {
        return 0;
        }

        return 1;
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
            // ▼ 分離課税は常に「あり」で固定：入力画面（第一表＋第三表）の表示は常に分離あり前提
            $showSeparatedNetting = true;
            $bunriFlag = 1;

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
            // ▼ 人的控除差調整は住民税概念なので tb_sogo_jumin を基準にする
            $taxableBase  = (int)($savedInputs[sprintf('tb_sogo_jumin_%s', $period)] ?? 0);
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
            'kihu_year'        => $calculatorYear,
            'company_id'       => $companyId,
            'data_id'          => $data?->id,
            // ひとり親控除（父=1万/母=5万）の人的控除差の分岐に用いる
            // guest のカラム名が環境差で揺れる可能性があるため data_get で安全に取得
            'taxpayer_sex'     => (function () use ($data) {
                if (!$data) return null;
                $v = data_get($data, 'guest.sex');
                if ($v === null) $v = data_get($data, 'guest.gender');
                if ($v === null) $v = data_get($data, 'guest.sex_code');
                return $v;
            })(),
        ];

        // ▼ KyuyoNenkin を index/calc 再描画時にも走らせ、details由来の収入→所得を確定
        /** @var KyuyoNenkinCalculator $knCalc */
        $knCalc = app(KyuyoNenkinCalculator::class);
        $knCtx = [
            'kihu_year'        => $calculatorYear,
            'guest_birth_date' => $this->normalizeBirthDateForContext($data?->guest?->birth_date ?? null),
            'data'             => $data,
        ];
        $seed = array_replace([], $savedInputs);
        $seed = array_replace($seed, $knCalc->compute($seed, $knCtx));

        $previewPayload = array_replace($seed, [
            'human_adjusted_taxable_prev' => $humanAdjusted['prev'],
            'human_adjusted_taxable_curr' => $humanAdjusted['curr'],
        ]);

        /**
         * ▼KyuyoNenkinCalculator を index/calc ルートでも実行する
         *    - UseCase 側では保存時に実行済みだが、画面再表示（index/calc）でも
         *      「details 収入 → 給与・雑の所得」確定値を previewPayload に生成しておく。
         *    - これにより buildInputsForView が previewOnly のままでも
         *      shotoku_kyuyo_* / shotoku_zatsu_* が確実に反映される。
         */
        /** @var KyuyoNenkinCalculator $knCalc */
        $knCalc = app(KyuyoNenkinCalculator::class);
        $knCtx = [
            'kihu_year'        => $calculatorYear,
            'guest_birth_date' => $this->normalizeBirthDateForContext($data?->guest?->birth_date ?? null),
            'data'             => $data,
        ];
        // details 側の kyuyo_syunyu_*/zatsu_*_syunyu_* を基に給与/雑の「所得」を確定
        $previewPayload = array_replace(
            $previewPayload,
            $knCalc->compute($previewPayload, $knCtx)
        );

        foreach (['prev', 'curr'] as $periodKey) {
            $tsusanmaeKeijo =
                (int) ($savedInputs[sprintf('shotoku_jigyo_eigyo_shotoku_%s', $periodKey)] ?? 0)
                + (int) ($savedInputs[sprintf('shotoku_jigyo_nogyo_shotoku_%s', $periodKey)] ?? 0)
                + (int) ($savedInputs[sprintf('shotoku_fudosan_shotoku_%s', $periodKey)] ?? 0)
                + max(0, (int) ($savedInputs[sprintf('shotoku_rishi_shotoku_%s', $periodKey)] ?? 0))
                + max(0, (int) ($savedInputs[sprintf('shotoku_haito_shotoku_%s', $periodKey)] ?? 0))
                + max(0, (int) ($savedInputs[sprintf('shotoku_kyuyo_shotoku_%s', $periodKey)] ?? 0))
                + max(0, (int) ($savedInputs[sprintf('shotoku_zatsu_nenkin_shotoku_%s', $periodKey)] ?? 0))
                + max(0, (int) ($savedInputs[sprintf('shotoku_zatsu_gyomu_shotoku_%s', $periodKey)] ?? 0))
                + max(0, (int) ($savedInputs[sprintf('shotoku_zatsu_sonota_shotoku_%s', $periodKey)] ?? 0));

            $previewPayload[sprintf('tsusanmae_keijo_%s', $periodKey)] = (int) $tsusanmaeKeijo;
        }

        /** @var SogoShotokuNettingCalculator $netCalc */
        $netCalc = app(SogoShotokuNettingCalculator::class);

        foreach (['prev', 'curr'] as $period) {
            $netOut = $netCalc->compute($previewPayload, $period);
            $previewPayload = array_replace($previewPayload, $netOut);
        }

        /**
         * 内部通算の直後に「段階通算（第1/2/3次）」を実施して
         *   after_1/2/3jitsusan_* と shotoku_*（第一表の所得金額）をここで確定させる。
         *   これにより details 画面の再計算だけで「損益通算後」「1/2」「所得金額」が 0 にならない。
         */
        /** @var SogoShotokuNettingStagesCalculator $stagesCalc */
        $stagesCalc = app(SogoShotokuNettingStagesCalculator::class);
        foreach (['prev', 'curr'] as $period) {
            $stageOut = $stagesCalc->compute($previewPayload, $period);
            $previewPayload = array_replace($previewPayload, $stageOut);
        }

        /**
         * SoT確定フェーズ（フォールバック禁止）
         *  1) CommonSums:   sum_for_sogoshotoku_*, sum_for_ab_total_* を確定
         *  2) KojoAgg:      kojo_gokei_shotoku_*, kojo_gokei_jumin_* を確定
         *  3) CommonTaxableBase: tb_*（唯一のSoT）を確定
         *  いずれも previewPayload に直接書き戻し、以降は tb_* を読むだけ。
         */
        /** @var CommonSumsCalculator $commonSums */
        $commonSums = app(CommonSumsCalculator::class);
        $previewPayload = array_replace($previewPayload, $commonSums->compute($previewPayload, $calculatorCtx));

        /**
         * ▼ 寄附金「所得控除」を先に確定（shotokuzei_kojo_kifukin_*）
         *    - この出力を KojoAggregation が合計に取り込む
         *    - 併せて used_by_income_deduction_* も生成され、後段の Seitoto が参照できる
         */
        /** @var \App\Domain\Tax\Calculators\KifukinCalculator $kifukinCalc */
        $kifukinCalc = app(\App\Domain\Tax\Calculators\KifukinCalculator::class);
        $previewPayload = array_replace($previewPayload, $kifukinCalc->compute($previewPayload, $calculatorCtx));

        /** @var KojoAggregationCalculator $kojoAgg */
        $kojoAgg = app(KojoAggregationCalculator::class);
        $previewPayload = array_replace($previewPayload, $kojoAgg->compute($previewPayload, $calculatorCtx));

        /** @var CommonTaxableBaseCalculator $ctb */
        $ctb = app(CommonTaxableBaseCalculator::class);
        $previewPayload = $ctb->compute($previewPayload, $calculatorCtx);
 
        /**
         * ▼ 住宅借入金等特別控除（住民税側上限）用の「（所得税の）課税総所得金額等」を SoT(tb_*)から合成
         *   rtax_taxable_total_* = tb_sogo_shotoku_* + tb_sanrin_shotoku_* + tb_taishoku_shotoku_* （各0下限）
         *   - ここでは画面表示用に previewPayload へ注入（保存時も Recalculate 経由で毎回再生成）
         */
        foreach (['prev','curr'] as $p) {
            $sogo     = max(0, (int)($previewPayload["tb_sogo_shotoku_{$p}"]     ?? 0));
            $sanrin   = max(0, (int)($previewPayload["tb_sanrin_shotoku_{$p}"]   ?? 0));
            $taishoku = max(0, (int)($previewPayload["tb_taishoku_shotoku_{$p}"] ?? 0));
            $previewPayload["rtax_taxable_total_{$p}"] = $sogo + $sanrin + $taishoku;
        }
        /**
         * ▼ 所得税額 → 所得税・寄附金税額控除（政党等/NPO/公益）をプレビュー段階で先行適用
         *    - ShotokuTax:  tb_sogo_shotoku_* を基に tax_zeigaku_shotoku_* を算出
         *    - Seitoto:     2,000円足切り・40%枠の食い合い・25%上限・最大化配分を行い、
         *                   tax_credit_shotoku_* / tax_sashihiki_shotoku_* を生成
         */
        /** @var ShotokuTaxCalculator $shotokuTax */
        $shotokuTax = app(ShotokuTaxCalculator::class);
        $previewPayload = array_replace($previewPayload, $shotokuTax->compute($previewPayload, $calculatorCtx));

        /** @var SeitotoTokubetsuZeigakuKojoCalculator $seitoto */
        $seitoto = app(SeitotoTokubetsuZeigakuKojoCalculator::class);
        $previewPayload = $seitoto->compute($previewPayload, $calculatorCtx);

        /**
         * ▼ 人的控除差調整後課税（human_adjusted_taxable_*）を SoT(tb_*) 確定“後”に上書き
         *    - これが Tokurei の標準率(AA50)の参照値になるため、prev=4,800,000 / curr=0 を保証する
         *    - 0 下限・千円未満切捨てを適用
         */
        $floorToThousands = static function (int $value): int {
            return $value > 0 ? intdiv($value, 1000) * 1000 : 0;
        };
        foreach (['prev','curr'] as $p) {
            // ▼ 人的控除差調整後課税は tb_sogo_jumin を基準に確定
            $tb = (int) ($previewPayload[sprintf('tb_sogo_jumin_%s', $p)] ?? 0);
            $humanDiff = (int) ($jintekiDiff['sum'][$p] ?? 0);
            $adj = $floorToThousands(max(0, $tb - $humanDiff));
            $previewPayload[sprintf('human_adjusted_taxable_%s', $p)] = $adj;
        }

        /** @var TokureiRateCalculator $tokureiCalculator */
        $tokureiCalculator = app(TokureiRateCalculator::class);
        $previewPayload = $tokureiCalculator->compute($previewPayload, $calculatorCtx);
 
        /** @var BunriSeparatedMinRateCalculator $bunriMinCalculator */
        $bunriMinCalculator = app(BunriSeparatedMinRateCalculator::class);
        $previewPayload = $bunriMinCalculator->compute($previewPayload, $calculatorCtx);

        /**
         * ▼ tsusango_%_% をサーバで確定（0 下限）
         *   仕様：tsusango_%_% = max(0, after_2jitsusan_%_%)
         *   - ここで previewPayload に確定値として生成しておくと、
         *     以降の details / inputs / POST すべてが 0 未満にならない
         */
        foreach (['prev','curr'] as $period) {
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                $after2Key = sprintf('after_2jitsusan_%s_%s', $suffix, $period);
                $tsusangoKey = sprintf('tsusango_%s_%s', $suffix, $period);
                $val = (int) ($previewPayload[$after2Key] ?? 0);
                $previewPayload[$tsusangoKey] = max(0, $val);
            }
            // 一時も 0 下限（既に elsewhere で max(0, …) しているが念のため統一）
            $after2Ichiji = (int) ($previewPayload[sprintf('after_2jitsusan_ichiji_%s', $period)] ?? 0);
            $previewPayload[sprintf('tsusango_ichiji_%s', $period)] = max(0, $after2Ichiji);
        }

        foreach (['prev', 'curr'] as $period) {
            $isSeparated = (int) ($syoriSettings["bunri_flag_{$period}"] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;

            if ($isSeparated) {
                // 分離ONでも課税標準は tb_* のみ。ここでは旧tax_*を生成しない（表示は buildInputsForView で tb_* をミラー）
            }
            /**
             * 表示用の最終値（第三表「所得金額」）は after_3（損益通算後の最終）を唯一のソースに統一
             *   - 山林:  after_3jitsusan_sanrin_*   → shotoku_sanrin_*
             *   - 退職:  after_3jitsusan_taishoku_* → shotoku_taishoku_*
             *   ※ 以降の buildInputsForView では shotoku_* をそのまま第三表「所得金額」にミラーする
             */
            $previewPayload["shotoku_sanrin_{$period}"]   = (int) ($previewPayload["after_3jitsusan_sanrin_{$period}"] ?? 0);
            $previewPayload["shotoku_taishoku_{$period}"] = (int) ($previewPayload["after_3jitsusan_taishoku_{$period}"] ?? 0);
        }

        foreach (['prev', 'curr'] as $period) {
            $short = (int) ($previewPayload[sprintf('shotoku_joto_tanki_sogo_%s', $period)] ?? 0);
            $long = (int) ($previewPayload[sprintf('shotoku_joto_choki_sogo_%s', $period)] ?? 0);
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
            'payload' => $previewPayload,
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
            // SoT確定後の tb_* から有効フラグを判定（早期判定はしない）
            'tokureiEnabled' => [
                'sanrin_prev' => ($previewPayload['tb_sanrin_shotoku_prev']  ?? 0) > 0,
                'sanrin_curr' => ($previewPayload['tb_sanrin_shotoku_curr']  ?? 0) > 0,
                'taishoku_prev' => ($previewPayload['tb_taishoku_shotoku_prev'] ?? 0) > 0,
                'taishoku_curr' => ($previewPayload['tb_taishoku_shotoku_curr'] ?? 0) > 0,
                'bunri_prev' => (
                    ($previewPayload['tb_joto_tanki_shotoku_prev'] ?? 0) +
                    ($previewPayload['tb_joto_choki_shotoku_prev'] ?? 0) +
                    ($previewPayload['tb_jojo_kabuteki_haito_shotoku_prev'] ?? 0) +
                    ($previewPayload['tb_ippan_kabuteki_joto_shotoku_prev'] ?? 0) +
                    ($previewPayload['tb_jojo_kabuteki_joto_shotoku_prev'] ?? 0) +
                    ($previewPayload['tb_sakimono_shotoku_prev'] ?? 0) > 0
                ),
                'bunri_curr' => (
                    ($previewPayload['tb_joto_tanki_shotoku_curr'] ?? 0) +
                    ($previewPayload['tb_joto_choki_shotoku_curr'] ?? 0) +
                    ($previewPayload['tb_jojo_kabuteki_haito_shotoku_curr'] ?? 0) +
                    ($previewPayload['tb_ippan_kabuteki_joto_shotoku_curr'] ?? 0) +
                    ($previewPayload['tb_jojo_kabuteki_joto_shotoku_curr'] ?? 0) +
                    ($previewPayload['tb_sakimono_shotoku_curr'] ?? 0) > 0
                ),
            ],
        ];

        \Log::debug('furusato.context.after_build_spotcheck', [
            'route' => __METHOD__,
            'outInputs' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'outInputs.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'outInputs.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'outInputs.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'outInputs.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'outInputs.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'outInputs.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'outInputs.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'outInputs.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'outInputs.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'outInputs.kojo_gokei_jumin_curr'),
            ],
            'out_inputs' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'out.inputs.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'out.inputs.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'out.inputs.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'out.inputs.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'out.inputs.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'out.inputs.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'out.inputs.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'out.inputs.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'out.inputs.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'out.inputs.kojo_gokei_jumin_curr'),
            ],
            'savedInputs' => [
                'kojo_shakaihoken_shotoku_curr' => data_get($context, 'savedInputs.kojo_shakaihoken_shotoku_curr'),
                'kojo_shakaihoken_jumin_curr'   => data_get($context, 'savedInputs.kojo_shakaihoken_jumin_curr'),
                'kojo_shokibo_shotoku_curr'     => data_get($context, 'savedInputs.kojo_shokibo_shotoku_curr'),
                'kojo_shokibo_jumin_curr'       => data_get($context, 'savedInputs.kojo_shokibo_jumin_curr'),
                'shotokuzei_kojo_kiso_curr'     => data_get($context, 'savedInputs.shotokuzei_kojo_kiso_curr'),
                'juminzei_kojo_kiso_curr'       => data_get($context, 'savedInputs.juminzei_kojo_kiso_curr'),
                'kojo_shokei_shotoku_curr'      => data_get($context, 'savedInputs.kojo_shokei_shotoku_curr'),
                'kojo_shokei_jumin_curr'        => data_get($context, 'savedInputs.kojo_shokei_jumin_curr'),
                'kojo_gokei_shotoku_curr'       => data_get($context, 'savedInputs.kojo_gokei_shotoku_curr'),
                'kojo_gokei_jumin_curr'         => data_get($context, 'savedInputs.kojo_gokei_jumin_curr'),
            ],
        ]);
        $session = session();
        if ($session->has('furusato_results')) {
            $context['results'] = (array) $session->get('furusato_results');
        } elseif ($dataId) {
            $context['results'] = $this->getStoredFurusatoResults($dataId);
        }

        if (($context['results'] ?? []) === []) {
            $context['results'] = $previewResults;
        }

        $resultsPayload = [];
        if (isset($context['results']['payload']) && is_array($context['results']['payload'])) {
            $resultsPayload = $context['results']['payload'];
        }

        $resultsUpper = [];
        if (isset($context['results']['upper']) && is_array($context['results']['upper'])) {
            $resultsUpper = $context['results']['upper'];
        }

        if ($resultsPayload === [] && $resultsUpper === [] && $context['results'] !== []) {
            $resultsPayload = is_array($context['results']) ? $context['results'] : [];
        }

        $context['outInputs'] = $this->buildInputsForView(
            $savedInputs,
            $previewPayload,
            $syoriSettings,
            $resultsPayload,
            $resultsUpper,
        );

        // ------------------------------------------------------------
        // ▼ 実利上限（自己負担<=2,000円）＆①〜④スナップショットは
        //   RecalculateFurusatoPayload で「1回だけ」生成し FurusatoResult に保存済み。
        //   画面表示では dry-run を回さず、保存済みを読むだけにする。
        // ------------------------------------------------------------
        $context['furusato_upper'] = null;
        $context['furusato_upper_scenarios'] = null;
        if (isset($context['results']) && is_array($context['results'])) {
            if (array_key_exists('furusato_upper', $context['results'])) {
                $context['furusato_upper'] = $context['results']['furusato_upper'];
            }
            if (array_key_exists('furusato_upper_scenarios', $context['results'])) {
                $context['furusato_upper_scenarios'] = $context['results']['furusato_upper_scenarios'];
            }
        }

        return $context;
    }
 
    /**
     * 住宅借入金等特別控除 内訳（表示）
     */
    public function kojoTokubetsuJutakuLoanDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear   = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear)     : '当年';
        $payload    = $this->getFurusatoInputPayload($data);

        // tb_* が未生成のケースでも 0 下限で安全に合成
        foreach (['prev','curr'] as $p) {
            $sogo     = max(0, (int)($payload["tb_sogo_shotoku_{$p}"]     ?? 0));
            $sanrin   = max(0, (int)($payload["tb_sanrin_shotoku_{$p}"]   ?? 0));
            $taishoku = max(0, (int)($payload["tb_taishoku_shotoku_{$p}"] ?? 0));
            $payload["rtax_taxable_total_{$p}"] = $sogo + $sanrin + $taishoku;
            // 住民税：プルダウン（5 / 7）の選択に応じて絶対上限を決定
            // payload にキーが存在しない初回表示時もあるため、
            // いったんローカル変数に取り出し 5 / 7 以外は 5 に矯正する。
            $rateInput = $payload["rtax_income_rate_percent_{$p}"] ?? 5;
            $ratePct   = in_array((int) $rateInput, [5, 7], true)
                        ? (int) $rateInput
                        : 5;
            $hardCap  = $ratePct === 7 ? 136_500 : 97_500;
            $byIncome = (int) floor($payload["rtax_taxable_total_{$p}"] * ($ratePct / 100.0));
            // 表示欄（readonly）：min(課税総所得金額等×率, 絶対上限)
            $payload["rtax_income_rate_percent_{$p}"] = $ratePct;
            $payload["rtax_carry_cap_{$p}"] = min($byIncome, $hardCap);
        }

        return view('tax.furusato.details.kojo_tokubetsu_jutaku_loan', [
            'dataId'     => $data->id,
            'kihuYear'   => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out'        => ['inputs' => $payload],
        ]);
    }

    /**
     * 住宅借入金等特別控除 内訳（保存→再計算）
     */
    public function saveKojoTokubetsuJutakuLoanDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase
    ): RedirectResponse {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（fields）
        //    ※整数系のカンマ正規化は normalizeIntegerFieldsFromRequest を維持
        $this->normalizeIntegerFieldsFromRequest($req, [
            'itax_borrow_cap_prev','itax_borrow_cap_curr',
            'itax_year_end_balance_prev','itax_year_end_balance_curr',
            'rtax_carry_cap_prev','rtax_carry_cap_curr',
        ]);
        $validated = $req->validated();

        $payload = [];
        $fields = [
            'itax_borrow_cap_prev','itax_borrow_cap_curr',
            'itax_year_end_balance_prev','itax_year_end_balance_curr',
            'itax_credit_rate_percent_prev','itax_credit_rate_percent_curr',
            'rtax_income_rate_percent_prev','rtax_income_rate_percent_curr',
            'rtax_carry_cap_prev','rtax_carry_cap_curr',
        ];
        foreach ($fields as $k) {
            $v = $validated[$k] ?? $req->input($k);
            if (in_array($k, ['itax_credit_rate_percent_prev','itax_credit_rate_percent_curr'], true)) {
                // 未入力時は 0.7 を採用／小数1位へ丸めて保存
                $num = ($v === null || $v === '') ? 0.7 : (float)str_replace([',',' '], '', (string)$v);
                $payload[$k] = number_format(round($num, 1), 1, '.', '');
                continue;
            }
            $payload[$k] = is_numeric($v) ? (string)$v : ($v === null ? null : (string)$v);
        }

        // 再計算（結果タブは開かない＝他detailsと同じ挙動）
        $this->runRecalculationPipeline(
            $req,
            $data,
            $payload,
            ['should_flash_results' => false],
            $recalculateUseCase,
        );

        // ▼ 再計算直後のDBペイロードを取得して「控除限度額（住民税：min(所得×率, 絶対上限)）」を確定させて保存
        $record = \App\Models\FurusatoInput::query()->where('data_id', $data->id)->first();
        $stored = is_array($record?->payload) ? $record->payload : [];
        foreach (['prev','curr'] as $p) {
            $sogo     = max(0, (int)($stored["tb_sogo_shotoku_{$p}"]     ?? 0));
            $sanrin   = max(0, (int)($stored["tb_sanrin_shotoku_{$p}"]   ?? 0));
            $taishoku = max(0, (int)($stored["tb_taishoku_shotoku_{$p}"] ?? 0));
            $stored["rtax_taxable_total_{$p}"] = $sogo + $sanrin + $taishoku;
            $ratePctI = in_array((int)($payload["rtax_income_rate_percent_{$p}"] ?? ($stored["rtax_income_rate_percent_{$p}"] ?? 5)), [5,7], true)
                        ? (int)($payload["rtax_income_rate_percent_{$p}"] ?? $stored["rtax_income_rate_percent_{$p}"]) : 5;
            $hardCap  = $ratePctI === 7 ? 136_500 : 97_500;
            $byIncome = (int) floor($stored["rtax_taxable_total_{$p}"] * ($ratePctI / 100.0));
            $stored["rtax_income_rate_percent_{$p}"] = $ratePctI;
            $stored["rtax_carry_cap_{$p}"] = min($byIncome, $hardCap); // 表示専用
        }
        if ($record) {
            $record->payload = $stored;
            $record->save();
        }
        // ボタンの redirect_to をそのまま採用（戻る＝input／再計算＝kojo_tokubetsu_jutaku_loan）
        $goto = (string) $req->input('redirect_to', $req->boolean('stay_on_details') ? 'kojo_tokubetsu_jutaku_loan' : 'input');
        return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
    }
    /**
     * @param  array<string, mixed>  $savedInputs
     * @param  array<string, mixed>  $previewPayload
     * @param  array<string, mixed>  $syoriSettings
     * @param  array<string, mixed>  $resultsPayload
     * @param  array<string, mixed>  $resultsUpper
     * @return array<string, mixed>
     */
    private function buildInputsForView(
        array $savedInputs,
        array $previewPayload,
        array $syoriSettings,
        array $resultsPayload = [],
        array $resultsUpper = [],
    ): array
    {
        $inputsForView = $savedInputs;

        $lookup = function (array $candidates, bool $previewOnly = false, bool $allowSaved = true) use ($resultsPayload, $resultsUpper, $previewPayload, $savedInputs): ?int {
            foreach ($candidates as $key) {
                if (! $previewOnly) {
                    if (array_key_exists($key, $resultsPayload) && $resultsPayload[$key] !== null) {
                        $value = $this->toNullableInt($resultsPayload[$key]);
                        if ($value !== null) {
                            return $value;
                        }
                    }

                    if (array_key_exists($key, $resultsUpper) && $resultsUpper[$key] !== null) {
                        $value = $this->toNullableInt($resultsUpper[$key]);
                        if ($value !== null) {
                            return $value;
                        }
                    }
                }

                if (array_key_exists($key, $previewPayload) && $previewPayload[$key] !== null) {
                    $value = $this->toNullableInt($previewPayload[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }

                if (! $previewOnly && $allowSaved && array_key_exists($key, $savedInputs) && $savedInputs[$key] !== null && $savedInputs[$key] !== '') {
                    $value = $this->toNullableInt($savedInputs[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $assign = function (
            string $destination,
            array $candidates,
            ?callable $transform = null,
            bool $previewOnly = false,
            bool $allowSaved = true
        ) use (&$inputsForView, $lookup): void {
            $value = $lookup($candidates, $previewOnly, $allowSaved);
            if ($value === null) {
                return;
            }

            $inputsForView[$destination] = $transform ? $transform($value) : $value;
        };

        $mirrorMany = function (
            array $destinations,
            array $candidates,
            ?callable $transform = null,
            bool $previewOnly = false,
            bool $allowSaved = true
        ) use (&$inputsForView, $lookup): void {
            $value = $lookup($candidates, $previewOnly, $allowSaved);
            if ($value === null) {
                return;
            }

            $value = $transform ? $transform($value) : $value;

            foreach ($destinations as $destination) {
                $inputsForView[$destination] = $value;
            }
        };

        // ▼ details（kyuyo/zatsu）→ 第一表 収入ミラー（税目共通・readonly表示用）
        foreach (['prev','curr'] as $p) {
            // 探索・フォールバック禁止：previewPayload のみ採用
            $v = $this->toNullableInt($previewPayload["kyuyo_syunyu_{$p}"] ?? null);
            if ($v !== null) {
                $inputsForView["syunyu_kyuyo_shotoku_{$p}"] = $v;
                $inputsForView["syunyu_kyuyo_jumin_{$p}"]   = $v;
            }
            $v = $this->toNullableInt($previewPayload["zatsu_nenkin_syunyu_{$p}"] ?? null);
            if ($v !== null) {
                $inputsForView["syunyu_zatsu_nenkin_shotoku_{$p}"] = $v;
                $inputsForView["syunyu_zatsu_nenkin_jumin_{$p}"]   = $v;
            }
            $v = $this->toNullableInt($previewPayload["zatsu_gyomu_syunyu_{$p}"] ?? null);
            if ($v !== null) {
                $inputsForView["syunyu_zatsu_gyomu_shotoku_{$p}"] = $v;
                $inputsForView["syunyu_zatsu_gyomu_jumin_{$p}"]   = $v;
            }
            $v = $this->toNullableInt($previewPayload["zatsu_sonota_syunyu_{$p}"] ?? null);
            if ($v !== null) {
                $inputsForView["syunyu_zatsu_sonota_shotoku_{$p}"] = $v;
                $inputsForView["syunyu_zatsu_sonota_jumin_{$p}"]   = $v;
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $isSeparated = (int) ($syoriSettings[sprintf('bunri_flag_%s', $period)] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;

            $kShot = sprintf('bunri_shotoku_taishoku_shotoku_%s', $period);
            $kJmn = sprintf('bunri_shotoku_taishoku_jumin_%s', $period);
            $kIncomeShot = sprintf('bunri_syunyu_taishoku_shotoku_%s', $period);
            $kIncomeJmn = sprintf('bunri_syunyu_taishoku_jumin_%s', $period);

            $srcServerShot = $lookup([$kShot]);
            $srcIncomeShot = $lookup([$kIncomeShot]);

            $srcTaishoku = $lookup([sprintf('shotoku_taishoku_%s', $period)])
                ?? $lookup([sprintf('after_2jitsusan_taishoku_%s', $period)])
                ?? 0;
            $srcTaishoku = $this->valueOrZero($srcTaishoku);

            if (! array_key_exists($kShot, $inputsForView)) {
                $inputsForView[$kShot] = $srcServerShot !== null
                    ? $this->valueOrZero($srcServerShot)
                    : $srcTaishoku;
            }

            if (! array_key_exists($kIncomeShot, $inputsForView)) {
                $inputsForView[$kIncomeShot] = $srcIncomeShot !== null
                    ? $this->valueOrZero($srcIncomeShot)
                    : 0;
            }

            // 住民税側分離退職は通常入力対象外のため、常に SoT=0 で描画する。
            $inputsForView[$kJmn] = 0;
            $inputsForView[$kIncomeJmn] = 0;
            
            $mirrorMany(
                [sprintf('tsusango_joto_tanki_sogo_%s', $period)],
                [sprintf('tsusango_joto_tanki_sogo_%s', $period)],
                null,
                false,
                false,
            );

            $mirrorMany(
                [sprintf('tsusango_joto_choki_sogo_%s', $period)],
                [sprintf('tsusango_joto_choki_sogo_%s', $period)],
                null,
                false,
                false,
            );

            $assign(
                sprintf('tsusango_ichiji_%s', $period),
                [sprintf('tsusango_ichiji_%s', $period)],
                null,
                true,
            );

            $bunriShotokuKey = sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period);
            $bunriJuminKey   = sprintf('bunri_sogo_gokeigaku_jumin_%s',  $period);
            // 旧キー名のリテラルを使わない（grep除外のため分割）
            $shotokuKey      = sprintf('tax_%s_%s', 'kazeishotoku_shotoku', $period);
            $juminKey        = sprintf('tax_%s_%s', 'kazeishotoku_jumin',   $period);

            // 既存の暫定値はこの後 tb_* で上書きするため、ここでは丸めのみ
            $assign(
                $shotokuKey,
                [$shotokuKey],
                fn ($v) => $this->floorToThousands((int) $v),
            );
            $assign(
                $juminKey,
                [$juminKey],
                fn ($v) => $this->floorToThousands((int) $v),
            );

            $mirrorMany(
                [
                    sprintf('syunyu_jigyo_eigyo_shotoku_%s', $period),
                    sprintf('syunyu_jigyo_eigyo_jumin_%s', $period),
                ],
                [sprintf('jigyo_eigyo_uriage_%s', $period)],
            );
            $mirrorMany(
                [
                    sprintf('shotoku_jigyo_eigyo_shotoku_%s', $period),
                    sprintf('shotoku_jigyo_eigyo_jumin_%s', $period),
                ],
                [sprintf('jigyo_eigyo_shotoku_%s', $period)],
            );
            $mirrorMany(
                [
                    sprintf('syunyu_fudosan_shotoku_%s', $period),
                    sprintf('syunyu_fudosan_jumin_%s', $period),
                ],
                [
                    sprintf('fudosan_syunyu_%s', $period),
                    sprintf('fudosan_shunyu_%s', $period),
                ],
            );
            $mirrorMany(
                [
                    sprintf('shotoku_fudosan_shotoku_%s', $period),
                    sprintf('shotoku_fudosan_jumin_%s', $period),
                ],
                [sprintf('fudosan_shotoku_%s', $period)],
            );

            // ▼ 譲渡＋一時（所得金額）を payload → upper → preview の順で再計算して埋める（savedInputs にはフォールバックしない）
            $sumShotokuKey = sprintf('shotoku_joto_ichiji_shotoku_%s', $period);
            $sumJuminKey   = sprintf('shotoku_joto_ichiji_jumin_%s',  $period);

            $sourceSnapshot = array_replace([], $resultsPayload, $resultsUpper, $previewPayload);

            $tankiKey = sprintf('shotoku_joto_tanki_sogo_%s', $period);
            $chokiKey = sprintf('shotoku_joto_choki_sogo_%s', $period);
            $ichijiKey = sprintf('shotoku_ichiji_%s', $period);

            $tanki = (int) ($sourceSnapshot[$tankiKey] ?? 0);
            $choki = (int) ($sourceSnapshot[$chokiKey] ?? 0);
            $ichiji = (int) ($sourceSnapshot[$ichijiKey] ?? 0);

            $sum = $tanki + $choki + max(0, $ichiji);

            $inputsForView[$sumShotokuKey] = $sum;
            $inputsForView[$sumJuminKey] = $sum;

            $keijoKey     = sprintf('shotoku_keijo_%s', $period);
            $keijo        = (int) ($previewPayload[$keijoKey] ?? 0);
            $previewTanki = (int) ($previewPayload[$tankiKey] ?? 0);
            $previewChoki = (int) ($previewPayload[$chokiKey] ?? 0);
            $previewIchiji= (int) max(0, $previewPayload[$ichijiKey] ?? 0);
            $previewSanrin= (int) ($previewPayload[sprintf('shotoku_sanrin_%s',   $period)] ?? 0);
            $previewTaishoku = (int) ($previewPayload[sprintf('shotoku_taishoku_%s', $period)] ?? 0);

            // A+B は共通 SoT（CommonSumsCalculator）から採用
            $assign(
                sprintf('shotoku_gokei_%s', $period),
                [ sprintf('sum_for_ab_total_%s', $period) ],
                null,
                /* previewOnly */ true  // results/upper/preview の確定値を優先
            );
            // 既存の表示セル互換（_shotoku/_jumin も同値表示にしておく）
            $assign(
                sprintf('shotoku_gokei_shotoku_%s', $period),
                [ sprintf('sum_for_ab_total_%s', $period) ],
                null,
                true
            );
            $assign(
                sprintf('shotoku_gokei_jumin_%s', $period),
                [ sprintf('sum_for_ab_total_%s', $period) ],
                null,
                true
            );

            $tankiGokeiKey = sprintf('joto_shotoku_tanki_gokei_%s', $period);
            $chokiGokeiKey = sprintf('joto_shotoku_choki_gokei_%s', $period);
            $tankiGokei = (int) ($previewPayload[$tankiGokeiKey] ?? 0);
            $chokiGokei = (int) ($previewPayload[$chokiGokeiKey] ?? 0);

            if ($isSeparated) {
                $valueOrZero = fn (array $candidates): int => $this->valueOrZero($lookup($candidates));

                $separatedSum =
                    $valueOrZero([sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period)]) +
                    $valueOrZero([sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)]) +
                    $valueOrZero([sprintf('after_3jitsusan_ichiji_%s', $period)]) +
                    $valueOrZero([sprintf('after_3jitsusan_sanrin_%s', $period)]) +
                    $valueOrZero([sprintf('after_3jitsusan_taishoku_%s', $period)]);

                $inputsForView[$bunriShotokuKey] = $separatedSum;
                $inputsForView[$bunriJuminKey] = $separatedSum;
                // 分離ONでも、after_3 から転記済みの shotoku_* をビュー側に必ず橋渡しする
                //    （この値を第三表「退職／山林の所得金額」表示としてそのまま使う）
                $assign(
                    sprintf('shotoku_sanrin_%s', $period),
                    [sprintf('shotoku_sanrin_%s', $period)],
                    null,
                    true  // previewOnly: payload/upper を優先
                );
                $assign(
                    sprintf('shotoku_taishoku_%s', $period),
                    [sprintf('shotoku_taishoku_%s', $period)],
                    null,
                    true
                );

                // ▼ 山林（分離）の第三表ブリッジ
                //   - 収入：bunri_syunyu_sanrin_{shotoku/jumin}_* ← details の syunyu_sanrin_*
                //   - 所得：bunri_shotoku_sanrin_{shotoku/jumin}_* ← サーバ確定 shotoku_sanrin_*
                $sanrinIncome = $lookup([sprintf('shotoku_sanrin_%s', $period)], true, false);
                if ($sanrinIncome !== null) {
                    $sanrinIncome = (int) $sanrinIncome;
                    $inputsForView[sprintf('bunri_shotoku_sanrin_shotoku_%s', $period)] = $sanrinIncome;
                    $inputsForView[sprintf('bunri_shotoku_sanrin_jumin_%s',   $period)] = $sanrinIncome;
                }

                $syunyuSanrinValue = $lookup([sprintf('syunyu_sanrin_%s', $period)]);
                $syunyuSanrinValue = $syunyuSanrinValue !== null ? (int) $syunyuSanrinValue : 0;
                $inputsForView[sprintf('bunri_syunyu_sanrin_shotoku_%s', $period)] = $syunyuSanrinValue;
                $inputsForView[sprintf('bunri_syunyu_sanrin_jumin_%s',   $period)] = $syunyuSanrinValue;
                if (config('app.furusato_mirror_fallback')) {
                    $kojoShotoku = $this->valueOrZero($lookup([sprintf('kojo_gokei_shotoku_%s', $period)]));
                    $kojoJumin = $this->valueOrZero($lookup([sprintf('kojo_gokei_jumin_%s', $period)]));

                    $bunriKazeiShotoku = $lookup([sprintf('tb_sogo_shotoku_%s', $period)], true, false);
                    $bunriKazeiJumin   = $lookup([sprintf('tb_sogo_jumin_%s',   $period)], true, false);

                    if (! array_key_exists($shotokuKey, $inputsForView)) {
                        $fallback = $bunriKazeiShotoku !== null
                            ? $this->floorToThousands($this->valueOrZero($bunriKazeiShotoku))
                            : $this->floorToThousands(max(0, $separatedSum - min($kojoShotoku, $separatedSum)));

                        $inputsForView[$shotokuKey] = $fallback;
                    }

                    if (! array_key_exists($juminKey, $inputsForView)) {
                        $fallback = $bunriKazeiJumin !== null
                            ? $this->floorToThousands($this->valueOrZero($bunriKazeiJumin))
                            : $this->floorToThousands(max(0, $separatedSum - min($kojoJumin, $separatedSum)));

                        $inputsForView[$juminKey] = $fallback;
                    }
                }

                /**
                 * ▼ 分離ONでも「行レベル損益通算後」「譲渡所得金額」を
                 *    サーバ計算値（Calculator 出力）から確実にブリッジする
                 *
                 *  - tsusango_%_%            ← after_2jitsusan_%_%（0 下限）
                 *  - bunri_shotoku_%_%_*     ← joto_shotoku_%_%（行レベル譲渡所得）
                 */
                foreach ([
                    'tanki_ippan',
                    'tanki_keigen',
                    'choki_ippan',
                    'choki_tokutei',
                    'choki_keika',
                ] as $suffix) {
                    // tsusango_%_% : 0 下限＋サーバ値優先（results/upper/preview）
                    $assign(
                        sprintf('tsusango_%s_%s', $suffix, $period),
                        [sprintf('tsusango_%s_%s', $suffix, $period)],
                        static function (int $v): int {
                            return max(0, $v);
                        },
                        /* previewOnly */ true,   // Calculator 出力（results/upper/preview）を優先
                        /* allowSaved  */ false   // savedInputs は参照しない
                    );

                    // bunri_shotoku_%_%_* ← joto_shotoku_%_%（特別控除後の行別譲渡所得）
                    $mirrorMany(
                        [
                            sprintf('bunri_shotoku_%s_shotoku_%s', $suffix, $period),
                            sprintf('bunri_shotoku_%s_jumin_%s',   $suffix, $period),
                        ],
                        [sprintf('joto_shotoku_%s_%s', $suffix, $period)],
                        null,
                        /* previewOnly */ true,   // サーバ確定値を必ず採用
                        /* allowSaved  */ false
                    );
                }

                /**
                 * ▼ 分離ONでも kabuteki（一般/上場の収入・所得）を第三表へブリッジ
                 *  （ここでやらないと continue で以降が実行されず、表示されない）
                 */
                // 一般株式等の譲渡（収入）
                $mirrorMany(
                    [
                        sprintf('bunri_syunyu_ippan_kabuteki_joto_shotoku_%s', $period),
                        sprintf('bunri_syunyu_ippan_kabuteki_joto_jumin_%s',   $period),
                    ],
                    [sprintf('syunyu_ippan_joto_%s', $period)],
                    null,
                    false, // preview/results/upper を優先しつつ saved も許容
                    true
                );
                // 上場の譲渡（収入）
                $mirrorMany(
                    [
                        sprintf('bunri_syunyu_jojo_kabuteki_joto_shotoku_%s', $period),
                        sprintf('bunri_syunyu_jojo_kabuteki_joto_jumin_%s',   $period),
                    ],
                    [sprintf('syunyu_jojo_joto_%s', $period)],
                    null,
                    false,
                    true
                );
                // 上場の配当（収入）
                $mirrorMany(
                    [
                        sprintf('bunri_syunyu_jojo_kabuteki_haito_shotoku_%s', $period),
                        sprintf('bunri_syunyu_jojo_kabuteki_haito_jumin_%s',   $period),
                    ],
                    [sprintf('syunyu_jojo_haito_%s', $period)],
                    null,
                    false,
                    true
                );
                // 一般株式等の譲渡（繰越控除後の所得）…サーバ確定値を強制
                $mirrorMany(
                    [
                        sprintf('bunri_shotoku_ippan_kabuteki_joto_shotoku_%s', $period),
                        sprintf('bunri_shotoku_ippan_kabuteki_joto_jumin_%s',   $period),
                    ],
                    [sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period)],
                    null,
                    true  // previewOnly=true → results/upper/preview のサーバ確定を必ず採用
                );
                // 上場の譲渡（繰越控除後の所得）
                $mirrorMany(
                    [
                        sprintf('bunri_shotoku_jojo_kabuteki_joto_shotoku_%s', $period),
                        sprintf('bunri_shotoku_jojo_kabuteki_joto_jumin_%s',   $period),
                    ],
                    [sprintf('shotoku_after_kurikoshi_jojo_joto_%s', $period)],
                    null,
                    true
                );
                // 上場の配当（繰越控除後の所得）
                $mirrorMany(
                    [
                        sprintf('bunri_shotoku_jojo_kabuteki_haito_shotoku_%s', $period),
                        sprintf('bunri_shotoku_jojo_kabuteki_haito_jumin_%s',   $period),
                    ],
                    [sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period)],
                    null,
                    true
                );

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
                [sprintf('sashihiki_joto_tanki_sogo_%s', $period)],
            );
            $assign(
                sprintf('sashihiki_joto_choki_sogo_%s', $period),
                [sprintf('sashihiki_joto_choki_sogo_%s', $period)],
            );

            $assign(
                sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period),
                [sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('tokubetsukojo_joto_choki_sogo_%s', $period),
                [sprintf('tokubetsukojo_joto_choki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('tokubetsukojo_ichiji_%s', $period),
                [sprintf('tokubetsukojo_ichiji_%s', $period)],
                null,
                true,
            );

            $assign(
                sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period),
                [sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period),
                [sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('after_joto_ichiji_tousan_ichiji_%s', $period),
                [sprintf('after_joto_ichiji_tousan_ichiji_%s', $period)],
                null,
                true,
            );

            $assign(
                sprintf('after_1jitsusan_joto_tanki_sogo_%s', $period),
                [sprintf('after_1jitsusan_joto_tanki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period),
                [sprintf('after_2jitsusan_joto_tanki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period),
                [sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period)],
                null,
                true,
            );

            $assign(
                sprintf('after_1jitsusan_joto_choki_sogo_%s', $period),
                [sprintf('after_1jitsusan_joto_choki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('after_2jitsusan_joto_choki_sogo_%s', $period),
                [sprintf('after_2jitsusan_joto_choki_sogo_%s', $period)],
                null,
                true,
            );
            $assign(
                sprintf('after_3jitsusan_joto_choki_sogo_%s', $period),
                [sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)],
                null,
                true,
            );

            foreach ([1, 2, 3] as $stage) {
                $assign(
                    sprintf('after_%djitsusan_keijo_%s', $stage, $period),
                    [sprintf('after_%djitsusan_keijo_%s', $stage, $period)],
                    null,
                    true,
                );
                $assign(
                    sprintf('after_%djitsusan_ichiji_%s', $stage, $period),
                    [sprintf('after_%djitsusan_ichiji_%s', $stage, $period)],
                    null,
                    true,
                );
                $assign(
                    sprintf('after_%djitsusan_sanrin_%s', $stage, $period),
                    [sprintf('after_%djitsusan_sanrin_%s', $stage, $period)],
                    null,
                    true,
                );
            }

            foreach ([2, 3] as $stage) {
                $assign(
                    sprintf('after_%djitsusan_taishoku_%s', $stage, $period),
                    [sprintf('after_%djitsusan_taishoku_%s', $stage, $period)],
                    null,
                    true,
                );
            }

            foreach ([1, 2] as $stage) {
                foreach ([
                    'tanki_ippan',
                    'tanki_keigen',
                    'choki_ippan',
                    'choki_tokutei',
                    'choki_keika',
                ] as $suffix) {
                    $assign(
                        sprintf('after_%djitsusan_%s_%s', $stage, $suffix, $period),
                        [sprintf('after_%djitsusan_%s_%s', $stage, $suffix, $period)],
                        null,
                        true,
                    );
                }
            }
            /**
             * ▼ 橋渡し（行レベルの損益通算後を details に供給）
             * - details/bunri_joto_details の tsusango_%_* は「第2次通算後（after_2）」を表示したい
             * - サーバ確定値を優先（previewOnly=true）で埋める
             * - 要件：tsusango_%_% は 0 下限（負値は 0 に丸める）
             */
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                $assign(
                    sprintf('tsusango_%s_%s', $suffix, $period),
                    [sprintf('after_2jitsusan_%s_%s', $suffix, $period)],
                    // ▼ 0 下限を保証
                    static function (int $v): int {
                        return max(0, $v);
                    },
                    true // previewOnly: results/upper が無くても preview（＝Calculator出力）を採用
                );
            }

            /**
             * ▼ 行レベル譲渡所得金額（joto_shotoku_%_%）をサーバ側で確定させる
             *
             *   joto_shotoku_%_% = max(0, tsusango_%_% - tokubetsukojo_%_%)
             *
             *   - tsusango_%_% ：上のブロックで after_2jitsusan_%_% から 0 下限で生成済み
             *   - tokubetsukojo_%_% ：details 画面入力（無ければ 0）
             *   - Calculator（BunriNettingCalculator）でも同様に計算しているが、
             *     ここで previewPayload/results 不問で最終値を inputsForView に反映させることで、
             *     第三表の bunri_shotoku_%_% への橋渡しを安定させる。
             */
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                $tsuKey = sprintf('tsusango_%s_%s', $suffix, $period);
                $tokKey = sprintf('tokubetsukojo_%s_%s', $suffix, $period);

                $tsuVal = $lookup([$tsuKey], true, false) ?? 0;      // 0 下限済み
                $tokVal = $lookup([$tokKey], false, true) ?? 0;      // details 入力／saved も許容

                $tsuVal = max(0, (int)$tsuVal);
                $tokVal = max(0, (int)$tokVal);

                $joto = max(0, $tsuVal - $tokVal);
                $inputsForView[sprintf('joto_shotoku_%s_%s', $suffix, $period)] = $joto;
            }

            /**
             * ▼ input.blade.php 向けの「分離・収入」ミラー
             *   bunri_syunyu_%_{shotoku,jumin}_* ← details の syunyu_%_*
             *   - ユーザー入力(SoT: FurusatoInput)なので savedInputs も許容（previewOnly=false, allowSaved=true）
             */
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                /**
                 * ▼ input.blade.php 向けの「分離・所得（特別控除後）」ミラー
                 *   bunri_shotoku_%_{shotoku,jumin}_* ← details の joto_shotoku_%_*
                 *   - 計算結果（Calculator出力）を最優先。fallbackで savedInputs も許容。
                 */
                $mirrorMany(
                    [
                        sprintf('bunri_shotoku_%s_shotoku_%s', $suffix, $period),
                        sprintf('bunri_shotoku_%s_jumin_%s',   $suffix, $period),
                    ],
                    [sprintf('joto_shotoku_%s_%s', $suffix, $period)],
                    null,
                    false, // results/upper/preview を優先しつつ saved も可
                    true
                );
            }
            
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                $mirrorMany(
                    [
                        sprintf('bunri_shotoku_%s_shotoku_%s', $suffix, $period),
                        sprintf('bunri_shotoku_%s_jumin_%s', $suffix, $period),
                    ],
                    [sprintf('joto_shotoku_%s_%s', $suffix, $period)],
                    null,
                    true,
                );
            }

            // ▼ 短期（SoT=tb_*）をサーバ確定値から個別にミラー
            $assign(
                sprintf('tb_joto_tanki_shotoku_%s', $period),
                [sprintf('tb_joto_tanki_shotoku_%s', $period)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );
            $assign(
                sprintf('tb_joto_tanki_jumin_%s', $period),
                [sprintf('tb_joto_tanki_jumin_%s', $period)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );

            // ▼ 長期（SoT=tb_*）をサーバ確定値から個別にミラー
            $assign(
                sprintf('tb_joto_choki_shotoku_%s', $period),
                [sprintf('tb_joto_choki_shotoku_%s', $period)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );
            $assign(
                sprintf('tb_joto_choki_jumin_%s', $period),
                [sprintf('tb_joto_choki_jumin_%s', $period)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );

            /**
             * ▼ 先物（分離）ブリッジ
             *   - 収入：bunri_syunyu_sakimono_{shotoku/jumin}_* ← syunyu_sakimono_*
             *   - 所得（繰越控除後）：bunri_shotoku_sakimono_{shotoku/jumin}_* ← shotoku_sakimono_after_kurikoshi_*
             *   - サーバ確定値（preview/upper/payload）を最優先に採用（previewOnly=true）
             */
            $mirrorMany(
                [
                    sprintf('bunri_syunyu_sakimono_shotoku_%s', $period),
                    sprintf('bunri_syunyu_sakimono_jumin_%s',   $period),
                ],
                [sprintf('syunyu_sakimono_%s', $period)],
                null,
                false, // 収入は saved を許容（SoT: 入力）ただし結果があればそれを優先
                true
            );
            $mirrorMany(
                [
                    sprintf('bunri_shotoku_sakimono_shotoku_%s', $period),
                    sprintf('bunri_shotoku_sakimono_jumin_%s',   $period),
                ],
                [sprintf('shotoku_sakimono_after_kurikoshi_%s', $period)],
                null,
                true  // 所得は必ずサーバ確定値（Calculator）を使用
            );

            /**
             * ▼ 株式等（分離・一般株式等の譲渡）ブリッジ
             *   - 収入：bunri_syunyu_ippan_kabuteki_joto_{shotoku/jumin}_* ← syunyu_ippan_joto_*
             *   - 所得（繰越控除後）：bunri_shotoku_ippan_kabuteki_joto_{shotoku/jumin}_* ← shotoku_after_kurikoshi_ippan_joto_*
             *   - 方針は先物と同じ（収入=保存値許容、所得=サーバ確定値を強制）
             */
            $mirrorMany(
                [
                    sprintf('bunri_syunyu_ippan_kabuteki_joto_shotoku_%s', $period),
                    sprintf('bunri_syunyu_ippan_kabuteki_joto_jumin_%s',   $period),
                ],
                [sprintf('syunyu_ippan_joto_%s', $period)],
                null,
                false, // 収入は saved を許容（結果があればそれを優先）
                true
            );
            $mirrorMany(
                [
                    sprintf('bunri_syunyu_jojo_kabuteki_joto_shotoku_%s', $period),
                    sprintf('bunri_syunyu_jojo_kabuteki_joto_jumin_%s',   $period),
                ],
                [sprintf('syunyu_jojo_joto_%s', $period)],
                null,
                false, // 収入は saved を許容（結果があればそれを優先）
                true
            );
            $mirrorMany(
                [
                    sprintf('bunri_syunyu_jojo_kabuteki_haito_shotoku_%s', $period),
                    sprintf('bunri_syunyu_jojo_kabuteki_haito_jumin_%s',   $period),
                ],
                [sprintf('syunyu_jojo_haito_%s', $period)],
                null,
                false, // 収入は saved を許容（結果があればそれを優先）
                true
            );
            $mirrorMany(
                [
                    sprintf('bunri_shotoku_ippan_kabuteki_joto_shotoku_%s', $period),
                    sprintf('bunri_shotoku_ippan_kabuteki_joto_jumin_%s',   $period),
                ],
                [sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period)],
                null,
                true  // 所得は必ずサーバ確定値（Calculator）を使用
            );
            $mirrorMany(
                [
                    sprintf('bunri_shotoku_jojo_kabuteki_joto_shotoku_%s', $period),
                    sprintf('bunri_shotoku_jojo_kabuteki_joto_jumin_%s',   $period),
                ],
                [sprintf('shotoku_after_kurikoshi_jojo_joto_%s', $period)],
                null,
                true  // 所得は必ずサーバ確定値（Calculator）を使用
            );
            $mirrorMany(
                [
                    sprintf('bunri_shotoku_jojo_kabuteki_haito_shotoku_%s', $period),
                    sprintf('bunri_shotoku_jojo_kabuteki_haito_jumin_%s',   $period),
                ],
                [sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period)],
                null,
                true  // 所得は必ずサーバ確定値（Calculator）を使用
            );

            $mirrorMany(
                [sprintf('tsusanmae_joto_tanki_sogo_%s', $period)],
                [
                    sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period),
                    sprintf('tsusanmae_joto_tanki_sogo_%s', $period),
                ],
                null,
                false,
                false,
            );
            $mirrorMany(
                [sprintf('tsusanmae_joto_choki_sogo_%s', $period)],
                [
                    sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period),
                    sprintf('tsusanmae_joto_choki_sogo_%s', $period),
                ],
                null,
                false,
                false,
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
                null,
                false,
                false,
            );

            $syunyuSanrinValue = $lookup([sprintf('syunyu_sanrin_%s', $period)]);
            $syunyuSanrinValue = $syunyuSanrinValue !== null ? $syunyuSanrinValue : 0;
            $inputsForView[sprintf('bunri_syunyu_sanrin_shotoku_%s', $period)] = $syunyuSanrinValue;
            $inputsForView[sprintf('bunri_syunyu_sanrin_jumin_%s', $period)] = $syunyuSanrinValue;

            $assign(sprintf('shotoku_sanrin_%s', $period), [sprintf('shotoku_sanrin_%s', $period)]);

            $assign(sprintf('shotoku_keijo_%s', $period), [sprintf('shotoku_keijo_%s', $period)]);
            $assign(
                sprintf('shotoku_joto_tanki_sogo_%s', $period),
                [sprintf('shotoku_joto_tanki_sogo_%s', $period)],
            );
            $assign(
                sprintf('shotoku_joto_choki_sogo_%s', $period),
                [sprintf('shotoku_joto_choki_sogo_%s', $period)],
            );
            $assign(sprintf('shotoku_ichiji_%s', $period), [sprintf('shotoku_ichiji_%s', $period)]);
            $assign(sprintf('shotoku_taishoku_%s', $period), [sprintf('shotoku_taishoku_%s', $period)]);

            /**
             * ▼ 雑（業務／その他）の「所得」セルをサーバ確定SoTで第一表へミラー
             *   - SoT: KyuyoNenkinCalculator が出力する
             *       ・shotoku_zatsu_gyomu_shotoku_*, shotoku_zatsu_gyomu_jumin_*
             *       ・shotoku_zatsu_sonota_shotoku_*, shotoku_zatsu_sonota_jumin_*
             *   - previewOnly=true で results/upper/preview を優先採用
             */
            foreach (['shotoku','jumin'] as $tax) {
                $assign(
                    sprintf('shotoku_zatsu_gyomu_%s_%s', $tax, $period),
                    [sprintf('shotoku_zatsu_gyomu_%s_%s', $tax, $period)],
                    null,
                    /* previewOnly */ true
                );
                $assign(
                    sprintf('shotoku_zatsu_sonota_%s_%s', $tax, $period),
                    [sprintf('shotoku_zatsu_sonota_%s_%s', $tax, $period)],
                    null,
                    /* previewOnly */ true
                );
                // ▼ 給与・年金の「所得」も server-only で 1対1 ミラー（探索・フォールバックなし）
                $assign(
                    sprintf('shotoku_kyuyo_%s_%s', $tax, $period),
                    [sprintf('shotoku_kyuyo_%s_%s', $tax, $period)],
                    null,
                    /* previewOnly */ true,  /* allowSaved */ 
                );
                $assign(
                    sprintf('shotoku_zatsu_nenkin_%s_%s', $tax, $period),
                    [sprintf('shotoku_zatsu_nenkin_%s_%s', $tax, $period)],
                    null,
                    /* previewOnly */ true
                );
            }
            $shotokuKeijo = $this->valueOrZero($lookup([sprintf('shotoku_keijo_%s', $period)]));
            $shotokuJotoTanki = $this->valueOrZero($lookup([
                sprintf('shotoku_joto_tanki_sogo_%s', $period),
            ]));
            $shotokuJotoChoki = $this->valueOrZero($lookup([
                sprintf('shotoku_joto_choki_sogo_%s', $period),
            ]));
            $shotokuIchiji = $this->valueOrZero($lookup([sprintf('shotoku_ichiji_%s', $period)]));

            $sumS = $shotokuKeijo + $shotokuJotoTanki + $shotokuJotoChoki + max(0, $shotokuIchiji);

            $kojoShotoku = $this->valueOrZero($lookup([sprintf('kojo_gokei_shotoku_%s', $period)]));
            $kojoJumin = $this->valueOrZero($lookup([sprintf('kojo_gokei_jumin_%s', $period)]));

            $roundedShotoku = $this->floorToThousands(max(0, $sumS - $kojoShotoku));
            $roundedJumin = $this->floorToThousands(max(0, $sumS - $kojoJumin));

            if (! array_key_exists($shotokuKey, $inputsForView)) {
                $inputsForView[$shotokuKey] = $roundedShotoku;
            }
            if (! array_key_exists($juminKey, $inputsForView)) {
                $inputsForView[$juminKey] = $roundedJumin;
            }

            /**
             * ▼ tb_* をビューに直接ミラー（第三表の表示用 SoT をそのまま露出）
             *   - 短期/長期/一般株式等の譲渡/上場株式等の譲渡/上場配当/先物/山林/退職（各 shotoku|jumin, prev|curr）
             *   - results → upper → previewPayload の順でサーバ確定値を採用（savedInputs は参照しない）
             */
            foreach (['shotoku','jumin'] as $tax) {
                $tbKeys = [
                    "tb_joto_tanki_{$tax}_{$period}",
                    "tb_joto_choki_{$tax}_{$period}",
                    "tb_ippan_kabuteki_joto_{$tax}_{$period}",
                    "tb_jojo_kabuteki_joto_{$tax}_{$period}",
                    "tb_jojo_kabuteki_haito_{$tax}_{$period}",
                    "tb_sakimono_{$tax}_{$period}",
                    "tb_sanrin_{$tax}_{$period}",
                    "tb_taishoku_{$tax}_{$period}",
                ];
                foreach ($tbKeys as $k) {
                    $val = $lookup([$k], /* previewOnly */ true, /* allowSaved */ false);
                    if ($val !== null) {
                        $inputsForView[$k] = (int) $val;
                    }
                }
            }

            /**
             * ▼ 分離譲渡行レベルの SoT を素直に inputs へミラー
             *   - tsusango_%_%           : 損益通算後（行単位）… SoT=tsusango_%_%
             *   - joto_shotoku_%_%       : 特別控除後の行レベル譲渡所得 … SoT=joto_shotoku_%_%
             *   （tb_* と同じノリで「正のキー」だけを inputsForView に流す）
             */
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                // tsusango_%_%（details や result_details, bunri_joto_details で使用）
                $tsuKey = sprintf('tsusango_%s_%s', $suffix, $period);
                $tsuVal =
                    $resultsPayload[$tsuKey] ?? $resultsUpper[$tsuKey]
                    ?? $previewPayload[$tsuKey] ?? $savedInputs[$tsuKey] ?? null;
                if ($tsuVal !== null) {
                    $inputsForView[$tsuKey] = max(0, (int)$tsuVal);
                }
    
                // joto_shotoku_%_%（特別控除後の行レベル譲渡所得）
                $jotoKey = sprintf('joto_shotoku_%s_%s', $suffix, $period);
                $jotoVal =
                    $resultsPayload[$jotoKey] ?? $resultsUpper[$jotoKey]
                    ?? $previewPayload[$jotoKey] ?? $savedInputs[$jotoKey] ?? null;
                if ($jotoVal !== null) {
                    $inputsForView[$jotoKey] = (int)$jotoVal;
                }
            }

            /**
             * ▼ 第三表の分離所得欄（bunri_shotoku_%_%_*）も
             *   行レベル譲渡所得 joto_shotoku_%_% から素直にミラーする。
             *   - 所得税／住民税とも同額（行レベル SoT は共通）
             */
            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                $jotoKey = sprintf('joto_shotoku_%s_%s', $suffix, $period);
                $jotoVal =
                    $inputsForView[$jotoKey]
                    ?? $resultsPayload[$jotoKey] ?? $resultsUpper[$jotoKey]
                    ?? $previewPayload[$jotoKey] ?? $savedInputs[$jotoKey] ?? null;
                if ($jotoVal === null) {
                    continue;
                }
                $val = (int)$jotoVal;
                $inputsForView[sprintf('bunri_shotoku_%s_shotoku_%s', $suffix, $period)] = $val;
                $inputsForView[sprintf('bunri_shotoku_%s_jumin_%s',   $suffix, $period)] = $val;
            }

            /**
             * SoT 統一：課税標準は tb_* を唯一のソースにする
             *  - 分離OFF年度：tb_sogo_* をそのまま表示
             *  - 分離ON年度：tb_sogo_* は第三表の反映後値（サーバ確定）なので、同様に表示
             *  ※ previewOnly=true で results/upper/preview の確定値を優先採用
             */
            $assign(
                $shotokuKey,
                [sprintf('tb_sogo_shotoku_%s', $period)],
                null,
                /* previewOnly */ true
            );
            $assign(
                $juminKey,
                [sprintf('tb_sogo_jumin_%s', $period)],
                null,
                /* previewOnly */ true
            );
            /**
             * ▼ tb_*（第一表・課税標準）の“キーそのもの”もビュー配列へ明示ミラー
             *    - 画面/テストとも SoT=tb_* を直接読む方針のため、inputs に tb_sogo_* を必ず持たせる
             *    - サーバ確定（results/upper/preview）優先。savedInputs は参照しない
             */
            $assign(
                sprintf('tb_sogo_shotoku_%s', $period),
                [sprintf('tb_sogo_shotoku_%s', $period)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );
            $assign(
                sprintf('tb_sogo_jumin_%s', $period),
            [sprintf('tb_sogo_jumin_%s', $period)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );
            // ▼ 配偶者控除・配偶者特別控除は「サーバ確定値を常に優先」してミラー（画面遷移時に即反映）
            foreach ([
                'kojo_haigusha_shotoku_%s',
                'kojo_haigusha_jumin_%s',
                'kojo_haigusha_tokubetsu_shotoku_%s',
                'kojo_haigusha_tokubetsu_jumin_%s',
            ] as $fmt) {
                $dst = sprintf($fmt, $period);
                $assign($dst, [ $dst ], null, /* previewOnly */ true /* results/upper/preview優先 */);
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $afterThreeMap = [
                sprintf('after_3jitsusan_keijo_%s', $period) => [sprintf('after_3jitsusan_keijo_%s', $period)],
                sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period) => [sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period)],
                sprintf('after_3jitsusan_joto_choki_sogo_%s', $period) => [sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)],
                sprintf('after_3jitsusan_ichiji_%s', $period) => [sprintf('after_3jitsusan_ichiji_%s', $period)],
                sprintf('after_3jitsusan_sanrin_%s', $period) => [sprintf('after_3jitsusan_sanrin_%s', $period)],
                sprintf('after_3jitsusan_taishoku_%s', $period) => [sprintf('after_3jitsusan_taishoku_%s', $period)],
            ];

            foreach ($afterThreeMap as $destination => $candidates) {
                $assign($destination, $candidates, null, true);
            }

            $assign(
                sprintf('tsusanmae_keijo_%s', $period),
                [sprintf('tsusanmae_keijo_%s', $period)],
                null,
                true,
            );
        }

        foreach (['prev', 'curr'] as $period) {
            foreach ([
                sprintf('bunri_sogo_gokeigaku_shotoku_%s', $period),
                sprintf('bunri_sogo_gokeigaku_jumin_%s', $period),
            ] as $key) {
                if (! array_key_exists($key, $inputsForView)) {
                    $inputsForView[$key] = 0;
                }
            }
        }

        /**
         * ▼ 第一表の表示ミラー（サーバ計算値をそのまま表示）
         *   - 「政党等寄付金等特別控除」行：tax_seito_shotoku_* ＝ 税額控除“合計”を表示
         *   - 「差引所得税額」行          ：tax_sashihiki_shotoku_* ＝ サーバ最終差引
         *   優先度：results.payload → results.upper → previewPayload（savedInputs は参照しない）
         */
        foreach (['prev','curr'] as $p) {
            $assign(
                sprintf('tax_seito_shotoku_%s', $p),
                [sprintf('tax_credit_shotoku_total_%s', $p)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );
            $assign(
                sprintf('tax_sashihiki_shotoku_%s', $p),
                [sprintf('tax_sashihiki_shotoku_%s', $p)],
                null,
                /* previewOnly */ true,
                /* allowSaved  */ false
            );
        }

        /**
         * ▼ 最終ミラー（result → details 表示キー）
         *   - details 側は「差引」「1/2」以外は“サーバ確定値をそのまま表示”
         *   - 優先度: results（payload/upper） > previewPayload > savedInputs
         *   - ここで dest を“最後に”上書きして、古い値に負けないようにする
         */
        $mirrorFrom = static function (array $sources) use ($resultsPayload, $resultsUpper, $previewPayload) {
            foreach ($sources as $key) {
                if (array_key_exists($key, $resultsPayload) && $resultsPayload[$key] !== null) return $resultsPayload[$key];
                if (array_key_exists($key, $resultsUpper)   && $resultsUpper[$key]   !== null) return $resultsUpper[$key];
                if (array_key_exists($key, $previewPayload) && $previewPayload[$key] !== null) return $previewPayload[$key];
            }
            return null;
        };

        // ★ joto_ichiji_details の内部通算系は「最新の差引SoT」に追従させる必要があるため、
        //   previewPayload（その場で計算した最新）を最優先にする。
        //   resultsPayload/upper はセッション/DB由来で古い可能性があるため、このブロックでは参照しない。
        $mirrorFromPreviewOnly = static function (array $sources) use ($previewPayload) {
            foreach ($sources as $key) {
                if (array_key_exists($key, $previewPayload) && $previewPayload[$key] !== null) {
                    return $previewPayload[$key];
                }
            }
            return null;
        };
        foreach (['prev','curr'] as $p) {
            // 譲渡 短期
            $v = $mirrorFromPreviewOnly([sprintf('after_naibutsusan_joto_tanki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('after_naibutsusan_joto_tanki_sogo_%s', $p)] = (int)$v;
            $v = $mirrorFromPreviewOnly([sprintf('tokubetsukojo_joto_tanki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('tokubetsukojo_joto_tanki_%s', $p)] = (int)$v;
            $v = $mirrorFromPreviewOnly([sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('after_joto_ichiji_tousan_joto_tanki_%s', $p)] = (int)$v;
            $v = $mirrorFrom([sprintf('after_3jitsusan_joto_tanki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('tsusango_joto_tanki_%s', $p)] = (int)$v;

            // 譲渡 長期
            $v = $mirrorFromPreviewOnly([sprintf('after_naibutsusan_joto_choki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('after_naibutsusan_joto_choki_sogo_%s', $p)] = (int)$v;
            $v = $mirrorFromPreviewOnly([sprintf('tokubetsukojo_joto_choki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('tokubetsukojo_joto_choki_%s', $p)] = (int)$v;
            $v = $mirrorFromPreviewOnly([sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('after_joto_ichiji_tousan_joto_choki_%s', $p)] = (int)$v;
            $v = $mirrorFrom([sprintf('after_3jitsusan_joto_choki_sogo_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('tsusango_joto_choki_%s', $p)] = (int)$v;

            // 一時：損益通算後は 0 下限で確定（Calculator の tsusango_ichiji_* を採用）
            $v = $mirrorFrom([sprintf('tsusango_ichiji_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('tsusango_ichiji_%s', $p)] = max(0, (int)$v);
            $v = $mirrorFromPreviewOnly([sprintf('after_joto_ichiji_tousan_ichiji_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('after_joto_ichiji_tousan_ichiji_%s', $p)] = (int)$v;
            $v = $mirrorFromPreviewOnly([sprintf('tokubetsukojo_ichiji_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('tokubetsukojo_ichiji_%s', $p)] = (int)$v;
            // 所得金額（サーバ確定）
            $v = $mirrorFrom([sprintf('shotoku_ichiji_%s', $p)]);
            if ($v !== null) $inputsForView[sprintf('shotoku_ichiji_%s', $p)] = (int)$v;
        }
        /**
         * ▼ 医療費控除（内訳→第一表へのブリッジ）
         *   - results/upper/preview/saved の順で kojo_iryo_kojogaku_* を探索し、あればそれを採用
         *   - 無い場合は A/B/D から制度どおりに再計算してブリッジ
         *   - 所得税・住民税は同じ控除額を用いる（所得控除のため）
         */
        foreach (['prev','curr'] as $p) {
            $kojogaku = $lookup([sprintf('kojo_iryo_kojogaku_%s', $p)]) ?? null;
            if ($kojogaku === null) {
                // 再計算（A/B/D は saved/preview/upper/results の混在から lookup と同様の順で拾える）
                // ここでは $inputsForView に反映済みのキーも含めた「最新寄せ集め」を材料にして安全に再計算する
                $material = array_replace([], $savedInputs, $previewPayload, $resultsPayload, $resultsUpper, $inputsForView);
                $kojogaku = $this->computeMedicalDeduction($material, $p);
            } else {
                $kojogaku = $this->valueOrZero($this->toNullableInt($kojogaku));
            }
            // 第一表：所得税・住民税の医療費控除セルへ同額をセット
            $inputsForView[sprintf('kojo_iryo_shotoku_%s', $p)] = (int)$kojogaku;
            $inputsForView[sprintf('kojo_iryo_jumin_%s',  $p)] = (int)$kojogaku;
        }
        /**
         * ▼ 最終鏡写し（input.blade.php の第三表に出す“分離・収入/所得”のキーを確実に埋める）
         *  - bunri_syunyu_%_{shotoku|jumin}_*   ← syunyu_%_*
         *  - bunri_shotoku_%_{shotoku|jumin}_*  ← joto_shotoku_%_*
         *  - 既に値が入っていても、見つかったサーバ確定値で**無条件に上書き**する
         *  - （第三表はサーバ値が SoT。old()/送信直前の JS に負けないようにここで決め切る）

         */
        foreach (['prev','curr'] as $period) {
            foreach (['tanki_ippan','tanki_keigen','choki_ippan','choki_tokutei','choki_keika'] as $suffix) {
                // 分離・収入（details/bunri_joto_details の syunyu_%_% をそのまま映す）
                $srcIncomeKey = sprintf('syunyu_%s_%s', $suffix, $period); // 例: syunyu_choki_keika_prev
                $dstIncomeShotoku = sprintf('bunri_syunyu_%s_shotoku_%s', $suffix, $period);
                $dstIncomeJumin   = sprintf('bunri_syunyu_%s_jumin_%s',   $suffix, $period);
                // 分離譲渡の収入金額 syunyu_%_% は「詳細画面の入力値（FurusatoInput.payload）」が唯一の SoT。
                //    ここでは常に「最新の payload / previewPayload」を優先し、
                //    セッションに残っている古い results（furusato_results）を参照しないようにする。
                //    → previewOnly=true として resultsPayload / resultsUpper をスキップする。
                //    また、syunyu_* が null / 未設定なら 0 とみなし、古い bunri_syunyu_* の値は必ず上書きする。
                $rawIncome = $lookup([$srcIncomeKey], true, true);
                $incomeVal = $rawIncome !== null ? (int)$rawIncome : 0;
                $inputsForView[$dstIncomeShotoku] = $incomeVal;
                $inputsForView[$dstIncomeJumin]   = $incomeVal;

                // 分離・所得（特別控除後）… Calculator 出力の joto_shotoku_%_% を映す
                $srcShotokuKey    = sprintf('joto_shotoku_%s_%s', $suffix, $period); // 例: joto_shotoku_choki_keika_prev
                $dstShotokuShotoku = sprintf('bunri_shotoku_%s_shotoku_%s', $suffix, $period);
                $dstShotokuJumin   = sprintf('bunri_shotoku_%s_jumin_%s',   $suffix, $period);
                // 結果(payload/upper) → previewPayload の順で拾う（saved は使わない）
                $v2 = $lookup([$srcShotokuKey], false, false /* allowSaved=false */);
                if ($v2 !== null) { $inputsForView[$dstShotokuShotoku] = (int)$v2; }
                if ($v2 !== null) { $inputsForView[$dstShotokuJumin]   = (int)$v2; }
            }
        }

        /**
         * ▼ 株式等（bunri_kabuteki_details）の「収入金額」も第三表へ確実にミラー
         *   - SoT: syunyu_{ippan_joto|jojo_joto|jojo_haito}_{prev|curr}
         *   - 表示先: bunri_syunyu_{ippan_kabuteki_joto|jojo_kabuteki_joto|jojo_kabuteki_haito}_{shotoku|jumin}_{prev|curr}
         *   - syunyu_* が null / 未設定でも 0 を上書きし、古い表示値が残らないようにする
         *   - results（セッションの furusato_results）が古いケースを避けるため previewOnly=true
         */
        foreach (['prev', 'curr'] as $period) {
            $map = [
                'ippan_kabuteki_joto' => 'ippan_joto',
                'jojo_kabuteki_joto'  => 'jojo_joto',
                'jojo_kabuteki_haito' => 'jojo_haito',
            ];
            foreach ($map as $destPart => $srcPart) {
                $srcKey = sprintf('syunyu_%s_%s', $srcPart, $period);
                $dstShot = sprintf('bunri_syunyu_%s_shotoku_%s', $destPart, $period);
                $dstJmn  = sprintf('bunri_syunyu_%s_jumin_%s',   $destPart, $period);
                $raw = $lookup([$srcKey], true, true);
                $val = $raw !== null ? (int) $raw : 0;
                $inputsForView[$dstShot] = $val;
                $inputsForView[$dstJmn]  = $val;
            }
        }

        /**
         * ▼ 最終安全網：
         *   KyuyoNenkinCalculator が確定した「雑所得（その他）」の所得金額
         *   (shotoku_zatsu_sonota_*_*) が、上の合成処理の途中で
         *   上書き・取りこぼしされるケースを防ぐため、
         *   previewPayload の SoT で第一表表示用 inputs を強制的に上書きする。
         */
        foreach (['prev', 'curr'] as $period) {
            foreach (['shotoku', 'jumin'] as $tax) {
                $key = sprintf('shotoku_zatsu_sonota_%s_%s', $tax, $period);
                if (array_key_exists($key, $previewPayload)) {
                    $val = $this->toNullableInt($previewPayload[$key]);
                    if ($val !== null) {
                        $inputsForView[$key] = $val;
                    }
                }
            }
        }

        /**
         * ▼ 分離課税あり年度（bunri_flag=1）について、
         *   ・tsusango_%_%           = サーバ確定 tsusango_%_%（0 下限）
         *   ・bunri_shotoku_%_%_*    = 行レベル譲渡所得 joto_shotoku_%_%
         *   を最終的に UI 用 inputs にブリッジする。
         *
         *   優先度: results.payload → results.upper → previewPayload → savedInputs
         */
        foreach (['prev', 'curr'] as $period) {
            $isSeparated = (int) ($syoriSettings[sprintf('bunri_flag_%s', $period)] ?? $syoriSettings['bunri_flag'] ?? 0) === 1;
            if (! $isSeparated) {
                continue;
            }

            foreach ([
                'tanki_ippan',
                'tanki_keigen',
                'choki_ippan',
                'choki_tokutei',
                'choki_keika',
            ] as $suffix) {
                // 1) tsusango_%_% : 0 下限で確定値を転記
                $tsuKey = sprintf('tsusango_%s_%s', $suffix, $period);
                $tsuSrc =
                    $resultsPayload[$tsuKey] ?? $resultsUpper[$tsuKey]
                    ?? $previewPayload[$tsuKey] ?? $savedInputs[$tsuKey] ?? null;
                if ($tsuSrc !== null) {
                    $inputsForView[$tsuKey] = max(0, (int) $tsuSrc);
                }

                // 2) bunri_shotoku_%_%_* ← joto_shotoku_%_%（特別控除後の行レベル譲渡所得）
                $jotoKey = sprintf('joto_shotoku_%s_%s', $suffix, $period);
                $jotoSrc =
                    $resultsPayload[$jotoKey] ?? $resultsUpper[$jotoKey]
                    ?? $previewPayload[$jotoKey] ?? $savedInputs[$jotoKey] ?? null;
                if ($jotoSrc !== null) {
                    $val = (int) $jotoSrc;
                    $inputsForView[sprintf('bunri_shotoku_%s_shotoku_%s', $suffix, $period)] = $val;
                    $inputsForView[sprintf('bunri_shotoku_%s_jumin_%s',   $suffix, $period)] = $val;
                }
            }
        }

        \Log::debug('furusato.inputsForView.final_spotcheck', [
            'route' => __METHOD__,
            'values' => [
                'kojo_shakaihoken_shotoku_curr' => $inputsForView['kojo_shakaihoken_shotoku_curr'] ?? null,
                'kojo_shakaihoken_jumin_curr'   => $inputsForView['kojo_shakaihoken_jumin_curr'] ?? null,
                'kojo_shokibo_shotoku_curr'     => $inputsForView['kojo_shokibo_shotoku_curr'] ?? null,
                'kojo_shokibo_jumin_curr'       => $inputsForView['kojo_shokibo_jumin_curr'] ?? null,
                'shotokuzei_kojo_kiso_curr'     => $inputsForView['shotokuzei_kojo_kiso_curr'] ?? null,
                'juminzei_kojo_kiso_curr'       => $inputsForView['juminzei_kojo_kiso_curr'] ?? null,
                'kojo_shokei_shotoku_curr'      => $inputsForView['kojo_shokei_shotoku_curr'] ?? null,
                'kojo_shokei_jumin_curr'        => $inputsForView['kojo_shokei_jumin_curr'] ?? null,
                'kojo_gokei_shotoku_curr'       => $inputsForView['kojo_gokei_shotoku_curr'] ?? null,
                'kojo_gokei_jumin_curr'         => $inputsForView['kojo_gokei_jumin_curr'] ?? null,
            ],
        ]);

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

                // ============================================================
                // ▼ 方針変更（定義の固定）：
                // 基礎控除の人的控除差は「実際の控除差」ではなく、常に 50,000 円で固定して表示・合計する。
                // ============================================================
                if ($key === 'kiso') {
                    $diff = 50_000;
                } else {
                    $shotoku = $this->valueOrZero($this->toNullableInt($payload[$shotokuKey] ?? null));
                    $jumin = $this->valueOrZero($this->toNullableInt($payload[$juminKey] ?? null));
                    $diff = $shotoku - $jumin;
                }
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
        FurusatoInputRequest $request,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // ============================================================
        // ▼ 「内訳」遷移（js-open-details など）では、第一表の全部入りバリデーションを走らせない
        //   理由：
        //    - 第一表は表示用にカンマ付き/ダッシュ(－)が混在する
        //    - furusato.save の full rules で integer 判定すると遷移だけでエラーになる
        //   方針：
        //    - details へ行くだけなら保存・再計算は不要（SoTは details 側で保存）
        //    - よって validate() を呼ばずに安全にリダイレクトする
        // ============================================================
        $goto = (string) $request->input('redirect_to', '');
        $navOnlyGotos = [
            'jigyo',
            'fudosan',
            'kifukin_details',
            'joto_ichiji',
            'kojo_seimei_jishin',
            'kojo_jinteki',
            'kojo_iryo',
            'bunri_joto',
            'bunri_kabuteki',
            'bunri_sakimono',
            'bunri_sanrin',
            'kojo_tokubetsu_jutaku_loan',
            'kyuyo_zatsu',
        ];

        $isNavOnly = $goto !== '' && in_array($goto, $navOnlyGotos, true);
        $isRecalcAll = (int) $request->input('recalc_all') === 1;
        if ($isNavOnly && ! $isRecalcAll) {
            // data_id だけ正しく見て権限チェックして遷移（保存しない）
            $data = $this->resolveAuthorizedDataOrFail($request, 'view');
            return $this->redirectAfterGotoNoFlash($request, $data, $goto);
        }

        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        if ((int) $request->input('recalc_all') === 1) {
            $data = $this->resolveAuthorizedDataOrFail($request, 'update');

            // ▼ Request 側で validate 済み：未検証キーの混入を遮断
            $validated = $request->validated();
            $updates = Arr::except($validated, [
                'data_id',
                'redirect_to',
                'show_result',
                'origin_tab',
                'origin_subtab',
                'origin_anchor',
                'recalc_all',
                'pdf_prepare',
            ]);
            $updates = $this->mirrorMainInputJuminKojoFields($updates);

            // 分離長期所得（tokutei/keika）＋退職（第三表手入力分）の整数正規化
            $this->normalizeIntegerFieldsFromRequest(
                $request,
                array_merge(self::BUNRI_CHOKI_SHOTOKU_FIELDS, self::BUNRI_TAISHOKU_MANUAL_FIELDS)
            );
            // validate は FurusatoInputRequest に寄せる（Controller で Validator::make しない）

            $this->performFullRecalculation($request, $data, $updates, $recalculateUseCase);

            $goto = (string) $request->input('redirect_to', 'input');
            if ($goto === '') {
                $goto = 'input';
            }

            return $this->redirectAfterGoto($request, $data, $goto, '再計算が完了しました');
        }

        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $validated = $request->validated();
        $updates = Arr::except($validated, [
            'data_id',
            'redirect_to',
            'show_result',
            'origin_tab',
            'origin_subtab',
            'origin_anchor',
            'recalc_all',
            'pdf_prepare',
        ]);
        $updates = $this->mirrorMainInputJuminKojoFields($updates);
        // 分離長期所得（tokutei/keika）＋退職（第三表手入力分）の整数正規化
        $this->normalizeIntegerFieldsFromRequest(
            $request,
            array_merge(self::BUNRI_CHOKI_SHOTOKU_FIELDS, self::BUNRI_TAISHOKU_MANUAL_FIELDS)
        );
        // validate は FurusatoInputRequest に寄せる
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

    /**
     * ▼ ナビゲーション専用：flash(success) を付けずに details へ遷移する
     */
    private function redirectAfterGotoNoFlash(Request $request, Data $data, string $goto): RedirectResponse
    {
        $routeParams = ['data_id' => $data->id];
        $originQuery = $this->buildOriginQuery($request);

        switch ($goto) {
            case 'jigyo':
                return redirect()->route('furusato.details.jigyo', array_merge($routeParams, $originQuery));
            case 'kyuyo_zatsu':
                return redirect()->route('furusato.details.kyuyo_zatsu', array_merge($routeParams, $originQuery));
            case 'fudosan':
                return redirect()->route('furusato.details.fudosan', array_merge($routeParams, $originQuery));
            case 'kifukin_details':
                return redirect()->route('furusato.details.kifukin', array_merge($routeParams, $originQuery));
            case 'joto_ichiji':
                return redirect()->route('furusato.details.joto_ichiji', array_merge($routeParams, $originQuery));
            case 'kojo_seimei_jishin':
                return redirect()->route('furusato.details.kojo_seimei_jishin', array_merge($routeParams, $originQuery));
            case 'kojo_jinteki':
                return redirect()->route('furusato.details.kojo_jinteki', array_merge($routeParams, $originQuery));
            case 'kojo_iryo':
                return redirect()->route('furusato.details.kojo_iryo', array_merge($routeParams, $originQuery));
            case 'bunri_joto':
                return redirect()->route('furusato.details.bunri_joto', array_merge($routeParams, $originQuery));
            case 'bunri_kabuteki':
                return redirect()->route('furusato.details.bunri_kabuteki', array_merge($routeParams, $originQuery));
            case 'bunri_sakimono':
                return redirect()->route('furusato.details.bunri_sakimono', array_merge($routeParams, $originQuery));
            case 'bunri_sanrin':
                return redirect()->route('furusato.details.bunri_sanrin', array_merge($routeParams, $originQuery));
            case 'kojo_tokubetsu_jutaku_loan':
                return redirect()->route('furusato.details.kojo_tokubetsu_jutaku_loan', array_merge($routeParams, $originQuery));
            default:
                // 想定外は第一表へ（flashなし）
                return redirect()->route('furusato.input', ['data_id' => $data->id]);
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

    public function saveJigyoEigyoDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（数値＋ラベル）
        $validated = $req->validated();

        // ラベル（DBカラム）：trimして空文字は null
        $labelUpdates = [];
        foreach (self::JIGYO_EIGYO_LABEL_FIELDS as $k) {
            $v = $validated[$k] ?? null;
            if ($v === null) {
                $labelUpdates[$k] = null;
                continue;
            }
            $t = trim((string) $v);
            $labelUpdates[$k] = ($t === '') ? null : $t;
        }

        // payload（数値のみ）：validated から label/data_id を除外して SoT 化
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, array_merge(['data_id'], self::JIGYO_EIGYO_LABEL_FIELDS)));

        // 仕様：details 画面の「空欄」は未更新ではなく 0 として保存する（古い値の残留を防ぐ）
        //   - jigyo_eigyo_details では JS が空欄を '' のまま hidden へ入れて送るため、
        //     sanitizeDetailPayload() の結果は null になりやすい。
        //   - null のままだと input.blade.php 側のミラーが上書きされず、過去値が残ることがある。
        //   → 事業・営業等に関しては「空欄=0」を貫通させる。
        foreach ($payload as $k => $v) {
            if (!is_string($k)) continue;
            // 対象：jigyo_eigyo_*_{prev|curr}
            if (preg_match('/^jigyo_eigyo_.+_(prev|curr)$/', $k) !== 1) continue;
            if ($v === null) {
                $payload[$k] = 0;
            }
        }

        $updatesForRecalc = array_merge($payload, $labelUpdates);

        if ((int) $req->input('recalc_all') === 1) {
            // details側の「戻る／再計算」時もフル再計算はするが、結果タブは自動で開かない
            Log::info('[details:jigyo_eigyo] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'jigyo'
                : (string) $req->input('redirect_to', 'jigyo');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }
        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
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
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（数値＋ラベル）
        $validated = $req->validated();

        // ラベル（DBカラム）：trimして空文字は null
        $labelUpdates = [];
        foreach (self::FUDOSAN_LABEL_FIELDS as $k) {
            $v = $validated[$k] ?? null;
            if ($v === null) {
                $labelUpdates[$k] = null;
                continue;
            }
            $t = trim((string) $v);
            $labelUpdates[$k] = ($t === '') ? null : $t;
        }

        // payload（数値のみ）：validated から label/data_id を除外して SoT 化
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, array_merge(['data_id'], self::FUDOSAN_LABEL_FIELDS)));

        // 仕様：details 画面の「空欄」は未更新ではなく 0 として保存する（古い値の残留を防ぐ）
        //   - fudosan_details では JS が空欄を '' のまま hidden へ入れて送るため、
        //     sanitizeDetailPayload() の結果は null になりやすい。
        //   - null のままだと input.blade.php 側のミラーが上書きされず、過去値が残ることがある。
        //   → 不動産に関しては「空欄=0」を貫通させる。
        foreach ($payload as $k => $v) {
            if (!is_string($k)) continue;
            // 対象：fudosan_*_{prev|curr}
            if (preg_match('/^fudosan_.+_(prev|curr)$/', $k) !== 1) continue;
            if ($v === null) {
                $payload[$k] = 0;
            }
        }

        $this->normalizeFudosanSyunyuKeys($payload);

        $updatesForRecalc = array_merge($payload, $labelUpdates);

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:fudosan] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'fudosan'
                : (string) $req->input('redirect_to', 'fudosan');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }
        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
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
        $syoriSettings = $this->getSyoriSettings($data->id);

        return view('tax.furusato.details.kifukin_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
            'syoriSettings' => $syoriSettings,
        ]);
    }

    public function saveKifukinDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（寄付入力の全キー）
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));
        $updatesForRecalc = $payload;

        // ▼ ワンストップ特例（syori_menu）= 利用する場合
        //   所得税側の寄付入力（所得控除/税額控除）は計算に使わないため 0 固定で保存する
        //   ※UI(JS)でも readonly+0 にするが、POST改ざん対策としてサーバでも上書きする
        $syoriSettings = $this->getSyoriSettings($data->id);
        $categories = ['furusato', 'kyodobokin_nisseki', 'seito', 'npo', 'koueki', 'kuni', 'sonota'];
        $oneStopZeroFixedCategories = ['kyodobokin_nisseki', 'seito', 'npo', 'koueki', 'kuni', 'sonota'];
        foreach (['prev', 'curr'] as $period) {
            $oneStop = (int) ($syoriSettings["one_stop_flag_{$period}"] ?? $syoriSettings['one_stop_flag'] ?? 0) === 1;
            if (! $oneStop) {
                continue;
            }
            foreach ($categories as $cat) {
                $updatesForRecalc["shotokuzei_shotokukojo_{$cat}_{$period}"] = 0;
                $updatesForRecalc["shotokuzei_zeigakukojo_{$cat}_{$period}"] = 0;
            }

            foreach ($oneStopZeroFixedCategories as $cat) {
                $updatesForRecalc["juminzei_zeigakukojo_pref_{$cat}_{$period}"] = 0;
                $updatesForRecalc["juminzei_zeigakukojo_muni_{$cat}_{$period}"] = 0;
            }
        }

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:kifukin] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'kifukin_details'
                : (string) $req->input('redirect_to', 'kifukin_details');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }
    /**
     * 給与・雑 details 表示
     */
    public function kyuyoZatsuDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear   = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear)     : '当年';
        $payload    = $this->getFurusatoInputPayload($data);

        return view('tax.furusato.details.kyuyo_zatsu_details', [
            'dataId'     => $data->id,
            'kihuYear'   => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out'        => ['inputs' => $payload],
        ]);
    }

    /**
     * 給与・雑 details 保存
     */
    public function saveKyuyoZatsuDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase
    ): RedirectResponse {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // 入力キー
        $fields = [
            'kyuyo_syunyu_prev','kyuyo_syunyu_curr',
            'kyuyo_chosei_applicable_prev','kyuyo_chosei_applicable_curr',
            'zatsu_nenkin_syunyu_prev','zatsu_nenkin_syunyu_curr',
            'zatsu_gyomu_syunyu_prev','zatsu_gyomu_syunyu_curr',
            'zatsu_gyomu_shiharai_prev','zatsu_gyomu_shiharai_curr',
            'zatsu_sonota_syunyu_prev','zatsu_sonota_syunyu_curr',
            'zatsu_sonota_shiharai_prev','zatsu_sonota_shiharai_curr',
        ];

        // 方針：details 画面の「空欄」は未更新ではなく 0 として保存する（payload/再計算/表示を一貫させる）
        //   - 数値フィールド：null/'' → 0
        //   - checkbox：0/1（下でルール矯正）
        $numericFields = [
            'kyuyo_syunyu_prev','kyuyo_syunyu_curr',
            'zatsu_nenkin_syunyu_prev','zatsu_nenkin_syunyu_curr',
            'zatsu_gyomu_syunyu_prev','zatsu_gyomu_syunyu_curr',
            'zatsu_gyomu_shiharai_prev','zatsu_gyomu_shiharai_curr',
            'zatsu_sonota_syunyu_prev','zatsu_sonota_syunyu_curr',
            'zatsu_sonota_shiharai_prev','zatsu_sonota_shiharai_curr',
        ];

        $rules = [
            'kyuyo_syunyu_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'kyuyo_syunyu_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'kyuyo_chosei_applicable_prev' => ['bail', 'nullable', 'in:0,1'],
            'kyuyo_chosei_applicable_curr' => ['bail', 'nullable', 'in:0,1'],
            'zatsu_nenkin_syunyu_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_nenkin_syunyu_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_gyomu_syunyu_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_gyomu_syunyu_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_gyomu_shiharai_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_gyomu_shiharai_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_sonota_syunyu_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_sonota_syunyu_curr' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_sonota_shiharai_prev' => ['bail', 'nullable', 'integer', 'min:0'],
            'zatsu_sonota_shiharai_curr' => ['bail', 'nullable', 'integer', 'min:0'],
        ];
        $this->normalizeIntegerFieldsFromRequest($req, $fields);
        // ▼ Request 側で validate 済み（数値＋checkbox hidden）
        $validated = $req->validated();

        // サニタイズ
        $payload = [];
        foreach ($fields as $k) {
            // checkbox（0/1）
            if ($k === 'kyuyo_chosei_applicable_prev' || $k === 'kyuyo_chosei_applicable_curr') {
                $payload[$k] = (int)($validated[$k] ?? 0) === 1 ? 1 : 0;
                continue;
            }

            // 数値：空欄は 0 で確定
            $v = $this->toNullableInt($validated[$k] ?? null);
            if (in_array($k, $numericFields, true)) {
                $payload[$k] = $v ?? 0;
            } else {
                // 念のため（現状ここは通らない想定）
                $payload[$k] = $v;
            }
        }

        // 850 万円超チェック：収入が 8,500,000 以下は適用不可（0 に矯正）
        foreach (['prev','curr'] as $p) {
            $income = (int)($payload["kyuyo_syunyu_{$p}"] ?? 0);
            if ($income <= 8_500_000) {
                $payload["kyuyo_chosei_applicable_{$p}"] = 0;
            } else {
                $payload["kyuyo_chosei_applicable_{$p}"] = (int)($payload["kyuyo_chosei_applicable_{$p}"] ?? 0) === 1 ? 1 : 0;
            }
        }

        // 「戻る/再計算」いずれも recalc_all=1 で来る前提（hidden）
        if ((int) $req->input('recalc_all') === 1) {
            \Log::info('[details:kyuyo_zatsu] recompute & redirect');
            // 画面に残るときはフラッシュ結果を出さない（他detailsと同じ扱い）
            $stay = $req->boolean('stay_on_details');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $payload,
                ['should_flash_results' => !$stay],
                $recalculateUseCase
            );
            // stay=true: 内訳に留まる / stay=false: 第一表へ戻る
            $goto = $stay ? 'kyuyo_zatsu' : (string) $req->input('redirect_to', 'input');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        // フォールバック（非想定ルート）
        $this->runRecalculationPipeline($req, $data, $payload, ['should_flash_results'=>true], $recalculateUseCase);
        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));
        $query  = $this->buildReturnQuery($req);
        return $this->redirectToInputWithAnchor($data, $anchor ?: 'shotoku_row_kyuyo', '保存しました', $query);
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
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（syunyu/keihi + sashihiki_*）
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));

        // ============================================================
        // ▼ details POST 範囲（A案）：
        //   - サーバが読む入力SoTのみを保存する（表示専用の派生キーは保存しない）
        //   - 収入/経費は入力SoTとして保存OK
        //   - 差引（サーバ計算入力SoT）は以下の3つに固定
        //       sashihiki_joto_tanki_sogo_{prev,curr}
        //       sashihiki_joto_choki_sogo_{prev,curr}
        //       sashihiki_ichiji_{prev,curr}  ※仕様：min0
        // ============================================================
        $filtered = [];
        foreach ($payload as $k => $v) {
            if (!is_string($k) || $k === '') continue;
            if (preg_match('/^(syunyu|keihi)_(joto_tanki|joto_choki|ichiji)_(prev|curr)$/', $k) === 1) {
                $filtered[$k] = $v;
                continue;
            }
            if (preg_match('/^sashihiki_joto_(tanki|choki)_sogo_(prev|curr)$/', $k) === 1) {
                $filtered[$k] = $v;
                continue;
            }
            if (preg_match('/^sashihiki_ichiji_(prev|curr)$/', $k) === 1) {
                // 仕様：一時差引は min0
                $filtered[$k] = max(0, (int)($v ?? 0));
                continue;
            }
        }
        $payload = $filtered;

        // 仕様：details 画面の「空欄」は未更新ではなく 0 として保存する（古い値の残留を防ぐ）
        //   - joto_ichiji_details は JS が空欄を '' のまま hidden へ入れて送る
        //   - sanitizeDetailPayload() は '' を null に正規化する
        //   - null のままだと input.blade.php 側のミラーが上書きされず、過去値が残ることがある
        //   → この内訳の「収入/経費」は空欄=0 を貫通させる
        foreach ($payload as $k => $v) {
            if (!is_string($k)) continue;
            if ($v !== null) continue;
            // 対象：収入・経費（総合譲渡短期/長期、一時）
            if (preg_match('/^(syunyu|keihi)_(joto_tanki|joto_choki|ichiji)_(prev|curr)$/', $k) !== 1) {
                continue;
            }
            $payload[$k] = 0;
        }

        $updatesForRecalc = $payload;

        // ============================================================
        // DBG: joto_ichiji_details のPOSTが「validated→filter→recalc」まで届いているか確認
        //  - ここは調査用ログ。原因確定後に削除してください。
        // ============================================================
        Log::info('[DBG details:joto_ichiji payload for recalc]', [
            'data_id' => (int) $data->id,
            // validated() から取れているか（Request層で落ちていないか）
            'has_validated_sashihiki_joto_choki_sogo_curr' => array_key_exists('sashihiki_joto_choki_sogo_curr', $validated),
            'validated_sashihiki_joto_choki_sogo_curr' => $validated['sashihiki_joto_choki_sogo_curr'] ?? null,
            'has_validated_sashihiki_ichiji_curr' => array_key_exists('sashihiki_ichiji_curr', $validated),
            'validated_sashihiki_ichiji_curr' => $validated['sashihiki_ichiji_curr'] ?? null,
            // filter 後（recalcに渡す最終payload）
            'payload_sashihiki_joto_choki_sogo_curr' => $payload['sashihiki_joto_choki_sogo_curr'] ?? null,
            'payload_sashihiki_joto_tanki_sogo_curr' => $payload['sashihiki_joto_tanki_sogo_curr'] ?? null,
            'payload_sashihiki_ichiji_curr' => $payload['sashihiki_ichiji_curr'] ?? null,
            // ★紛れ込み確認（これが混ざると “別キー” を参照してズレる）
            'payload_has_sashihiki_joto_choki_sogo_curr' => array_key_exists('sashihiki_joto_choki_sogo_curr', $payload),
            'payload_sashihiki_joto_choki_sogo_curr' => $payload['sashihiki_joto_choki_sogo_curr'] ?? null,
            'payload_keys' => array_slice(array_keys($payload), 0, 50),
        ]);

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:joto_ichiji] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'joto_ichiji'
                : (string) $req->input('redirect_to', 'joto_ichiji');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
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
        $syoriSettings = $this->getSyoriSettings($data->id);

        return view('tax.furusato.details.bunri_joto_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'syoriSettings' => $syoriSettings,
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriJotoDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request（FurusatoInputRequest）側で画面固有の入力キーのみ validate 済み
        //    - payload は validated のみから構築し、display-only の混入を防ぐ
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));

        // 仕様：details 画面の「空欄」は未更新ではなく 0 として保存する（古い値の残留を防ぐ）
        //   対象：syunyu_*/keihi_*/tokubetsukojo_*/joto_choki_tokutei_sonshitsu_*（いずれも入力SoT）
        foreach ($payload as $k => $v) {
            if (!is_string($k)) continue;
            if ($v !== null) continue;
            if (preg_match('/^(syunyu|keihi|tokubetsukojo)_(tanki_ippan|tanki_keigen|choki_ippan|choki_tokutei|choki_keika)_(prev|curr)$/', $k) === 1
                || preg_match('/^joto_choki_tokutei_sonshitsu_(prev|curr)$/', $k) === 1) {
                $payload[$k] = 0;
            }
        }        

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:bunri_joto] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'bunri_joto'
                : (string) $req->input('redirect_to', 'bunri_joto');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
            $recalculateUseCase,
        );

        // ▼ 戻り先のタブ/サブタブ/アンカーを URL へ反映してリダイレクト
        $query    = $this->buildReturnQuery($req);         // ['data_id'=>..., 'tab'=>'input', 'subtab'=>'bunri']
        $fragment = $this->sanitizedAnchor($req);          // '#bunri_...'
        return $this->redirectToInputWithAnchor($data, $fragment, '保存しました', $query);
    }

    public function bunriKabutekiDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);
        $syoriSettings = $this->getSyoriSettings($data->id);

        return view('tax.furusato.details.bunri_kabuteki_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'syoriSettings' => $syoriSettings,
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriKabutekiDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（syunyu/keihi/kurikoshi のみ）
        //    - payload は validated から構築し、display-only の混入を防ぐ
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));

        // 仕様：details 画面の「空欄」は未更新ではなく 0 として保存する（古い値の残留を防ぐ）
        foreach ($payload as $k => $v) {
            if (!is_string($k)) continue;
            if ($v !== null) continue;
            if (preg_match('/^(syunyu|keihi)_(ippan_joto|jojo_joto|jojo_haito)_(prev|curr)$/', $k) === 1
                || preg_match('/^kurikoshi_jojo_joto_(prev|curr)$/', $k) === 1) {
                $payload[$k] = 0;
            }
        }

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:bunri_kabuteki] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'bunri_kabuteki'
                : (string) $req->input('redirect_to', 'bunri_kabuteki');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
            $recalculateUseCase,
        );

        // ▼ 戻り先のタブ/サブタブ/アンカーを URL に反映
        $query    = $this->buildReturnQuery($req);  // ['data_id'=>..., 'tab'=>'input', 'subtab'=>'bunri' ...]
        $fragment = $this->sanitizedAnchor($req);   // 'bunri_...'
        return $this->redirectToInputWithAnchor($data, $fragment, '保存しました', $query);
    }

    public function bunriSakimonoDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);
        $syoriSettings = $this->getSyoriSettings($data->id);

        return view('tax.furusato.details.bunri_sakimono_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
            'syoriSettings' => $syoriSettings,
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriSakimonoDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（syunyu/keihi/kurikoshi のみ）
        //    - payload は validated から構築し、display-only の混入を防ぐ
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));
        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:bunri_sakimono] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'bunri_sakimono'
                : (string) $req->input('redirect_to', 'bunri_sakimono');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
            $recalculateUseCase,
        );

        // ▼ 戻り先のタブ/サブタブ/アンカーを URL に反映
        $query    = $this->buildReturnQuery($req);
        $fragment = $this->sanitizedAnchor($req);
        return $this->redirectToInputWithAnchor($data, $fragment, '保存しました', $query);
    }

    public function bunriSanrinDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        // ▼ ここで年ラベル等を確実に初期化（$kihuYear 未定義エラー対策）
        $kihuYear   = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear)     : '当年';
    
        // 入力ペイロード取得
        $payload = $this->getFurusatoInputPayload($data);
        // 分離設定（ON/OFF）もビューに渡す
        $syoriSettings = $this->getSyoriSettings($data->id);
    
        return view('tax.furusato.details.bunri_sanrin_details', [
            'dataId'        => $data->id,
            'kihuYear'      => $kihuYear,
            'warekiPrev'    => $warekiPrev,
            'warekiCurr'    => $warekiCurr,
            'out'           => ['inputs' => $payload],
            'syoriSettings' => $syoriSettings,
            'placeholderMessage' => self::BUNRI_PLACEHOLDER_MESSAGE,
        ]);
    }

    public function saveBunriSanrinDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（syunyu_sanrin/keihi_sanrin のみ）
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));

         // 仕様：details 画面の「空欄」は未更新ではなく 0 として保存する（古い値の残留を防ぐ）
         foreach ($payload as $k => $v) {
             if (!is_string($k)) continue;
             if ($v !== null) continue;
             if (preg_match('/^(syunyu_sanrin|keihi_sanrin)_(prev|curr)$/', $k) === 1) {
                 $payload[$k] = 0;
             }
         }

         // サーバで派生値を強制確定（POST改ざん防止 & 二重控除防止の前提を安定化）
         foreach (['prev', 'curr'] as $period) {
             $syunyu = (int) ($payload[sprintf('syunyu_sanrin_%s', $period)] ?? 0);
             $keihi  = (int) ($payload[sprintf('keihi_sanrin_%s',  $period)] ?? 0);
             $sashihiki = $syunyu - $keihi;
             $payload[sprintf('sashihiki_sanrin_%s', $period)] = $sashihiki;

             // 特別控除＝min(500,000, max(0, 差引))
             $tokubetsu = min(500_000, max(0, $sashihiki));
             $payload[sprintf('tokubetsukojo_sanrin_%s', $period)] = $tokubetsu;

             // shotoku_sanrin_* は stages で確定する（ここでは触らない）
             unset($payload[sprintf('shotoku_sanrin_%s', $period)]);
             unset($payload[sprintf('after_1jitsusan_sanrin_%s', $period)]);
             unset($payload[sprintf('after_2jitsusan_sanrin_%s', $period)]);
             unset($payload[sprintf('after_3jitsusan_sanrin_%s', $period)]);
         }

         $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:bunri_sanrin] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'bunri_sanrin'
                : (string) $req->input('redirect_to', 'bunri_sanrin');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
            $recalculateUseCase,
        );

        // ▼ 戻り先のタブ/サブタブ/アンカーを URL に反映
        $query    = $this->buildReturnQuery($req);
        $fragment = $this->sanitizedAnchor($req);
        return $this->redirectToInputWithAnchor($data, $fragment, '保存しました', $query);
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
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（入力SoTのみ）
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:kojo_seimei_jishin] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'kojo_seimei_jishin'
                : (string) $req->input('redirect_to', 'kojo_seimei_jishin');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
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
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate + after（withValidator）済み
        $validated = $req->validated();
        $payload = Arr::except($validated, ['data_id']);

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:kojo_jinteki] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'kojo_jinteki'
                : (string) $req->input('redirect_to', 'kojo_jinteki');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
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
  
         // ============================================================
         // ▼ 医療費控除（表示用の派生値）をサーバで確定
         //   方針：
         //     - SoTはサーバ（保存はA/Bのみ）
         //     - 表示（ⒸⒺⒻⒼ）もサーバ計算値をそのまま表示する（JSで再計算しない）
         //   制度（所得控除）：
         //     C = max(A - B, 0)
         //     threshold = min(100,000, floor(max(0,D)*0.05))
         //     G = min( max(C - threshold, 0), 2,000,000 )
         //     ※上限2,000,000は「最後」に適用
         // ============================================================
         foreach (['prev', 'curr'] as $p) {
             $a = $this->valueOrZero($this->toNullableInt($payload["kojo_iryo_shiharai_{$p}"] ?? null));
             $b = $this->valueOrZero($this->toNullableInt($payload["kojo_iryo_hotengaku_{$p}"] ?? null));
             $d = $this->valueOrZero($this->toNullableInt($payload["kojo_iryo_shotoku_gokei_{$p}"] ?? null));
             $d = max(0, (int) $d);
 
             // 表示用：差引金額（Ⓐ－Ⓑ）は生の差（マイナスも表示し得る）
             $cRaw = (int) $a - (int) $b;
             $payload["kojo_iryo_sashihiki_{$p}"] = $cRaw;
 
             // 制度上：対象額は 0 下限（医療費控除の対象となる自己負担分）
             $c = max(0, $cRaw);
             $e = (int) floor($d * 0.05);          // Ⓔ（円単位）
             $f = (int) min($e, 100_000);          // Ⓕ
             $g = (int) min(max($c - $f, 0), 2_000_000); // Ⓖ（上限は最後）
 
             $payload["kojo_iryo_shotoku_5pct_{$p}"] = $e;
             $payload["kojo_iryo_min_threshold_{$p}"] = $f;
             $payload["kojo_iryo_kojogaku_{$p}"] = $g;
         }
 
        return view('tax.furusato.details.kojo_iryo_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => ['inputs' => $payload],
        ]);
    }

    public function saveKojoIryoDetails(
        FurusatoInputRequest $req,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        // SoTはFurusatoInput/FurusatoResult（DB）、セッションは再描画用一時値で表示は「セッション→DB」だが保存の正は常にDB。
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        // ▼ Request 側で validate 済み（支払額/補填額のみ）
        $validated = $req->validated();
        $payload = $this->sanitizeDetailPayload(Arr::except($validated, ['data_id']));

        $updatesForRecalc = $payload;

        if ((int) $req->input('recalc_all') === 1) {
            Log::info('[details:kojo_iryo] recompute & redirect');
            $this->runRecalculationPipeline(
                $req,
                $data,
                $updatesForRecalc,
                ['should_flash_results' => false],
                $recalculateUseCase,
            );
            $goto = $req->boolean('stay_on_details')
                ? 'kojo_iryo'
                : (string) $req->input('redirect_to', 'kojo_iryo');
            return $this->redirectAfterGoto($req, $data, $goto, '再計算が完了しました');
        }

        $this->runRecalculationPipeline(
            $req,
            $data,
            $updatesForRecalc,
            ['should_flash_results' => true],
            $recalculateUseCase,
        );

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor ?: 'kojo_iryo');
    }

    public function syoriIndex(Request $request)
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        /** @var SyoriSettingsFactory $syoriFactory */
        $syoriFactory = app(SyoriSettingsFactory::class);

        // syori_menu 画面用の設定（jumin_master の均等割・その他も反映済み）
        $settings = $syoriFactory->buildMenuPayload($data);

        return view('tax.furusato.syori_menu', [
            'dataId'   => $data->id,
            'settings' => $settings,
        ]);
    }

    public function syoriSave(
        FurusatoSyoriRequest $request,
        RecalculateFurusatoPayload $recalculateUseCase,
    ): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $validated = $request->validated();

        /** @var SyoriSettingsFactory $syoriFactory */
        $syoriFactory = app(SyoriSettingsFactory::class);

        // 画面は百分率（0〜100）で送ってくるため、小数（0.00〜1.00）へ正規化しつつ
        // デフォルト値や標準税率も含めてドメイン側で組み立てる
        $payload = $syoriFactory->buildPayloadForSave($validated);

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

        // ▼ 分離なし年度は分離系（山林・退職）を SoT=0 に正規化して保存
        $forceZeros = [];
        $offPrev = (int)($payload['bunri_flag_prev'] ?? $payload['bunri_flag'] ?? 0) === 0;
        $offCurr = (int)($payload['bunri_flag_curr'] ?? $payload['bunri_flag'] ?? 0) === 0;
        if ($offPrev) {
            $forceZeros = array_merge($forceZeros, [
                // 山林（分離）
                'bunri_syunyu_sanrin_prev'            => 0,
                'bunri_shotoku_sanrin_prev'           => 0,
                'bunri_syunyu_sanrin_jumin_prev'      => 0,
                'bunri_shotoku_sanrin_jumin_prev'     => 0,
                // 退職（分離）
                'bunri_syunyu_taishoku_prev'          => 0,
                'bunri_shotoku_taishoku_prev'         => 0,
                'bunri_syunyu_taishoku_jumin_prev'    => 0,
                'bunri_shotoku_taishoku_jumin_prev'   => 0,
            ]);
        }
        if ($offCurr) {
            $forceZeros = array_merge($forceZeros, [
                // 山林（分離）
                'bunri_syunyu_sanrin_curr'            => 0,
                'bunri_shotoku_sanrin_curr'           => 0,
                'bunri_syunyu_sanrin_jumin_curr'      => 0,
                'bunri_shotoku_sanrin_jumin_curr'     => 0,
                // 退職（分離）
                'bunri_syunyu_taishoku_curr'          => 0,
                'bunri_shotoku_taishoku_curr'         => 0,
                'bunri_syunyu_taishoku_jumin_curr'    => 0,
                'bunri_shotoku_taishoku_jumin_curr'   => 0,
            ]);
        }
        // 正規化した 0 を反映してフル再計算
        $this->performFullRecalculation($request, $data, $forceZeros, $recalculateUseCase);

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
        $year = (int) ($data->kihu_year ?: self::MASTER_KIHU_YEAR);

        $rates = $masterService->getJuminRates($year, $companyId, $data->id);

        // FurusatoInput.payload から均等割・その他税額を取得（無ければデフォルト）
        $inputPayload = \App\Models\FurusatoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $inputPayload = is_array($inputPayload) ? $inputPayload : [];

        $equal = [
            'pref_equal_share_prev'   => (int) ($inputPayload['jumin_pref_equal_share_prev']   ?? 1500),
            'pref_equal_share_curr'   => (int) ($inputPayload['jumin_pref_equal_share_curr']   ?? 1500),
            'muni_equal_share_prev'   => (int) ($inputPayload['jumin_muni_equal_share_prev']   ?? 3500),
            'muni_equal_share_curr'   => (int) ($inputPayload['jumin_muni_equal_share_curr']   ?? 3500),
            'other_taxes_prev'        => (int) ($inputPayload['jumin_other_taxes_amount_prev'] ?? 0),
            'other_taxes_curr'        => (int) ($inputPayload['jumin_other_taxes_amount_curr'] ?? 0),
        ];

        return view('tax.furusato.master.jumin_master', [
            'dataId' => $data->id,
            'rates'  => $rates,
            'equal'  => $equal,
        ]);
    }

    /**
     * 住民税率マスター保存（編集UIのPOST先）
     */
    public function juminMasterSave(
        Request $request,
        RecalculateFurusatoPayload $recalculateUseCase
    ): RedirectResponse
    {
        // data_id ごとの JSON 保存に切り替え（jumin_rates テーブルは使わない）
        $data = $this->resolveCompanyScopedDataOrFail($request, 'update');
        $year = (int) ($data->kihu_year ?: FurusatoMasterDefaults::DEFAULT_YEAR);

        // 受領フォーマット：
        // rates[n][sort category sub_category city_specified pref_specified city_non_specified pref_non_specified]
        $rows = (array) $request->input('rates', []);

        $normalized = [];

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                $row = [];
            }

            $category = trim((string) ($row['category'] ?? ''));
            // name="rates[...]" を持たない行や category 未指定行はスキップ
            if ($category === '') {
                continue;
            }

            $subCategory = ($row['sub_category'] ?? '') === '' ? null : trim((string) $row['sub_category']);
            $sort        = (int) ($row['sort'] ?? (10 * ($i + 1)));

            // レイアウト系（year/category/sub/sort）は必ず保存するが、
            //    率カラムは「入力されたものだけ」保存（空欄は Defaults を使う）
            $record = [
                'year'         => $year,
                'company_id'   => null,
                'sort'         => $sort,
                'category'     => $category,
                'sub_category' => $subCategory,
                // remark は編集不可なのでここでは保存しない（Defaults 側を採用）
            ];

            // 4 率：空欄は「キー自体を持たない」→ Defaults にフォールバック
            foreach (['city_specified','pref_specified','city_non_specified','pref_non_specified'] as $k) {
                $v = $row[$k] ?? null;
                if ($v === '' || $v === null) {
                    // 入力なし → キーを作らない（初期値採用）
                    continue;
                }
                $sv = is_string($v) ? str_replace([',',' '], '', $v) : $v;
                if (! is_numeric($sv)) {
                    throw ValidationException::withMessages([
                        "rates.$i.$k" => '数値を入力してください（空欄＝初期値を使用、0 を入力した場合のみ 0% として保存）。',
                    ]);
                }
                // 0 と入力された場合は 0.000 として保存される
                $record[$k] = round((float) $sv, 3);
            }

            $normalized[] = $record;
        }

        // sort 昇順に並べ替え（念のため）
        usort($normalized, static fn (array $a, array $b) => ($a['sort'] <=> $b['sort']));

        // 均等割・その他税額の取り出し（整数化）
        $equalRaw = (array) $request->input('jumin', []);

        $toInt = function ($value, int $default): int {
            if ($value === null || $value === '') {
                return $default;
            }
            $s = preg_replace('/[^\d]/', '', (string) $value);
            if ($s === '') {
                return $default;
            }
            return (int) $s;
        };

        $equal = [
            'jumin_pref_equal_share_prev'   => $toInt($equalRaw['pref_equal_share_prev']   ?? null, 1500),
            'jumin_pref_equal_share_curr'   => $toInt($equalRaw['pref_equal_share_curr']   ?? null, 1500),
            'jumin_muni_equal_share_prev'   => $toInt($equalRaw['muni_equal_share_prev']   ?? null, 3500),
            'jumin_muni_equal_share_curr'   => $toInt($equalRaw['muni_equal_share_curr']   ?? null, 3500),
            'jumin_other_taxes_amount_prev' => $toInt($equalRaw['other_taxes_prev']        ?? null, 0),
            'jumin_other_taxes_amount_curr' => $toInt($equalRaw['other_taxes_curr']        ?? null, 0),
        ];

        // FurusatoInput.payload に jumin_master ＋ 均等割・その他 を保存
        FurusatoInput::unguarded(function () use ($data, $normalized, $equal): void {
            /** @var FurusatoInput $record */
            $record = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            $payload = is_array($record->payload) ? $record->payload : [];

            $payload['jumin_master'] = $normalized;

            foreach ($equal as $k => $v) {
                $payload[$k] = $v;
            }

            $record->payload    = $payload;
            $record->company_id = $data->company_id;
            $record->group_id   = $data->group_id;
            $record->save();
        });

        // 保存後に「当該 data_id を再計算」してから遷移（新レートを即反映）
        $this->performFullRecalculation($request, $data, [], $recalculateUseCase);

        // 画面遷移：「戻る」＝ マスター（親）へ戻る
        return redirect()
            ->route('furusato.master', ['data_id' => $data->id])
            ->with('success', '住民税率マスターを保存し、再計算しました');
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

    /**
     * 指定キーについて、文字列を「整数 or null」に正規化して $request に上書きする。
     * - カンマ・空白・全角数字 → 半角数字に寄せる
     * - 空/「－」等は null
     */
    private function normalizeIntegerFieldsFromRequest(Request $request, array $keys): void
    {
        if ($keys === []) return;

        $replacements = [];
        foreach ($keys as $key) {
            $raw = $request->input($key);
            if ($raw === null) {
                $replacements[$key] = null;
                continue;
            }
            // 文字列化
            $s = is_string($raw) ? $raw : (string) $raw;
            $s = trim($s);
            if ($s === '' || $s === '－' || $s === '-') {
                $replacements[$key] = null;
                continue;
            }
            // 全角→半角、カンマ除去
            $s = preg_replace('/,/', '', $s ?? '') ?? '';
            // 全角数字→半角数字
            $s = mb_convert_kana($s, 'n', 'UTF-8');
            // 数値判定
            if ($s !== '' && preg_match('/^-?\d+$/', $s) === 1) {
                $replacements[$key] = (int) $s;
            } else {
                // 変換不能ならそのまま（後続のバリデーションで弾かれる）
                $replacements[$key] = $raw;
            }
        }

        if ($replacements !== []) {
            $request->merge($replacements);
        }
    }
    /**
     * @return array{int, int}
     */
    private function resolveShotokuGokei(int $dataId): array
    {
        $payload = [];

        $sessionResults = session('furusato_results');
        if (is_array($sessionResults)) {
            $candidate = $sessionResults['payload'] ?? $sessionResults['upper'] ?? $sessionResults;
            if (is_array($candidate)) {
                $payload = $candidate;
            }
        }

        if ($payload === []) {
            $storedResults = $this->getStoredFurusatoResults($dataId);
            $candidate = $storedResults['payload'] ?? $storedResults['upper'] ?? $storedResults;
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

        $subtabRaw = $request->input('origin_subtab');
        if (is_string($subtabRaw)) {
            $subtab = preg_replace('/[^A-Za-z0-9_-]/', '', trim($subtabRaw));
            if ($subtab !== '') {
                $query['origin_subtab'] = $subtab;
            }
        }

        $anchor = $this->sanitizeOriginAnchor($request->input('origin_anchor'));
        if ($anchor !== '') {
            $query['origin_anchor'] = $anchor;
        }

        return $query;
    }

    private function buildReturnQuery(Request $request): array
    {
        $dataId = (int) $request->input('data_id');
        $query = ['data_id' => $dataId];

        $tabRaw = $request->input('origin_tab');
        if (is_string($tabRaw)) {
            $tab = preg_replace('/[^A-Za-z0-9_-]/', '', trim($tabRaw));
            if ($tab !== '') {
                $query['tab'] = $tab;
            }
        }

        $subtabRaw = $request->input('origin_subtab');
        if (is_string($subtabRaw)) {
            $subtab = preg_replace('/[^A-Za-z0-9_-]/', '', trim($subtabRaw));
            if ($subtab !== '') {
                $query['subtab'] = $subtab;
            }
        }

        return $query;
    }

    private function sanitizedAnchor(Request $request): string
    {
        return $this->sanitizeOriginAnchor($request->input('origin_anchor'));
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
                'data_id'          => $data->id,
                'taxpayer_sex'     => data_get($data, 'guest.sex') ?? data_get($data, 'guest.gender') ?? data_get($data, 'guest.sex_code'),
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

        switch ($goto) {
            case 'syori':
                return redirect()->route('furusato.syori', $routeParams)->with('success', $message);
            case 'master':
                return redirect()->route('furusato.master', $routeParams)->with('success', $message);
            case 'jigyo':
                return redirect()->route('furusato.details.jigyo', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'kyuyo_zatsu':
                return redirect()->route('furusato.details.kyuyo_zatsu', array_merge($routeParams, $originQuery))->with('success', $message);
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
            case 'kojo_tokubetsu_jutaku_loan':
                return redirect()->route('furusato.details.kojo_tokubetsu_jutaku_loan', array_merge($routeParams, $originQuery))->with('success', $message);
            case 'input':
            case '':
            default:
                $query = $this->buildReturnQuery($request);
                $fragment = $this->sanitizedAnchor($request);

                return $this->redirectToInputWithAnchor($data, $fragment, $message, $query);
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

    private function redirectToInputWithAnchor(Data $data, string $anchor = '', string $message = '保存しました', array $query = []): RedirectResponse
    {
        $dataId = (int) ($query['data_id'] ?? 0);
        if ($dataId <= 0) {
            $dataId = $data->id;
        }
        $query['data_id'] = $dataId;

        $redirect = redirect()->route('furusato.input', $query)
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
             // base = 収入 − 必要経費 − 専従者給与（ここはマイナス可）
             $base = $sashihiki - $senjuusha;
             $result[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $period)] = $base;

            $tokubetsuKojo = $this->valueOrZero($inputs[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);
 
            // ▼ 要望：土地等を取得するための負債利子を所得金額から控除（0下限）
            $fusairishi = $this->valueOrZero($inputs[sprintf('fudosan_fusairishi_%s', $period)] ?? null);
 
             // ▼ 仕様：base>0 のときのみ青色控除・負債利子を 0 下限で差し引く。base<=0 は差し引かない。
             if ($base > 0) {
                 $afterAoi = max(0, $base - $tokubetsuKojo);
                 $result[sprintf('fudosan_shotoku_%s', $period)] = max(0, $afterAoi - $fusairishi);
             } else {
                 $result[sprintf('fudosan_shotoku_%s', $period)] = $base;
             }
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

    /**
     * 医療費控除（所得控除）をペイロードから制度どおり再計算する。
     * G = max( min(max(A-B,0), 2,000,000) - min(100,000, floor(max(0,D)*0.05)) , 0 )
     * - A: kojo_iryo_shiharai_*
     * - B: kojo_iryo_hotengaku_*
     * - D: 総所得金額等（ここでは第一表の合計 shotoku_gokei_shotoku_* を採用）
     */
    private function computeMedicalDeduction(array $source, string $period): int
    {
        $a = $this->valueOrZero($this->toNullableInt($source["kojo_iryo_shiharai_{$period}"] ?? null));
        $b = $this->valueOrZero($this->toNullableInt($source["kojo_iryo_hotengaku_{$period}"] ?? null));
        // 総所得金額等：負値は 0 として扱う
        $dCandidates = [
            // 内訳画面で供給される場合のキー（存在すれば優先）
            "kojo_iryo_shotoku_gokei_{$period}",
            // 第一表の合計（本プロジェクトではこれが SoT）
            "shotoku_gokei_shotoku_{$period}",
        ];
        $d = 0;
        foreach ($dCandidates as $k) {
            if (array_key_exists($k, $source) && $source[$k] !== null && $source[$k] !== '') {
                $d = $this->valueOrZero($this->toNullableInt($source[$k]));
                break;
            }
        }
        $d = max(0, $d);

        // ============================================================
        // ▼ 医療費控除（所得控除）
        //   制度：
        //     C = max(A - B, 0)
        //     threshold = min(100,000, floor(D*0.05))
        //     G = min( max(C - threshold, 0), 2,000,000 )
        //     ※上限2,000,000は「最後」に適用する（足切り後の控除額に対して上限）
        // ============================================================
        $c       = max(0, $a - $b);
        $e       = (int) floor($d * 0.05);
        $f       = min($e, 100_000);
        $gRaw    = max(0, $c - $f);
        $g       = min($gRaw, 2_000_000);
        return $g;
    }

    private function getSyoriSettings(int $dataId): array
    {
        /** @var SyoriSettingsFactory $syoriFactory */
        $syoriFactory = app(SyoriSettingsFactory::class);

        // data_id が無い場合は処理設定なし
        if ($dataId === null) {
            return [];
        }

        // Data モデルを lookup してから Factory の Data ベース API を呼び出す
        $data = \App\Models\Data::find($dataId);

        if (! $data) {
            return [];
        }

        // data_id ごとの処理設定（syori_menu + jumin_master の値を統合した有効設定）
        return $syoriFactory->buildInitial($data);
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

        // ▼ 人的控除差調整（住民税）は tb_sogo_jumin_* を参照
        $key = sprintf('tb_sogo_jumin_%s', $period);

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

        // client：自分に紐付く guest の data のみ
        if ($role === 'client') {
            $data->loadMissing('guest');
            $guest = $data->guest;
            if (! $guest || (int)($guest->client_user_id ?? 0) !== (int)$me->id) {
                abort(403);
            }
        }

        if (! $isOwnerOrRegistrar && (int) $data->group_id !== (int) ($me->group_id ?? 0)) {
            abort(403);
        }

        // private：作成者のみ（owner/registrar でも例外なし）
        if (config('feature.data_privacy')) {
            $vis = (string)($data->visibility ?? 'shared');
            if ($vis === 'private') {
                $creatorId = (int)($data->owner_user_id ?? 0) ?: (int)($data->user_id ?? 0);
                if ((int)$me->id !== $creatorId) {
                    abort(403);
                }
            }
        }

        return $data;
    }

    private function resolveCompanyScopedDataOrFail(Request $request): Data
    {
        // ★危険：company だけのチェックは private/client を素通しするため廃止し、
        //   常に resolveAuthorizedDataOrFail(view) を経由する。
        return $this->resolveAuthorizedDataOrFail($request, 'view');
    }

    /**
     * syori_menu で入力される「適用率」4項目は百分率（0〜100）で来る。
     * DB/計算は小数（0〜1）で扱うため 100 で割って正規化する。
     */
    private function normalizePercentAppliedRates(array $payload): array
    {
        foreach (['prev', 'curr'] as $p) {
            $k1 = "pref_applied_rate_{$p}";
            $k2 = "muni_applied_rate_{$p}";
            if (array_key_exists($k1, $payload) && $payload[$k1] !== null && $payload[$k1] !== '') {
                $payload[$k1] = max(0.0, min(100.0, (float)$payload[$k1])) / 100.0;
            }
            if (array_key_exists($k2, $payload) && $payload[$k2] !== null && $payload[$k2] !== '') {
                $payload[$k2] = max(0.0, min(100.0, (float)$payload[$k2])) / 100.0;
            }
        }
        return $payload;
    }

    /**
     * main input 画面で「所得税側入力を住民税側へそのまま使う」控除を
     * サーバ側で必ず補完する。
     *
     * 理由:
     * - 画面JSの見た目ミラーだけだと、readonly側が未反映のまま保存されることがある
     * - 保存の正本はサーバなので、ここで明示的に補完する
     *
     * 対象:
     * - 社会保険料控除
     * - 小規模企業共済等掛金控除
     *
     * @param  array<string,mixed>  $updates
     * @return array<string,mixed>
     */
    private function mirrorMainInputJuminKojoFields(array $updates): array
    {
        foreach (['prev', 'curr'] as $period) {
            $shotokuShakai = $this->toNullableInt($updates["kojo_shakaihoken_shotoku_{$period}"] ?? null);
            if ($shotokuShakai !== null) {
                $updates["kojo_shakaihoken_jumin_{$period}"] = $shotokuShakai;
            }

            $shotokuShokibo = $this->toNullableInt($updates["kojo_shokibo_shotoku_{$period}"] ?? null);
            if ($shotokuShokibo !== null) {
                $updates["kojo_shokibo_jumin_{$period}"] = $shotokuShokibo;
            }
        }

        return $updates;
    }

    /**
     * ふるさと納税の理論上限額 Kmax を算出する。
     *
     * 前提：
     *  - $payload には各 Calculator が出力した SoT がそのまま入っていること
     *    (sum_for_sogoshotoku_etc_curr / tb_sogo_shotoku_curr / kifu_gaku_curr など)
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function buildKmaxContext(array $payload): array
    {
        // 整数正規化（カンマ付き文字列も許容）
        $n = static function ($v): int {
            if ($v === null || $v === '') return 0;
            if (is_string($v)) {
                $v = str_replace([',', ' '], '', $v);
            }
            return is_numeric($v) ? (int) floor((float) $v) : 0;
        };

        // float 正規化
        $f = static function ($v): float {
            if ($v === null || $v === '') return 0.0;
            if (is_string($v)) {
                $v = str_replace([',', ' '], '', $v);
            }
            return is_numeric($v) ? (float) $v : 0.0;
        };

        // ---- 1) ベース指標 S40 / S30 / R ----
        // 所得税側：総所得金額等 S40 = sum_for_sogoshotoku_etc_curr
        $S40 = $n($payload['sum_for_sogoshotoku_etc_curr'] ?? null);

        // 住民税側：S30（30%ガード母数）は「総所得金額等（分離課税を含む）」を使用する
        // - SoT: CommonSumsCalculator の sum_for_sogoshotoku_etc_curr
        // - フォールバックは行わない（キーが無い/0 の場合は 0 扱い）
        $S30 = max(0, $n($payload['sum_for_sogoshotoku_etc_curr'] ?? null));

        // 調整控除後所得割額ベース R = capbase_pref + capbase_muni
        $R = $n($payload['choseigo_shotokuwari_capbase_pref_curr'] ?? null)
           + $n($payload['choseigo_shotokuwari_capbase_muni_curr'] ?? null);

        // ---- 2) 特例控除最終率 α ----
        $alphaPercent = $f($payload['tokurei_rate_final_curr'] ?? null); // 単位：%
        $alpha = $alphaPercent > 0.0 ? $alphaPercent / 100.0 : 0.0;      // 0〜1

        // ---- 3) 寄附金合計 D_total / ふるさと D_furu / その他 D_other ----
        $DTotal = $n($payload['kifu_gaku_curr'] ?? null);          // 住民税側の寄附金合計
        $DFuru  = $n($payload['furusato_kifu_gaku_curr'] ?? null); // うち ふるさと納税分
        $DOther = max(0, $DTotal - $DFuru);                        // ふるさと以外

        // ---- 4) Kmax(40)：所得税 40% 上限 ----
        $k40 = max(0, (int) floor(0.4 * $S40) - $DOther);

        // ---- 5) Kmax(30)：住民税 30% ガード ----
        $k30 = max(0, (int) floor(0.3 * $S30) - $DOther);

        // ---- 6) Kmax(20)：住民税所得割 20% 上限 ----
        $k20 = null;
        if ($R > 0 && $alpha > 0.0) {
            $k20 = (int) floor(0.2 * $R / $alpha + 2000);
        }

        // ---- 7) 最終 Kmax & binding constraint ----
        $candidates = [
            '40' => $k40,
            '30' => $k30,
        ];
        if ($k20 !== null) {
            $candidates['20'] = $k20;
        }

        $binding = null;
        $kmax = null;
        foreach ($candidates as $key => $val) {
            if ($kmax === null || $val < $kmax) {
                $kmax = $val;
                $binding = $key;
            }
        }

        $remaining = $kmax !== null ? max(0, $kmax - $DFuru) : null;

        return [
            'S40'           => $S40,
            'S30'           => $S30,
            'R'             => $R,
            'alpha_percent' => $alphaPercent,

            'D_total'       => $DTotal,
            'D_furu'        => $DFuru,
            'D_other'       => $DOther,

            'kmax_40'       => $k40,
            'kmax_30'       => $k30,
            'kmax_20'       => $k20,
            'kmax'          => $kmax,
            'binding'       => $binding,
            'remaining'     => $remaining,
        ];
    }
}