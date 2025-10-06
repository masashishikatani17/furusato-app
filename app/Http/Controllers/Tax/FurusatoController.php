<?php

namespace App\Http\Controllers\Tax;

use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\Support\FurusatoMasterSheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\FurusatoInputRequest;
use App\Models\Data;
use App\Models\FurusatoInput;
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
        $data = $this->resolveAuthorizedDataOrFail($request);
        $payload = $request->except(['_token', 'data_id']);
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

        return redirect()->route('furusato.input', ['data_id' => $data->id])->with('success', '保存しました');
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

    private function resolveAuthorizedDataOrFail(Request $request): Data
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
}