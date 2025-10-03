<?php
namespace App\Http\Controllers\Tax;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Tax\Services\FurusatoCalcService;
use App\Http\Requests\FurusatoInputRequest;

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

    public function calc(FurusatoInputRequest $req, FurusatoCalcService $svc)
    {
        $dataId = $req->integer('data_id') ?: null;
        $in = $req->toDto();
        $upper = $svc->calcUpperLimit($in);
        $donation = $svc->calcDonationOverview($in);
        if ($req->wantsJson()) {
            return response()->json([
                'upper' => $upper,
                'donation' => $donation,
            ]);
        }

        session()->flash('_old_input', $req->except(['_token']));

        return view('tax.furusato.input', [
            'out' => $upper,
            'donation' => $donation,
            'dataId' => $dataId,
        ]);
    }
}
