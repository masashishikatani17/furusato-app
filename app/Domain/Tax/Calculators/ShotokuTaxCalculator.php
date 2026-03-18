<?php

namespace App\Domain\Tax\Calculators;

use App\Domain\Tax\Contracts\MasterProviderContract;
use App\Services\Tax\Contracts\ProvidesKeys;

class ShotokuTaxCalculator implements ProvidesKeys
{
    public const ID = 'tax.shotoku';
    // 【制度順】フェーズD：所得税額（tb_*の後）
    public const ORDER = 5100;
    public const ANCHOR = 'tax';
    public const BEFORE = [];
    public const AFTER = [KojoAggregationCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public function __construct(
        private readonly MasterProviderContract $masterProvider,
    ) {
    }

    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $p) {
            // 総合課税の所得税額
            $keys[] = sprintf('tax_zeigaku_shotoku_%s', $p);
            // 分離課税（所得税側）の税額（第三表用）
            $keys[] = sprintf('bunri_zeigaku_sogo_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_tanki_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_choki_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_joto_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_haito_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_sakimono_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_sanrin_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_taishoku_shotoku_%s', $p);
            $keys[] = sprintf('bunri_zeigaku_gokei_shotoku_%s', $p);
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $year = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        $companyId = $ctx['company_id'] ?? null;
        if ($companyId !== null) {
            $companyId = (int) $companyId;
        }

        // 分離課税ON/OFF（所得税側でも同じ syori_settings を使う）
        $settings = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];

