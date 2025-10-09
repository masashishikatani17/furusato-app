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

final class FurusatoController extends Controller
{
    private const MASTER_KIHU_YEAR = 2025;

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
        $payload = FurusatoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $out = ['inputs' => is_array($payload) ? $payload : []];

        return view('tax.furusato.details.jigyo_eigyo_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
        ]);
    }

    public function saveJigyoEigyoDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

        $calculations = $this->calculateJigyoEigyo($payload);
        $payload = array_replace($payload, $calculations);

        $payload['syunyu_jigyo_eigyo_shotoku_prev'] = $this->valueOrZero($payload['jigyo_eigyo_uriage_prev'] ?? null);
        $payload['syunyu_jigyo_eigyo_shotoku_curr'] = $this->valueOrZero($payload['jigyo_eigyo_uriage_curr'] ?? null);
        $payload['shotoku_jigyo_eigyo_shotoku_prev'] = (int) ($payload['jigyo_eigyo_shotoku_prev'] ?? 0);
        $payload['shotoku_jigyo_eigyo_shotoku_curr'] = (int) ($payload['jigyo_eigyo_shotoku_curr'] ?? 0);

        $this->updateFurusatoInputPayload($data, $payload);

        $anchor = $this->sanitizeOriginAnchor($req->input('origin_anchor'));

        return $this->redirectToInputWithAnchor($data, $anchor);
    }

    public function fudosanDetails(Request $req)
    {
        $data = $this->resolveAuthorizedDataOrFail($req);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = FurusatoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $out = ['inputs' => is_array($payload) ? $payload : []];

        return view('tax.furusato.details.fudosan_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
        ]);
    }

    public function saveFudosanDetails(Request $req): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($req, 'update');

        $payload = $this->sanitizeDetailPayload($req->except(['_token', 'data_id', 'origin_tab', 'origin_anchor']));

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

        $this->updateFurusatoInputPayload($data, $payload);

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
        return optional(FurusatoInput::query()
            ->where('data_id', $data->id)
            ->first())->payload ?? [];
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

    private function updateFurusatoInputPayload(Data $data, array $updates): void
    {
        $userId = (int) auth()->id();

        FurusatoInput::unguarded(function () use ($data, $updates, $userId): void {
            $record = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->fill([
                    'data_id'    => $data->id,
                    'company_id' => $data->company_id,
                    'group_id'   => $data->group_id,
                    'created_by' => $userId ?: null,
                ]);
            }

            $current = is_array($record->payload) ? $record->payload : [];
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