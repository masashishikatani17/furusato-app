<?php

namespace App\Http\Controllers\Tax;

use App\Domain\Tax\Services\FurusatoCalcService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\FurusatoInputRequest;
use Illuminate\Http\Request;

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
}