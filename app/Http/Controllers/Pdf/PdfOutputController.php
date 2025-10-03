<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Data;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\ReportRegistry;
use App\Services\Pdf\PdfRenderer;

class PdfOutputController extends Controller
{
    public function __construct(
        private ReportRegistry $reports,
        private PdfRenderer $renderer
    ) {}

    /** HTMLプレビュー: GET /pdf/{report}/preview?data_id=◯◯ */
    public function preview(Request $request, string $report)
    {
        $data = $this->resolveAuthorizedDataOrFail($request);
        $reportObj = $this->reports->resolve($report);
        $vars = $reportObj->buildViewData($data);
        return view($reportObj->viewName(), $vars);
    }

    /** PDFダウンロード: GET /pdf/{report}?data_id=◯◯ */
    public function download(Request $request, string $report)
    {
        $data = $this->resolveAuthorizedDataOrFail($request);
        $reportObj = $this->reports->resolve($report);
        $vars = $reportObj->buildViewData($data);
        $view = $reportObj->viewName();
        $file = $reportObj->fileName($data);
        $pdf = $this->renderer->render($view, $vars);
        return $pdf->download($file);
    }

    /** 親ファースト：Dataのview認可（会社一致＋必要なら部署一致） */
    private function resolveAuthorizedDataOrFail(Request $request): Data
    {
        $id = (int) $request->query('data_id');
        abort_unless($id > 0, 422, 'data_id が指定されていません。');
        $data = Data::with('guest')->findOrFail($id);
        $me = Auth::user();
        if ((int)$data->company_id !== (int)$me->company_id) abort(403);
        $role = strtolower((string)($me->role ?? ''));
        $isOwnerOrRegistrar = (method_exists($me, 'isOwner') && $me->isOwner()) || in_array($role, ['owner','registrar'], true);
        if (!$isOwnerOrRegistrar && (int)$data->group_id !== (int)($me->group_id ?? 0)) abort(403);
        return $data;
    }
}