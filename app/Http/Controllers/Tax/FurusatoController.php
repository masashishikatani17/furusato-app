<?php
namespace App\Http\Controllers\Tax;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\DTO\FurusatoInput;

final class FurusatoController extends Controller
{
    public function index(Request $req)
    {
        // data_id をクエリで受け取り、View に渡す（将来 Translator でDBロードする際の足場）
        $dataId = $req->integer('data_id') ?: null;
        if ($dataId) {
            session(['selected_data_id' => $dataId]);
        }
        return view('tax.furusato.input', ['dataId' => $dataId]);
    }

    public function calc(Request $req, FurusatoCalcService $svc)
    {
        $in = new FurusatoInput(
            w17: (int)$req->input('w17'),
            w18: (int)$req->input('w18'),
            ab6: (int)$req->input('ab6'),
            ab56: max(1, (int)$req->input('ab56')),
            v6: (int)$req->input('v6', 0),
            w6: (int)$req->input('w6', 0),
            x6: (int)$req->input('x6', 0),
        );
        $out = $svc->calcUpperLimit($in);
        if ($req->wantsJson()) {
            return response()->json($out);
        }

        $dataId = $req->integer('data_id') ?: null;

        return view('tax.furusato.input', [
            'out' => $out,
            'dataId' => $dataId,
        ]);
    }
}