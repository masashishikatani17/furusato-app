<?php

namespace App\Http\Controllers\Tax;

use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\Support\FurusatoMasterSheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\FurusatoInputRequest;
use App\Http\Requests\Tax\FurusatoSyoriRequest;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoSyoriSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FurusatoController extends Controller
{
    public function index(Request $req)
    {
        $dataId = $req->integer('data_id') ?: null;
        if ($dataId) {
            session(['selected_data_id' => $dataId]);
        }
        
        return view('tax.furusato.input', ['dataId' => $dataId]);
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

        return view('tax.furusato.input', [
            'out' => $out,
            'dataId' => $dataId,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $payload = $request->except(['_token', 'data_id', 'redirect_to']);
        $userId = (int) auth()->id();

        // SQLite で IFNULL(created_by, ...) を VALUES 句で参照すると
        // 「no such column: created_by」になるため、作成/更新を分けて処理する
        FurusatoInput::unguarded(function () use ($data, $payload, $userId): void {
            $rec = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            if (! $rec->exists) {
                // 初回作成時のみ created_by をセット
                $rec->fill([
                    'data_id'    => $data->id,
                    'company_id' => $data->company_id,
                    'group_id'   => $data->group_id,
                    'created_by' => $userId ?: null,
                ]);
            }

            // 毎回更新されるフィールド
            $rec->payload   = $payload;
            $rec->updated_by = $userId ?: null;

            $rec->save();
        });

        if ($request->input('redirect_to') === 'syori') {
            return redirect()->route('furusato.syori', ['data_id' => $data->id])->with('success', '保存しました');
        }

        return redirect()->route('furusato.input', ['data_id' => $data->id])->with('success', '保存しました');
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

        if ($request->input('redirect_to') === 'input') {
            return redirect()->route('furusato.input', ['data_id' => $data->id])->with('success', '保存しました');
        }

        return redirect()->route('data.index')->with('success', '保存しました');
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

    public function shotokuMaster(Request $request)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('tax.furusato.master.shotoku_master', [
            'dataId' => $data->id,
        ]);
    }

    public function juminMaster(Request $request)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('tax.furusato.master.jumin_master', [
            'dataId' => $data->id,
        ]);
    }

    public function tokureiMaster(Request $request)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('tax.furusato.master.tokurei_master', [
            'dataId' => $data->id,
        ]);
    }

    public function shinkokutokureiMaster(Request $request)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('tax.furusato.master.shinkokutokurei_master', [
            'dataId' => $data->id,
        ]);
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