        $rates = $this->masterProvider
            ->getShotokuRates($year, $companyId)
            ->all();

        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $period) {
            // SoT 統一：総合課税の課税標準は tb_sogo_shotoku_* のみを参照
            $sogoKey = sprintf('tb_sogo_shotoku_%s', $period);
            $sogoAmount = $this->n($payload[$sogoKey] ?? null);
            $taxKey = sprintf('tax_zeigaku_shotoku_%s', $period);
            $taxSogo = $this->calculateTaxAmount($rates, $sogoAmount);
            $updates[$taxKey] = $taxSogo;

            // ▼ 分離課税がOFFなら、分離系の税額はすべて0のまま
            $bunriOn = (int)($settings[sprintf('bunri_flag_%s', $period)] ?? $settings['bunri_flag'] ?? 0) === 1;
            if (! $bunriOn) {
                continue;
            }

            // 総合課税分（第三表「総合課税」行）は所得税額そのものをミラー
            $updates[sprintf('bunri_zeigaku_sogo_shotoku_%s', $period)] = $taxSogo;

            // 山林：tb_sanrin_shotoku_* を 1/5 にして累進表 → 税額×5
            $sanTaxable = $this->n($payload[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null);
            $san = 0;
            if ($sanTaxable > 0) {
                $perYear = (int) floor($sanTaxable / 5);
                $san = $this->calculateTaxAmount($rates, $perYear) * 5;
            }
            $updates[sprintf('bunri_zeigaku_sanrin_shotoku_%s', $period)] = $san;

            // 退職：tb_taishoku_shotoku_* を累進表でそのまま計算
            $taiTaxable = $this->n($payload[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null);
            $tai = $taiTaxable > 0 ? $this->calculateTaxAmount($rates, $taiTaxable) : 0;
            $updates[sprintf('bunri_zeigaku_taishoku_shotoku_%s', $period)] = $tai;

            // 短期譲渡：一般30% ＋ 軽減15%
            $tIppan  = $this->n($payload[sprintf('tb_joto_tanki_ippan_shotoku_%s',  $period)] ?? null);
            $tKeigen = $this->n($payload[sprintf('tb_joto_tanki_keigen_shotoku_%s', $period)] ?? null);
            $zeigakuTanki = (int) floor($tIppan * 0.30 + $tKeigen * 0.15);
            $updates[sprintf('bunri_zeigaku_tanki_shotoku_%s', $period)] = $zeigakuTanki;

            // 長期譲渡：一般15% ＋ 特定/軽課は段階税率（10%/15%）
            $cIppan   = $this->n($payload[sprintf('tb_joto_choki_ippan_shotoku_%s',   $period)] ?? null);
            $cTokutei = $this->n($payload[sprintf('tb_joto_choki_tokutei_shotoku_%s', $period)] ?? null);
            $cKeika   = $this->n($payload[sprintf('tb_joto_choki_keika_shotoku_%s',   $period)] ?? null);

            // 特定分：2,000万円以下10%、超過分15%
            if ($cTokutei <= 0) {
                $tokuteiTax = 0;
            } elseif ($cTokutei <= 20_000_000) {
                $tokuteiTax = (int) floor($cTokutei * 0.10);
            } else {
                $tokuteiTax = (int) floor(($cTokutei - 20_000_000) * 0.15 + 2_000_000);
            }

            // 軽課分：6,000万円以下10%、超過分15%
            if ($cKeika <= 0) {
                $keikaTax = 0;
            } elseif ($cKeika <= 60_000_000) {
                $keikaTax = (int) floor($cKeika * 0.10);
            } else {
                $keikaTax = (int) floor(($cKeika - 60_000_000) * 0.15 + 6_000_000);
            }

            $zeigakuChoki = (int) floor($cIppan * 0.15 + $tokuteiTax + $keikaTax);
            $updates[sprintf('bunri_zeigaku_choki_shotoku_%s', $period)] = $zeigakuChoki;

            // 一般・上場株式の譲渡：合算して15%
            $jotoTaxable =
                $this->n($payload[sprintf('tb_ippan_kabuteki_joto_shotoku_%s', $period)] ?? null) +
                $this->n($payload[sprintf('tb_jojo_kabuteki_joto_shotoku_%s',  $period)] ?? null);
            $zeigakuJoto = (int) floor($jotoTaxable * 0.15);
            $updates[sprintf('bunri_zeigaku_joto_shotoku_%s', $period)] = $zeigakuJoto;

            // 上場株式等の配当等：15%
            $haitoTaxable = $this->n($payload[sprintf('tb_jojo_kabuteki_haito_shotoku_%s', $period)] ?? null);
            $zeigakuHaito = (int) floor($haitoTaxable * 0.15);
            $updates[sprintf('bunri_zeigaku_haito_shotoku_%s', $period)] = $zeigakuHaito;

            // 先物取引：15%
            $sakiTaxable = $this->n($payload[sprintf('tb_sakimono_shotoku_%s', $period)] ?? null);
            $zeigakuSakimono = (int) floor($sakiTaxable * 0.15);
            $updates[sprintf('bunri_zeigaku_sakimono_shotoku_%s', $period)] = $zeigakuSakimono;

            // 合計（第三表の「合計（第一表へ）」行）
            $gokei =
                $taxSogo +
                $zeigakuTanki +
                $zeigakuChoki +
                $zeigakuJoto +
                $zeigakuHaito +
                $zeigakuSakimono +
                $san +
                $tai;
            $updates[sprintf('bunri_zeigaku_gokei_shotoku_%s', $period)] = $gokei;

            //　分離ON年度：第一表の tax_zeigaku_shotoku_* は「総合＋分離」の合算額を表示
            // （第三表の合計（第一表へ）と一致させる）
            $updates[$taxKey] = $gokei;
        }

        return array_replace($payload, $updates);
    }

    /**
     * @param  array<int, array<string, mixed>|object>  $rates
     */
    private function calculateTaxAmount(array $rates, int $amount): int
    {
        $taxable = max(0, $amount);

        foreach ($rates as $rate) {
            $rate = is_array($rate) ? $rate : (array) $rate;
            $lower = (int) ($rate['lower'] ?? 0);
            $upper = array_key_exists('upper', $rate) ? $rate['upper'] : null;

            if ($taxable < $lower) {
                continue;
            }

            if ($upper !== null && $taxable > $upper) {
                continue;
            }

            $rateDecimal = (float) ($rate['rate'] ?? 0) / 100;
            $deduction = (int) ($rate['deduction_amount'] ?? 0);
            $value = $taxable * $rateDecimal - $deduction;

            return (int) $value;
        }

        return 0;
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