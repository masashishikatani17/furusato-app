<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use Illuminate\Support\Facades\Log;

class BunriNettingCalculator implements ProvidesKeys
{
    public const ID = 'bunri.netting';
    public const ORDER = 4020;
    public const BEFORE = [];
    public const AFTER = [];

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $period) {
            // 差引（＝収入−必要経費）を「通算前」値として公開
            $keys[] = sprintf('before_tsusan_tanki_ippan_%s',  $period);
            $keys[] = sprintf('before_tsusan_tanki_keigen_%s', $period);
            $keys[] = sprintf('before_tsusan_choki_ippan_%s',  $period);
            $keys[] = sprintf('before_tsusan_choki_tokutei_%s',$period);
            $keys[] = sprintf('before_tsusan_choki_keika_%s',  $period);

            // 第1次通算後
            $keys[] = sprintf('after_1jitsusan_tanki_ippan_%s',   $period);
            $keys[] = sprintf('after_1jitsusan_tanki_keigen_%s',  $period);
            $keys[] = sprintf('after_1jitsusan_choki_ippan_%s',   $period);
            $keys[] = sprintf('after_1jitsusan_choki_tokutei_%s', $period);
            $keys[] = sprintf('after_1jitsusan_choki_keika_%s',   $period);

            // 第2次通算後（＝損益通算後として details 側 tsusango_%_* に橋渡しする値）
            $keys[] = sprintf('after_2jitsusan_tanki_ippan_%s',   $period);
            $keys[] = sprintf('after_2jitsusan_tanki_keigen_%s',  $period);
            $keys[] = sprintf('after_2jitsusan_choki_ippan_%s',   $period);
            $keys[] = sprintf('after_2jitsusan_choki_tokutei_%s', $period);
            $keys[] = sprintf('after_2jitsusan_choki_keika_%s',   $period);

            // ▼ 特別控除適用後の「行レベル 譲渡所得金額」
            $keys[] = sprintf('joto_shotoku_tanki_ippan_%s',   $period);
            $keys[] = sprintf('joto_shotoku_tanki_keigen_%s',  $period);
            $keys[] = sprintf('joto_shotoku_choki_ippan_%s',   $period);
            $keys[] = sprintf('joto_shotoku_choki_tokutei_%s', $period);
            $keys[] = sprintf('joto_shotoku_choki_keika_%s',   $period);

            // ▼ 区分合計（短期／長期）
            $keys[] = sprintf('joto_shotoku_tanki_gokei_%s', $period);
            $keys[] = sprintf('joto_shotoku_choki_gokei_%s', $period);
        }
        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $period
     * @return array<string, int>
     */
    public function compute(array $payload, string $period): array
    {
        if (! in_array($period, self::PERIODS, true)) {
            return [];
        }

        // ▼ デバッグ用：まずは payload に何が入ってきているかを確認する
        Log::debug('bunri.netting: raw inputs', [
            'period'                => $period,
            'syunyu_tanki_ippan'    => $payload[sprintf('syunyu_tanki_ippan_%s',   $period)] ?? null,
            'keihi_tanki_ippan'     => $payload[sprintf('keihi_tanki_ippan_%s',    $period)] ?? null,
            'syunyu_tanki_keigen'   => $payload[sprintf('syunyu_tanki_keigen_%s',  $period)] ?? null,
            'keihi_tanki_keigen'    => $payload[sprintf('keihi_tanki_keigen_%s',   $period)] ?? null,
            'syunyu_choki_ippan'    => $payload[sprintf('syunyu_choki_ippan_%s',   $period)] ?? null,
            'keihi_choki_ippan'     => $payload[sprintf('keihi_choki_ippan_%s',    $period)] ?? null,
            'syunyu_choki_tokutei'  => $payload[sprintf('syunyu_choki_tokutei_%s', $period)] ?? null,
            'keihi_choki_tokutei'   => $payload[sprintf('keihi_choki_tokutei_%s',  $period)] ?? null,
            'syunyu_choki_keika'    => $payload[sprintf('syunyu_choki_keika_%s',   $period)] ?? null,
            'keihi_choki_keika'     => $payload[sprintf('keihi_choki_keika_%s',    $period)] ?? null,
        ]);

        // 1) 差引金額（＝収入−必要経費）を基礎値として作成（details/bunri_joto_details の入力キーに準拠）
        $shortGeneral = $this->diff($payload, 'tanki_ippan',  $period);
        $shortReduced = $this->diff($payload, 'tanki_keigen', $period);
        $longGeneral  = $this->diff($payload, 'choki_ippan',  $period);
        $longTokutei  = $this->diff($payload, 'choki_tokutei',$period);
        $longKeika    = $this->diff($payload, 'choki_keika',  $period);

        // 公開：通算前（差引）
        $before = [
            sprintf('before_tsusan_tanki_ippan_%s',   $period) => $shortGeneral,
            sprintf('before_tsusan_tanki_keigen_%s',  $period) => $shortReduced,
            sprintf('before_tsusan_choki_ippan_%s',   $period) => $longGeneral,
            sprintf('before_tsusan_choki_tokutei_%s', $period) => $longTokutei,
            sprintf('before_tsusan_choki_keika_%s',   $period) => $longKeika,
        ];

        // 2) 第1次通算（短期内：一般⇔軽減 の相互補填、長期内：一般⇔特定⇔軽課 の並び替え補填）
        // 短期：tanki_ippan と tanki_keigen のみ
        $sG = $shortGeneral; // short general
        $sR = $shortReduced; // short reduced
        if ($sG >= 0 && $sR < 0) {
            $move = min($sG, -$sR);
            $sG  -= $move;
            $sR  += $move;
        } elseif ($sG < 0 && $sR >= 0) {
            $move = min($sR, -$sG);
            $sG  += $move;
            $sR  -= $move;
        }

        // 長期：choki_ippan（G）・choki_tokutei（T）・choki_keika（K）間で一般を優先的に充足
        $lG = $longGeneral;
        $lT = $longTokutei;
        $lK = $longKeika;

        // まず「一般」をマイナスなら特定→軽課の順に補填
        $needG  = max(0, -$lG);
        $mvTG   = min(max(0, $lT), $needG);
        $lG    += $mvTG;
        $lT    -= $mvTG;
        $needG2 = max(0, -$lG);
        $mvKG   = min(max(0, $lK), $needG2);
        $lG    += $mvKG;
        $lK    -= $mvKG;

        // 次に「特定」をマイナスなら一般→軽課の順に補填
        $needT  = max(0, -$lT);
        $mvGT   = min(max(0, $lG), $needT);
        $lG    -= $mvGT;
        $lT    += $mvGT;
        $needT2 = max(0, -$lT);
        $mvKT   = min(max(0, $lK), $needT2);
        $lK    -= $mvKT;
        $lT    += $mvKT;

        // 最後に「軽課」をマイナスなら一般→特定の順に補填
        $needK  = max(0, -$lK);
        $mvGK   = min(max(0, $lG), $needK);
        $lG    -= $mvGK;
        $lK    += $mvGK;
        $needK2 = max(0, -$lK);
        $mvTK   = min(max(0, $lT), $needK2);
        $lT    -= $mvTK;
        $lK    += $mvTK;

        $after1 = [
            sprintf('after_1jitsusan_tanki_ippan_%s',   $period) => $sG,
            sprintf('after_1jitsusan_tanki_keigen_%s',  $period) => $sR,
            sprintf('after_1jitsusan_choki_ippan_%s',   $period) => $lG,
            sprintf('after_1jitsusan_choki_tokutei_%s', $period) => $lT,
            sprintf('after_1jitsusan_choki_keika_%s',   $period) => $lK,
        ];

        // 3) 第2次通算（短期群⇔長期群の相互補填）
        $sSumNeg = min(0, $sG) + min(0, $sR);
        $lSumNeg = min(0, $lG) + min(0, $lT) + min(0, $lK);
        $needS   = max(0, -$sSumNeg);
        $needL   = max(0, -$lSumNeg);

        // 長期→短期へ補填（長期側にプラスがあり短期側がマイナスのとき）
        if ($needS > 0 && ($lG > 0 || $lT > 0 || $lK > 0)) {
            $give  = 0;
            $fromG = min(max(0, $lG), $needS - $give); $lG -= $fromG; $give += $fromG;
            $fromT = min(max(0, $lT), $needS - $give); $lT -= $fromT; $give += $fromT;
            $fromK = min(max(0, $lK), $needS - $give); $lK -= $fromK; $give += $fromK;
            // 短期の各マイナスを小さい方から埋める（一般→軽減の順で埋める）
            if ($sG < 0) { $use = min($give, -$sG); $sG += $use; $give -= $use; }
            if ($give > 0 && $sR < 0) { $use = min($give, -$sR); $sR += $use; $give -= $use; }
        }

        // 短期→長期へ補填（短期側にプラスがあり長期側がマイナスのとき）
        if ($needL > 0 && ($sG > 0 || $sR > 0)) {
            $give  = 0;
            $fromSG = min(max(0, $sG), $needL - $give); $sG -= $fromSG; $give += $fromSG;
            $fromSR = min(max(0, $sR), $needL - $give); $sR -= $fromSR; $give += $fromSR;
            // 長期の各マイナスを小さい方から埋める（一般→特定→軽課の順で埋める）
            if ($lG < 0) { $use = min($give, -$lG); $lG += $use; $give -= $use; }
            if ($give > 0 && $lT < 0) { $use = min($give, -$lT); $lT += $use; $give -= $use; }
            if ($give > 0 && $lK < 0) { $use = min($give, -$lK); $lK += $use; $give -= $use; }
        }

        $after2 = [
            sprintf('after_2jitsusan_tanki_ippan_%s',   $period) => $sG,
            sprintf('after_2jitsusan_tanki_keigen_%s',  $period) => $sR,
            sprintf('after_2jitsusan_choki_ippan_%s',   $period) => $lG,
            sprintf('after_2jitsusan_choki_tokutei_%s', $period) => $lT,
            sprintf('after_2jitsusan_choki_keika_%s',   $period) => $lK,
        ];

        // ===== 4) 特別控除 → 行レベル譲渡所得金額 =====
        // 画面入力の tokubetsukojo_%_% を取得し、joto_shotoku_%_% = max(0, after_2 - tokubetsu) を算出
        $rowSuffixes = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
        ];
        $joto = [];
        foreach ($rowSuffixes as $suf) {
            $after2Key = sprintf('after_2jitsusan_%s_%s', $suf, $period);
            $tokKey    = sprintf('tokubetsukojo_%s_%s',   $suf, $period);
            $after2Val = $after2[$after2Key] ?? 0;
            $tokVal    = $this->n($payload[$tokKey] ?? null);
            // 0 下限（必要に応じて仕様変更可）
            $val = $after2Val - $tokVal;
            if ($val < 0) $val = 0;
            $jotoKey = sprintf('joto_shotoku_%s_%s', $suf, $period);
            $joto[$jotoKey] = $val;
        }

        // ===== 5) 区分合計（短期／長期） =====
        $tankiGokei = ($joto[sprintf('joto_shotoku_%s_%s', 'tanki_ippan',  $period)] ?? 0)
                    + ($joto[sprintf('joto_shotoku_%s_%s', 'tanki_keigen', $period)] ?? 0);
        $chokiGokei = ($joto[sprintf('joto_shotoku_%s_%s', 'choki_ippan',   $period)] ?? 0)
                    + ($joto[sprintf('joto_shotoku_%s_%s', 'choki_tokutei', $period)] ?? 0)
                    + ($joto[sprintf('joto_shotoku_%s_%s', 'choki_keika',   $period)] ?? 0);
        $gokei = [
            sprintf('joto_shotoku_tanki_gokei_%s', $period) => $tankiGokei,
            sprintf('joto_shotoku_choki_gokei_%s', $period) => $chokiGokei,
        ];

