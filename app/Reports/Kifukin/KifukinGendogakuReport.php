<?php

namespace App\Reports\Kifukin;

use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Domain\Tax\Factory\SyoriSettingsFactory;
use App\Domain\Tax\Support\PayloadNormalizer;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Domain\Tax\Services\FurusatoScenarioTaxSummaryService;
use App\Reports\Contracts\ReportInterface;

class KifukinGendogakuReport implements ReportInterface
{
    public function viewName(): string
    {
        // Blade名は番号付き、URLキーは番号なし（A案）
        return 'pdf/1_kifukingendogaku';
    }

    public function buildViewData(Data $data): array
    {
        // 帳票内の数値は「計算済みSoT（DB payload）」から組み立てて渡す。
        $guestName = $data->guest?->name ?? '（名称未登録）';
        $year = (int)($data->kihu_year ?? now()->year);

        // ------------------------------
        // 1) SoT payload（優先：FurusatoResult.payload['payload'] → 次点：FurusatoInput.payload）
        // ------------------------------
        $payload = [];
        $storedResults = FurusatoResult::query()
            ->where('data_id', (int)$data->id)
            ->value('payload');
        if (is_array($storedResults)) {
            $candidate = $storedResults['payload'] ?? $storedResults['upper'] ?? null;
            if (is_array($candidate)) {
                $payload = $candidate;
            }
        }
        if ($payload === []) {
            $storedInput = FurusatoInput::query()
                ->where('data_id', (int)$data->id)
                ->value('payload');
            if (is_array($storedInput)) {
                $payload = $storedInput;
            }
        }

        /** @var PayloadNormalizer $normalizer */
        $normalizer = app(PayloadNormalizer::class);
        $payload = $normalizer->normalize($payload);

        // ------------------------------
        // 2) ctx（syori_settings + master year + company/data）
        // ------------------------------
        /** @var SyoriSettingsFactory $syoriFactory */
        $syoriFactory = app(SyoriSettingsFactory::class);
        $syoriSettings = $syoriFactory->buildInitial($data);

        $ctx = [
            'master_kihu_year' => 2025,
            'kihu_year'        => $year,
            'company_id'       => $data->company_id !== null ? (int)$data->company_id : null,
            'data_id'          => $data->id !== null ? (int)$data->id : null,
            'syori_settings'   => $syoriSettings,
        ];

        // ------------------------------
        // 3) 実利上限（自己負担<=2,000円）＋ ①〜④税額比較
        // ------------------------------
        /** @var FurusatoPracticalUpperLimitService $upperSvc */
        $upperSvc = app(FurusatoPracticalUpperLimitService::class);
        $upper = $upperSvc->compute($payload, $ctx);

        // 上限額は Service 側で
        //   「1円単位探索 → 千円未満切捨て →（念のためOK再確認）」まで完了して返る想定。
        // よって、帳票側では二重に切捨てをしない（=返ってきた値を最終上限として扱う）。
        $yMaxFloor = (int)($upper['y_max_total'] ?? 0);
        $yMaxTotalRaw = (int)($upper['y_max_total_raw'] ?? $yMaxFloor);

        // ★重要：現状のふるさと寄付額（y_current）は
        //   「所得税側=0 / 住民税側だけ入力」でも成立するため、
        //   シナリオ計算に渡す payload 側へ SoT 同期してから build する。
        // これをやらないと saved_2_3（今までに寄附した額で計算した減税額）が 0 になり得る。
        $yCurrent = (int)($upper['y_current'] ?? 0);
        $payloadForScenario = $this->withFurusato($payload, $yCurrent);

        /** @var FurusatoScenarioTaxSummaryService $scSvc */
        $scSvc = app(FurusatoScenarioTaxSummaryService::class);
        $sc = $scSvc->build($payloadForScenario, $ctx, $yMaxFloor);

        // ②（その他寄附=現状、ふるさと=0）を基準にした減税額
        $s23 = is_array($sc['saved_2_3'] ?? null) ? $sc['saved_2_3'] : ['itax'=>0,'jumin'=>0];
        $s24 = is_array($sc['saved_2_4'] ?? null) ? $sc['saved_2_4'] : ['itax'=>0,'jumin'=>0];
        $s34 = is_array($sc['saved_3_4'] ?? null) ? $sc['saved_3_4'] : ['itax'=>0,'jumin'=>0];

        // 「今までに寄附した額」は、所得税側が 0 でも住民税側に入力があれば拾う必要がある。
        // FurusatoPracticalUpperLimitService が max(itax,pref,muni) を y_current として返す。
        $yAdd = max(0, $yMaxFloor - $yCurrent);

        $row = function (int $donation, array $saved): array {
            $it = max(0, (int)($saved['itax'] ?? 0));
            $ju = max(0, (int)($saved['jumin'] ?? 0));
            $tot = $it + $ju;
            $burden = max(0, $donation - $tot);
            return [
                'donation' => $donation,
                'itax'     => $it,
                'jumin'    => $ju,
                'total'    => $tot,
                'burden'   => $burden,
            ];
        };

        $tableTop = [
            'limit'   => $row($yMaxFloor, $s24),
            'current' => $row($yCurrent,  $s23),
            'diff'    => $row($yAdd,      $s34),
            'meta' => [
                'y_max_total_raw'  => $yMaxTotalRaw,
                'y_max_total_floor'=> $yMaxFloor,
                'y_current'        => $yCurrent,
                'y_add'            => $yAdd,
            ],
        ];

        // ------------------------------
        // 4) 内訳（当年=curr）
        // ------------------------------
        $cats = [
            'furusato',
            'kyodobokin_nisseki',
            'seito',
            'npo',
            'koueki',
            'kuni',
            'sonota',
        ];
        $detail = [];
        foreach ($cats as $cat) {
            $detail[$cat] = [
                // 所得税：所得控除
                'itax_income' => $this->n($payload["shotokuzei_shotokukojo_{$cat}_curr"] ?? 0),
                // 所得税：税額控除（政党/NPO/公益のみ想定だが、値があればそのまま出す）
                'itax_credit' => $this->n($payload["shotokuzei_zeigakukojo_{$cat}_curr"] ?? 0),
                // 所得税：合計（表示用）
                'itax_total'  => 0,
                // 住民税：市/県（税額控除）
                'rtax_muni'   => $this->n($payload["juminzei_zeigakukojo_muni_{$cat}_curr"] ?? 0),
                'rtax_pref'   => $this->n($payload["juminzei_zeigakukojo_pref_{$cat}_curr"] ?? 0),
            ];
            $detail[$cat]['itax_total'] = $detail[$cat]['itax_income'] + $detail[$cat]['itax_credit'];
        }
        // 合計行
        $sum = ['itax_income'=>0,'itax_credit'=>0,'itax_total'=>0,'rtax_muni'=>0,'rtax_pref'=>0];
        foreach ($detail as $rowv) {
            $sum['itax_income'] += (int)$rowv['itax_income'];
            $sum['itax_credit'] += (int)$rowv['itax_credit'];
            $sum['itax_total']  += (int)$rowv['itax_total'];
            $sum['rtax_muni']   += (int)$rowv['rtax_muni'];
            $sum['rtax_pref']   += (int)$rowv['rtax_pref'];
        }
        $detail['total'] = $sum;

        // ------------------------------
        // 5) 和暦表示（帳票文言用）
        // ------------------------------
        $wareki = $this->toWarekiYear($year);

        return [
            'title'      => '寄附金上限額',
            'year'       => $year,
            'wareki_year'=> $wareki,
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
            // 帳票差し込み用
            'kifukin_upper_table' => $tableTop,
            'kifukin_breakdown_curr' => $detail,
        ];
    }

