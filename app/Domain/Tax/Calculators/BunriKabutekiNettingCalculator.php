<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

final class BunriKabutekiNettingCalculator implements ProvidesKeys
{
    public const ID = 'bunri.kabuteki.netting';
    public const ORDER = 4030;

    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];

        foreach (self::PERIODS as $period) {
            // 入力（details）
            $keys[] = sprintf('syunyu_ippan_joto_%s', $period);
            $keys[] = sprintf('keihi_ippan_joto_%s', $period);
            $keys[] = sprintf('syunyu_jojo_joto_%s', $period);
            $keys[] = sprintf('keihi_jojo_joto_%s', $period);
            $keys[] = sprintf('kurikoshi_jojo_joto_%s', $period);
            $keys[] = sprintf('syunyu_jojo_haito_%s', $period);
            $keys[] = sprintf('keihi_jojo_haito_%s', $period);

            // サーバ確定（details表示用 SoT）
            $keys[] = sprintf('shotoku_ippan_joto_%s', $period);
            $keys[] = sprintf('shotoku_jojo_joto_%s', $period);
            $keys[] = sprintf('shotoku_jojo_haito_%s', $period);
            $keys[] = sprintf('tsusango_ippan_joto_%s', $period);
            $keys[] = sprintf('tsusango_jojo_joto_%s', $period);
            $keys[] = sprintf('tsusango_jojo_haito_%s', $period);
            $keys[] = sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period);
            $keys[] = sprintf('shotoku_after_kurikoshi_jojo_joto_%s', $period);
            $keys[] = sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period);

            // 既存：上場株式等の損益通算（result_details でも参照）
            $keys[] = sprintf('before_tsusan_jojo_joto_%s', $period);
            $keys[] = sprintf('before_tsusan_jojo_haito_%s', $period);
            $keys[] = sprintf('after_tsusan_jojo_joto_%s', $period);
            $keys[] = sprintf('after_tsusan_jojo_haito_%s', $period);
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

        // 1) details の入力（収入/経費）から「所得金額（通算前）」をサーバで確定
        $ippanSyunyu = $this->normalize($payload[sprintf('syunyu_ippan_joto_%s', $period)] ?? null);
        $ippanKeihi  = $this->normalize($payload[sprintf('keihi_ippan_joto_%s',  $period)] ?? null);
        $shotokuIppan = $ippanSyunyu - $ippanKeihi;

        $jojoSyunyu = $this->normalize($payload[sprintf('syunyu_jojo_joto_%s', $period)] ?? null);
        $jojoKeihi  = $this->normalize($payload[sprintf('keihi_jojo_joto_%s',  $period)] ?? null);
        $shotokuJojoTransfer = $jojoSyunyu - $jojoKeihi;

        $haitoSyunyu = $this->normalize($payload[sprintf('syunyu_jojo_haito_%s', $period)] ?? null);
        $haitoKeihi  = $this->normalize($payload[sprintf('keihi_jojo_haito_%s',  $period)] ?? null);
        $shotokuJojoDividendRaw = $haitoSyunyu - $haitoKeihi;

        // 2) 上場株式等：譲渡（損失OK）⇔配当（0下限）を損益通算
        $beforeTransfer = $shotokuJojoTransfer;
        $beforeDividend = max($shotokuJojoDividendRaw, 0);

        $listedTransfer = $beforeTransfer;
        $listedDividendPos = max(0, $beforeDividend);
        $useDividend = min($listedDividendPos, max(0, -$listedTransfer));

        $afterTransfer = $listedTransfer + $useDividend;
        $afterDividend = max(0, $listedDividendPos - $useDividend);

        // 3) tsusango（損益通算後）の SoT を確定
        $tsusangoIppan = $shotokuIppan;          // 一般株式等：ここでは通算なし（そのまま）
        $tsusangoJojoTransfer = $afterTransfer;  // 上場譲渡：通算後
        $tsusangoJojoDividend = $afterDividend;  // 上場配当：通算後（0下限）

        // 4) 繰越控除（上場株式等の譲渡のみ）
        $kurikoshi = $this->normalize($payload[sprintf('kurikoshi_jojo_joto_%s', $period)] ?? null);
        $deduct = min(max(0, $tsusangoJojoTransfer), max(0, $kurikoshi));
        $afterKurikoshiJojoTransfer = $tsusangoJojoTransfer - $deduct;

        return [
            // details 表示（通算前の所得金額）
            sprintf('shotoku_ippan_joto_%s', $period) => $shotokuIppan,
            sprintf('shotoku_jojo_joto_%s',  $period) => $shotokuJojoTransfer,
            sprintf('shotoku_jojo_haito_%s', $period) => $shotokuJojoDividendRaw,

            // details 表示（損益通算後）
            sprintf('tsusango_ippan_joto_%s', $period) => $tsusangoIppan,
            sprintf('tsusango_jojo_joto_%s',  $period) => $tsusangoJojoTransfer,
            sprintf('tsusango_jojo_haito_%s', $period) => $tsusangoJojoDividend,

            // details 表示（繰越控除後）
            sprintf('shotoku_after_kurikoshi_ippan_joto_%s', $period) => $tsusangoIppan,
            sprintf('shotoku_after_kurikoshi_jojo_joto_%s',  $period) => $afterKurikoshiJojoTransfer,
            sprintf('shotoku_after_kurikoshi_jojo_haito_%s', $period) => $tsusangoJojoDividend,

            // 既存：result_details でも参照する上場株式等の損益通算スナップショット
            sprintf('before_tsusan_jojo_joto_%s',  $period) => $beforeTransfer,
            sprintf('before_tsusan_jojo_haito_%s', $period) => $beforeDividend,
            sprintf('after_tsusan_jojo_joto_%s',   $period) => $afterTransfer,
            sprintf('after_tsusan_jojo_haito_%s',  $period) => $afterDividend,
        ];
    }

    private function normalize(mixed $value): int
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
}