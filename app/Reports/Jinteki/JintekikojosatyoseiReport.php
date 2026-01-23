<?php

namespace App\Reports\Jinteki;

use App\Models\Data;
 use App\Models\FurusatoInput;
 use App\Models\FurusatoResult;
 use App\Domain\Tax\Support\PayloadNormalizer;
use App\Reports\Contracts\ReportInterface;

class JintekikojosatyoseiReport implements ReportInterface
{
    public function viewName(): string
    {
        // resources/views/pdf/6_jintekikojosatyosei.blade.php
        return 'pdf/6_jintekikojosatyosei';
    }

    public function buildViewData(Data $data): array
    {
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
             $candidate = $storedResults['payload'] ?? $storedResults['upper'] ?? $storedResults;
             $payload = is_array($candidate) ? $candidate : [];
         }
         if ($payload === []) {
             $storedInput = FurusatoInput::query()
                 ->where('data_id', (int)$data->id)
                 ->value('payload');
             $payload = is_array($storedInput) ? $storedInput : [];
         }
 
         /** @var PayloadNormalizer $normalizer */
         $normalizer = app(PayloadNormalizer::class);
         $payload = $normalizer->normalize($payload);
 
         $n = static function ($v): int {
             if ($v === null || $v === '') return 0;
             if (is_string($v)) $v = str_replace([',', ' '], '', $v);
             return is_numeric($v) ? (int) floor((float)$v) : 0;
         };
 
         // ==========================================================
         // 帳票6（右側の「〇 / n人」）用：表示SoTをpayloadから組み立てる（curr）
         //  - 入力元：kojo_jinteki_details の入力キー（FurusatoInput.payload）
         //  - ただし「寡婦 vs ひとり親」の排他は UI/Calculator と同じ優先（ひとり親＞寡婦）
         // ==========================================================
         $p = 'curr';
         $markCircle = static fn(bool $on): string => $on ? '〇' : '';
         $markPeople = static fn(int $cnt): string => ($cnt > 0) ? ($cnt . '人') : '';

         // 入力（details）系
         $kafu = (string)($payload["kojo_kafu_applicable_{$p}"] ?? '');
         $hitorioya = (string)($payload["kojo_hitorioya_applicable_{$p}"] ?? '');
         $hitorioyaOn = in_array($hitorioya, ['父', '母', '〇'], true);
         $hitorioyaNorm = ($hitorioya === '〇') ? '母' : $hitorioya; // 互換：旧〇=母

         $kinro = (string)($payload["kojo_kinrogakusei_applicable_{$p}"] ?? '');

         // 障害者人数
         $cntShogaisha = $n($payload["kojo_shogaisha_count_{$p}"] ?? 0);
         $cntTokubetsu = $n($payload["kojo_tokubetsu_shogaisha_count_{$p}"] ?? 0);
         $cntDoukyoTokubetsu = $n($payload["kojo_doukyo_tokubetsu_shogaisha_count_{$p}"] ?? 0);

         // 扶養人数
         $cntFuyoIppan  = $n($payload["kojo_fuyo_ippan_count_{$p}"] ?? 0);
         $cntFuyoTokutei= $n($payload["kojo_fuyo_tokutei_count_{$p}"] ?? 0);
         $cntFuyoRoujinSonota = $n($payload["kojo_fuyo_roujin_sonota_count_{$p}"] ?? 0);
         $cntFuyoRoujinDoukyo = $n($payload["kojo_fuyo_roujin_doukyo_count_{$p}"] ?? 0);

         // 本人所得（配偶者控除/基礎控除の判定に使用）
         //  - 住民税の調整控除/人的控除の判定と同様、sum_for_gokeishotoku_* を参照
         $taxpayerIncome = $n($payload["sum_for_gokeishotoku_{$p}"] ?? 0);

         // 配偶者控除（details）
         $haigushaCategory = (string)($payload["kojo_haigusha_category_{$p}"] ?? 'none'); // ippan/roujin/none
         $spouseIncome = $n($payload["kojo_haigusha_tokubetsu_gokeishotoku_{$p}"] ?? 0);

         // 対象年（currの所得年）
         $targetYear = $year > 0 ? $year : null;
         // 配偶者控除の配偶者所得要件起算点（2025年分以降=58万、以前=48万）
         $spouseStart = ($targetYear !== null && $targetYear >= 2025) ? 580_000 : 480_000;

         // 本人所得レンジ（<=900 / <=950 / <=1000）
         $tier = null;
         if ($taxpayerIncome <= 9_000_000) $tier = 0;
         elseif ($taxpayerIncome <= 9_500_000) $tier = 1;
         elseif ($taxpayerIncome <= 10_000_000) $tier = 2;

         // 配偶者控除の「〇」位置（該当なしなら全て空）
         $mkHaigushaIppan = ['', '', '']; // tier 0..2
         $mkHaigushaRoujin= ['', '', ''];
         if ($tier !== null && $haigushaCategory !== 'none' && $taxpayerIncome <= 10_000_000) {
             // 配偶者控除（配偶者所得が起算点以下）
             if ($spouseIncome > 0 && $spouseIncome <= $spouseStart) {
                 if ($haigushaCategory === 'roujin') $mkHaigushaRoujin[$tier] = '〇';
                 if ($haigushaCategory === 'ippan')  $mkHaigushaIppan[$tier]  = '〇';
             }
         }

         // 基礎控除：本人所得 2,500万円以下なら上段に〇、超なら下段に〇
         $mkKisoUnder = ($taxpayerIncome <= 25_000_000) ? '〇' : '';
         $mkKisoOver  = ($taxpayerIncome >  25_000_000) ? '〇' : '';