    public function fileName(Data $data): string
    {
        $year = (int)($data->kihu_year ?? now()->year);
        return "寄附金上限額_{$year}_data{$data->id}.pdf";
    }

    // 全帳票：6_jintekikojosatyosei と同じ（A4横）
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }

    private function floorToThousands(int $v): int
    {
        if ($v <= 0) return 0;
        return (int) (floor($v / 1000) * 1000);
    }

    /**
     * ふるさと寄付額の注入（同額コピー運用）
     * - 所得税側：shotokuzei_shotokukojo_furusato_curr = y
     * - 住民税側：pref/muni のふるさとも同額にする（readonlyコピー運用）
     */
    private function withFurusato(array $payload, int $y): array
    {
        $y = max(0, $y);
        $payload['shotokuzei_shotokukojo_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_pref_furusato_curr'] = $y;
        $payload['juminzei_zeigakukojo_muni_furusato_curr'] = $y;
        return $payload;
    }

    private function toWarekiYear(int $year): string
    {
        if ($year >= 2019) {
            return sprintf('令和%d年', $year - 2018);
        }
        if ($year >= 1989) {
            return sprintf('平成%d年', $year - 1988);
        }
        if ($year >= 1926) {
            return sprintf('昭和%d年', $year - 1925);
        }
        return (string) $year;
    }
}


