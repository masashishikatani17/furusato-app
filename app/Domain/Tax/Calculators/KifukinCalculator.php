<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

class KifukinCalculator implements ProvidesKeys
{
    public const ID = 'kojo.kifukin';
    public const ORDER = 2000;
    public const ANCHOR = 'deductions';
    public const BEFORE = [];
    public const AFTER = [];

    /** @var string[] */
    private const TOTAL_KEYS = [
        'shotoku_gokei_shotoku',
        'bunri_shotoku_tanki_ippan_shotoku',
        'bunri_shotoku_tanki_keigen_shotoku',
        'bunri_shotoku_choki_ippan_shotoku',
        'bunri_shotoku_choki_tokutei_shotoku',
        'bunri_shotoku_choki_keika_shotoku',
        'bunri_shotoku_ippan_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_joto_shotoku',
        'bunri_shotoku_jojo_kabuteki_haito_shotoku',
        'bunri_shotoku_sakimono_shotoku',
        'bunri_shotoku_sanrin_shotoku',
        'bunri_shotoku_taishoku_shotoku',
    ];

    /** @var string[] */
    private const DONATION_BASE_KEYS = [
        'shotokuzei_shotokukojo_kyodobokin_nisseki',
        'shotokuzei_shotokukojo_seito',
        'shotokuzei_shotokukojo_npo',
        'shotokuzei_shotokukojo_koueki',
        'shotokuzei_shotokukojo_kuni',
        'shotokuzei_shotokukojo_sonota',
    ];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        return [
            'shotokuzei_kojo_kifukin_prev',
            'shotokuzei_kojo_kifukin_curr',
            'juminzei_kojo_kifukin_prev',
            'juminzei_kojo_kifukin_curr',
            // ▼ 所得控除側で「元本ベース」で実際に使用した寄附額（40%枠との食い合い評価・足切り一体管理に使用）
            'used_by_income_deduction_prev',
            'used_by_income_deduction_curr',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];

        $updates = array_fill_keys(self::provides(), 0);

        foreach (['prev', 'curr'] as $period) {
            $key = sprintf('one_stop_flag_%s', $period);
            $oneStop = $this->n($settings[$key] ?? 0) === 1;

            if ($oneStop) {
                $updates[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = 0;
                // ワンストップ利用でも I は 0 として一貫させる（所得控除は使わない）
                $updates[sprintf('used_by_income_deduction_%s', $period)] = 0;
                continue;
            }

            // ▼ SoT優先：総所得金額等（合算上限の基礎）
            $sogoEtc = $this->n($payload[sprintf('sum_for_sogoshotoku_etc_%s', $period)] ?? null);
            $incomeCap40 = intdiv(max($sogoEtc, 0) * 4, 10);

            // ▼ 寄附「元本」内訳（所得控除側で使う可能性のある母集団）
            $baseDonations = $this->sumByKeys($payload, self::DONATION_BASE_KEYS, $period);
            $furusato      = $this->n($payload[sprintf('shotokuzei_shotokukojo_furusato_%s', $period)] ?? 0);
            $donationSum   = $baseDonations + $furusato;

            if ($donationSum < 2000) {
                // 足切り未満でも I は「元本の実使用量＝min(寄附元本合計, 40%枠)」として記録（税額控除側が一体管理するため）
                $updates[sprintf('used_by_income_deduction_%s', $period)] = min($donationSum, $incomeCap40);
                continue;
            }

            // ▼ 所得控除の「元本」側使用量 I（40%枠に対する消費量）…控除額の足切りはここでは考慮しない
            $usedByIncome = min($donationSum, $incomeCap40);
            $updates[sprintf('used_by_income_deduction_%s', $period)] = $usedByIncome;

            // ▼ 所得控除額（従来ロジックを維持）：所得控除の枠配分は「基礎寄附(baseDonations)＋ふるさと」を控除額として評価
            //   - 40%上限は SoT に合わせて sogoEtc を使用
            $allowedBase = min($baseDonations, $incomeCap40);
            $deduction   = $allowedBase + $furusato - 2000;

            $updates[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = max(0, $deduction);
        }

        return array_replace($payload, $updates);
    }

    private function sumTotalIncome(array $payload, string $period): int
    {
        $sum = 0;
        foreach (self::TOTAL_KEYS as $key) {
            $field = sprintf('%s_%s', $key, $period);
            $sum += $this->n($payload[$field] ?? 0);
        }

        return $sum;
    }

    /**
     * @param  string[]  $keys
     */
    private function sumByKeys(array $payload, array $keys, string $period): int
    {
        $sum = 0;
        foreach ($keys as $key) {
            $field = sprintf('%s_%s', $key, $period);
            $sum += $this->n($payload[$field] ?? 0);
        }

        return $sum;
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }
}