         // 寡婦：ひとり親がONなら空欄（ひとり親優先）
         $mkKafu = (!$hitorioyaOn && $kafu === '〇');

         // ひとり親：父/母の行に〇（互換：旧〇は母扱い）
         $mkHitorioyaFather = ($hitorioyaNorm === '父');
         $mkHitorioyaMother = ($hitorioyaNorm === '母');

         // 勤労学生：〇/×
         $mkKinro = ($kinro === '〇');

         // ------------------------------
         // 2) JuminTaxCalculator の中間SoT（curr を帳票に出す）
         // ------------------------------
         $case = $n($payload['jumin_chosei_case_curr'] ?? 0);
         $a    = $n($payload['jumin_chosei_a_curr'] ?? 0);
         $b    = $n($payload['jumin_chosei_b_curr'] ?? 0);
         $cBase= $n($payload['jumin_chosei_c_base_curr'] ?? 0);
         $cAmt = $n($payload['jumin_chosei_c_amount_curr'] ?? 0);
         $max  = $n($payload['jumin_chosei_max_curr'] ?? 0);
         $final= $n($payload['jumin_choseikojo_total_curr'] ?? 0); // 最終（上限適用後）
 
         // 参考（表示調整に使う可能性があるので渡しておく）
         $taxableTotal = $n($payload['jumin_kazeishotoku_total_curr'] ?? 0);
         $humanDiff    = $n($payload['human_diff_sum_curr'] ?? 0);

        return [
            'title'      => '人的控除差調整額',
            'year'       => $year,
            'wareki_year'=> $this->toWarekiYear($year),
            'guest_name' => $guestName,
            'data_id'    => (int)$data->id,
 
             // ▼ 調整控除（中間SoT）
             'jumin_chosei_case_curr'     => $case,   // 0=対象外, 1=200万円以下, 2=200万円超
             'jumin_chosei_a_curr'        => $a,      // a（caseにより意味が変わる：計算ロジックを正）
             'jumin_chosei_b_curr'        => $b,      // b（合計課税所得金額）
             'jumin_chosei_c_base_curr'   => $cBase,  // cの母数（min/max 後）
             'jumin_chosei_c_amount_curr' => $cAmt,   // c（= c_base×5% の算出）
             'jumin_chosei_max_curr'      => $max,    // 上限（調整控除前所得割額 市県）
             'jumin_choseikojo_total_curr'=> $final,  // 最終（上限適用後）
 
             // ▼ 参考（帳票内の注記/将来調整用に渡しておく）
             'jumin_kazeishotoku_total_curr' => $taxableTotal,
             'human_diff_sum_curr'           => $humanDiff,

             // ==================================================
             // ▼ 右側の「〇 / n人」印字用（curr）
             // ==================================================
             'jinteki_mark_shogaisha_cnt_curr'          => $markPeople($cntShogaisha),
             'jinteki_mark_tokubetsu_shogaisha_cnt_curr'=> $markPeople($cntTokubetsu),
             'jinteki_mark_doukyo_tokubetsu_cnt_curr'   => $markPeople($cntDoukyoTokubetsu),

             'jinteki_mark_kafu_curr'                   => $markCircle($mkKafu),
             'jinteki_mark_hitorioya_father_curr'       => $markCircle($mkHitorioyaFather),
             'jinteki_mark_hitorioya_mother_curr'       => $markCircle($mkHitorioyaMother),
             'jinteki_mark_kinro_curr'                  => $markCircle($mkKinro),

             // 配偶者控除（本人所得レンジ×一般/老人）
             'jinteki_mark_haigusha_ippan_900_curr'      => $mkHaigushaIppan[0] ?? '',
             'jinteki_mark_haigusha_ippan_950_curr'      => $mkHaigushaIppan[1] ?? '',
             'jinteki_mark_haigusha_ippan_1000_curr'     => $mkHaigushaIppan[2] ?? '',
             'jinteki_mark_haigusha_roujin_900_curr'     => $mkHaigushaRoujin[0] ?? '',
             'jinteki_mark_haigusha_roujin_950_curr'     => $mkHaigushaRoujin[1] ?? '',
             'jinteki_mark_haigusha_roujin_1000_curr'    => $mkHaigushaRoujin[2] ?? '',

             // 扶養控除（人数）
             'jinteki_mark_fuyo_ippan_cnt_curr'          => $markPeople($cntFuyoIppan),
             'jinteki_mark_fuyo_tokutei_cnt_curr'        => $markPeople($cntFuyoTokutei),
             'jinteki_mark_fuyo_roujin_sonota_cnt_curr'  => $markPeople($cntFuyoRoujinSonota),
             'jinteki_mark_fuyo_roujin_doukyo_cnt_curr'  => $markPeople($cntFuyoRoujinDoukyo),

             // 基礎控除（本人所得レンジ）
             'jinteki_mark_kiso_under_curr'              => $mkKisoUnder,
             'jinteki_mark_kiso_over_curr'               => $mkKisoOver,
        ];
    }

    public function fileName(Data $data): string
    {
        $guest = $data->guest?->name ?? '名称未登録';
        $year  = (int)($data->kihu_year ?? now()->year);
        return "人的控除差調整額_{$year}_{$guest}_data{$data->id}.pdf";
    }

    /** PdfOutputController が存在確認して PdfRenderer に渡す（任意） */
    public function pdfOptions(Data $data): array
    {
        return [
            'paper'  => 'a4',
            'orient' => 'landscape',
        ];
    }
 
     private function toWarekiYear(int $year): string
     {
         if ($year >= 2019) return sprintf('令和%d年', $year - 2018);
         if ($year >= 1989) return sprintf('平成%d年', $year - 1988);
         if ($year >= 1926) return sprintf('昭和%d年', $year - 1925);
         return (string) $year;
     }
}