Log::debug('bunri.netting: outputs snapshot', [
    'period' => $period,
    'before' => [
        'before_tsusan_tanki_ippan'   => $before[sprintf('before_tsusan_tanki_ippan_%s', $period)] ?? null,
        'before_tsusan_tanki_keigen'  => $before[sprintf('before_tsusan_tanki_keigen_%s', $period)] ?? null,
        'before_tsusan_choki_ippan'   => $before[sprintf('before_tsusan_choki_ippan_%s', $period)] ?? null,
        'before_tsusan_choki_tokutei' => $before[sprintf('before_tsusan_choki_tokutei_%s', $period)] ?? null,
        'before_tsusan_choki_keika'   => $before[sprintf('before_tsusan_choki_keika_%s', $period)] ?? null,
    ],
    'after2' => [
        'after_2jitsusan_tanki_ippan'   => $after2[sprintf('after_2jitsusan_tanki_ippan_%s', $period)] ?? null,
        'after_2jitsusan_tanki_keigen'  => $after2[sprintf('after_2jitsusan_tanki_keigen_%s', $period)] ?? null,
        'after_2jitsusan_choki_ippan'   => $after2[sprintf('after_2jitsusan_choki_ippan_%s', $period)] ?? null,
        'after_2jitsusan_choki_tokutei' => $after2[sprintf('after_2jitsusan_choki_tokutei_%s', $period)] ?? null,
        'after_2jitsusan_choki_keika'   => $after2[sprintf('after_2jitsusan_choki_keika_%s', $period)] ?? null,
    ],
    'joto' => [
        'joto_shotoku_tanki_ippan'   => $joto[sprintf('joto_shotoku_tanki_ippan_%s', $period)] ?? null,
        'joto_shotoku_tanki_keigen'  => $joto[sprintf('joto_shotoku_tanki_keigen_%s', $period)] ?? null,
        'joto_shotoku_choki_ippan'   => $joto[sprintf('joto_shotoku_choki_ippan_%s', $period)] ?? null,
        'joto_shotoku_choki_tokutei' => $joto[sprintf('joto_shotoku_choki_tokutei_%s', $period)] ?? null,
        'joto_shotoku_choki_keika'   => $joto[sprintf('joto_shotoku_choki_keika_%s', $period)] ?? null,
        'joto_shotoku_tanki_gokei'   => $gokei[sprintf('joto_shotoku_tanki_gokei_%s', $period)] ?? null,
        'joto_shotoku_choki_gokei'   => $gokei[sprintf('joto_shotoku_choki_gokei_%s', $period)] ?? null,
    ],
]);

        return array_replace($before, $after1, $after2, $joto, $gokei);
    }

    private function n(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) ((float) $value);
        }

        return 0;
    }

    /**
     * details の各行（% = tanki_ippan / tanki_keigen / choki_ippan / choki_tokutei / choki_keika）
     * について、差引（= 収入 − 必要経費）を整数で返す。
     */
    private function diff(array $payload, string $rowKey, string $period): int
    {
        $syunyu = $this->n($payload[sprintf('syunyu_%s_%s', $rowKey, $period)] ?? null);
        $keihi  = $this->n($payload[sprintf('keihi_%s_%s',  $rowKey, $period)] ?? null);
        return $syunyu - $keihi;
    }
}