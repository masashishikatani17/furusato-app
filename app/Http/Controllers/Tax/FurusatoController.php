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
use App\Services\Tax\FurusatoMasterService;
use App\Services\Tax\Result\FurusatoResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

final class FurusatoController extends Controller
{
    private const MASTER_KIHU_YEAR = 2025;

    private const FUDOSAN_LABEL_FIELDS = [
        'fudosan_keihi_label_01',
        'fudosan_keihi_label_02',
        'fudosan_keihi_label_03',
        'fudosan_keihi_label_04',
        'fudosan_keihi_label_05',
        'fudosan_keihi_label_06',
        'fudosan_keihi_label_07',
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
            }
        }

        $companyId = $request->user()?->company_id;
        if ($companyId === null && $data) {
            $companyId = $data->company_id;
        }
        $companyId = $companyId !== null ? (int) $companyId : null;

        $shotokuRates = app(FurusatoMasterService::class)
            ->getShotokuRates(self::MASTER_KIHU_YEAR, $companyId);

        return [
            'dataId' => $dataId,
            'bunriFlag' => $bunriFlag,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'savedInputs' => $savedInputs,
            'results' => [],
            'showResult' => false,
            'shotokuRates' => $shotokuRates,
        ];
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

        $bases = [
            'kojo_kafu',
            'kojo_hitorioya',
            'kojo_kinrogakusei',
            'kojo_shogaisha',
            'kojo_haigusha',
            'kojo_haigusha_tokubetsu',
            'kojo_fuyo',
            'kojo_tokutei_shinzoku',
        ];
        $rules = [];
        foreach ($bases as $base) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

        foreach (['prev', 'curr'] as $period) {
            $total = 0;
            foreach ($bases as $base) {
                $amount = $this->valueOrZero($payload[sprintf('%s_%s', $base, $period)] ?? null);
                $payload[sprintf('%s_shotoku_%s', $base, $period)] = $amount;
                $payload[sprintf('%s_jumin_%s', $base, $period)] = $amount;
                $total += $amount;
            }
            $payload[sprintf('kojo_jinteki_gokei_%s', $period)] = $total;
        }

        $this->updateFurusatoInputPayload($data, $payload);

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function kojoIryoDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = $this->getFurusatoInputPayload($data);

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

        $fields = ['kojo_iryo_shishutsu', 'kojo_iryo_hojokin'];
        $rules = [];
        foreach ($fields as $field) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('%s_%s', $field, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
            }
        }

        Validator::make($req->only(array_keys($rules)), $rules)->validate();

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

        foreach (['prev', 'curr'] as $period) {
            $shishutsu = $this->valueOrZero($payload[sprintf('kojo_iryo_shishutsu_%s', $period)] ?? null);
            $hojokin = $this->valueOrZero($payload[sprintf('kojo_iryo_hojokin_%s', $period)] ?? null);
            $kojogaku = max(0, $shishutsu - $hojokin);
            $payload[sprintf('kojo_iryo_kojogaku_%s', $period)] = $kojogaku;
            $payload[sprintf('kojo_iryo_shotoku_%s', $period)] = $kojogaku;
            $payload[sprintf('kojo_iryo_jumin_%s', $period)] = $kojogaku;
        }

        $this->updateFurusatoInputPayload($data, $payload);

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
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
            $payload = array_replace($current, $updates);
            $payload = $this->applyAutoCalculatedFields($data, $payload);
            $record->payload = $payload;
            $record->updated_by = $userId ?: null;

            $record->save();
        });
    }

    private function applyAutoCalculatedFields(Data $data, array $payload): array
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

        foreach ($taxTypes as $tax) {
            foreach ($periods as $period) {
                $shokei = 0;
                foreach ($kojoShokeiBases as $base) {
                    $key = sprintf('%s_%s_%s', $base, $tax, $period);
                    $shokei += $this->valueOrZero($this->toNullableInt($payload[$key] ?? null));
                }
                $payload[sprintf('kojo_shokei_%s_%s', $tax, $period)] = $shokei;

                $gokei = $shokei;
                foreach ($kojoGokeiExtras as $base) {
                    $key = sprintf('%s_%s_%s', $base, $tax, $period);
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

        return $payload;
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
            'detail_mode' => 1,
            'bunri_flag' => 0,
            'one_stop_flag' => 1,
            'shitei_toshi_flag' => 0,
            'pref_standard_rate' => 0.04,
            'muni_standard_rate' => 0.06,
            'pref_applied_rate' => 0.04,
            'muni_applied_rate' => 0.06,
            'pref_equal_share' => 1500,
            'muni_equal_share' => 3500,
            'other_taxes_amount' => 0,
        ];
    }

    private function applyStandardRates(array $payload): array
    {
        $shitei = (int) ($payload['shitei_toshi_flag'] ?? 0);

        if ($shitei === 1) {
            $payload['pref_standard_rate'] = 0.02;
            $payload['muni_standard_rate'] = 0.08;
        } else {
            $payload['pref_standard_rate'] = 0.04;
            $payload['muni_standard_rate'] = 0.06;
        }

        if (! array_key_exists('pref_applied_rate', $payload) || $payload['pref_applied_rate'] === null) {
            $payload['pref_applied_rate'] = $payload['pref_standard_rate'];
        }

        if (! array_key_exists('muni_applied_rate', $payload) || $payload['muni_applied_rate'] === null) {
            $payload['muni_applied_rate'] = $payload['muni_standard_rate'];
        }

        $payload['detail_mode'] = (int) ($payload['detail_mode'] ?? 1);
        $payload['bunri_flag'] = (int) ($payload['bunri_flag'] ?? 0);
        $payload['one_stop_flag'] = (int) ($payload['one_stop_flag'] ?? 1);
        $payload['shitei_toshi_flag'] = $shitei;
        $payload['pref_applied_rate'] = (float) $payload['pref_applied_rate'];
        $payload['muni_applied_rate'] = (float) $payload['muni_applied_rate'];
        $payload['pref_standard_rate'] = (float) $payload['pref_standard_rate'];
        $payload['muni_standard_rate'] = (float) $payload['muni_standard_rate'];
        $payload['pref_equal_share'] = (int) ($payload['pref_equal_share'] ?? 1500);
        $payload['muni_equal_share'] = (int) ($payload['muni_equal_share'] ?? 3500);
        $payload['other_taxes_amount'] = (int) ($payload['other_taxes_amount'] ?? 0);

        return $payload;
    }
}