<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;
use App\Domain\Tax\Contracts\MasterProviderContract;

/**
 * 税額（支払額）SoT を確定する（UIの tax_kijun / tax_fukkou / tax_gokei をサーバ側で再現）
 *
 * - 所得税（shotoku）：
 *   住宅ローン控除・政党/NPO/公益の税額控除（Seitoto）適用後の tax_sashihiki_shotoku_* を基礎とし、
 *   災害減免額・令和6年度分特別税額控除を差し引いた「基準所得税額」を tax_kijun_shotoku_* に出す。
 *   その2.1%を復興特別所得税額 tax_fukkou_shotoku_* とし、合計 tax_gokei_shotoku_* を確定する。
 *
 * - 住民税（jumin）：
 *   配当控除・住宅ローン控除適用後の tax_after_jutaku_jumin_* を基礎とし、
 *   住民税寄附金税額控除（kifukin_zeigaku_kojo_gokei_*：天井キャップ後）と災害減免額を差し引いた
 *   「基準（所得割残）」を tax_kijun_jumin_* とし、合計 tax_gokei_jumin_* を確定する。
 *   （住民税の復興税はUI上「－」なので tax_fukkou_jumin_* は0で保持）
 */
final class TaxGokeiCalculator implements ProvidesKeys
{
    public const ID = 'tax.gokei';
    // 結果表示寄せ（上限探索で参照するため、なるべく後段で確定）
    public const ORDER = 9700;
    public const ANCHOR = 'tax';

    public const AFTER = [
        SeitotoTokubetsuZeigakuKojoCalculator::ID,
        JuminJutakuLoanCreditCalculator::ID,
        JuminzeiKifukinCalculator::ID,
    ];
    public const BEFORE = [
        FurusatoResultCalculator::ID,
        TaxBaseMirrorCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public function __construct(
        private readonly MasterProviderContract $masterProvider,
    ) {}

    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $p) {
            $keys[] = "tax_kijun_shotoku_{$p}";
            $keys[] = "tax_fukkou_shotoku_{$p}";
            $keys[] = "tax_gokei_shotoku_{$p}";

            $keys[] = "tax_kijun_jumin_{$p}";
            $keys[] = "tax_fukkou_jumin_{$p}";
            $keys[] = "tax_gokei_jumin_{$p}";

            // 住民税（最終支払額）を市区町村/都道府県に分解したSoT
            $keys[] = "tax_gokei_jumin_muni_{$p}";
            $keys[] = "tax_gokei_jumin_pref_{$p}";
        }
        return $keys;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        $settings  = is_array($ctx['syori_settings'] ?? null) ? $ctx['syori_settings'] : [];
        $year      = isset($ctx['master_kihu_year']) ? (int) $ctx['master_kihu_year'] : 0;
        $companyId = isset($ctx['company_id']) && $ctx['company_id'] !== '' ? (int) $ctx['company_id'] : null;
        $dataId    = isset($ctx['data_id']) && $ctx['data_id'] !== '' ? (int) $ctx['data_id'] : null;

        $rateRows = $year > 0 ? $this->buildJuminRateRows($year, $companyId, $dataId) : [];

        $out = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            // --- 所得税（支払額） ---
            // まずは「寄附税額控除等を適用した後の残税額」を基礎にする
            $baseItax = $this->n($payload["tax_sashihiki_shotoku_{$p}"] ?? null);
            if ($baseItax <= 0) {
                // 保険：最低限のフォールバック（通常は tax_sashihiki が埋まる）
                $baseItax = $this->n($payload["tax_after_jutaku_shotoku_{$p}"] ?? null);
            }
            if ($baseItax <= 0) {
                $baseItax = $this->n($payload["tax_zeigaku_shotoku_{$p}"] ?? null);
            }
            $baseItax = max(0, $baseItax);

            // 災害減免額・令和6特別（入力）を差し引いた「基準所得税額」
            $saigaiItax = $this->n($payload["tax_saigai_genmen_shotoku_{$p}"] ?? null);
            $r6Tokubetsu = $this->n($payload["tax_tokubetsu_R6_shotoku_{$p}"] ?? null);
            $kijunItax = max(0, $baseItax - max(0, $saigaiItax) - max(0, $r6Tokubetsu));

            // 復興特別所得税（2.1%）…UI同様に trunc
            $fukkou = (int) floor($kijunItax * 0.021);
            $gokeiItax = $kijunItax + $fukkou;

            $out["tax_kijun_shotoku_{$p}"]  = $kijunItax;
            $out["tax_fukkou_shotoku_{$p}"] = $fukkou;
            $out["tax_gokei_shotoku_{$p}"]  = $gokeiItax;

            // --- 住民税（支払額：所得割） ---
            // 住民税は「調整控除後所得割（pref/muni）」を基礎に、
            //   配当控除（入力）→ 住宅ローン控除（SoT）→ 寄附金税額控除（天井後SoT）→ 災害減免（入力）
            // の順で“合計額を天井キャップ”し、特例控除按分（jumin_master）で pref/muni に割る。

            $basePref = max(0, $this->n($payload["choseigo_shotokuwari_pref_{$p}"] ?? null));
            $baseMuni = max(0, $this->n($payload["choseigo_shotokuwari_muni_{$p}"] ?? null));
            $baseTotal = $basePref + $baseMuni;

            [$prefShare, $muniShare] = $this->resolveTokureiShares($settings, $payload, $rateRows, $p);

            // 配当控除（入力値を“適用額”として扱う）
            $haito = max(0, $this->n($payload["tax_haito_jumin_{$p}"] ?? null));
            $haitoApplied = min($haito, $baseTotal);
            [$haitoPref, $haitoMuni] = $this->splitByShare($haitoApplied, $prefShare, $muniShare);
            $afterHaitoPref = max(0, $basePref - $haitoPref);
            $afterHaitoMuni = max(0, $baseMuni - $haitoMuni);

            // 住宅ローン控除（サーバSoT：適用額）
            $jutaku = max(0, $this->n($payload["tax_jutaku_jumin_{$p}"] ?? null));
            $afterHaitoTotal = $afterHaitoPref + $afterHaitoMuni;
            $jutakuApplied = min($jutaku, $afterHaitoTotal);
            [$jutakuPref, $jutakuMuni] = $this->splitByShare($jutakuApplied, $prefShare, $muniShare);
            $afterJutakuPref = max(0, $afterHaitoPref - $jutakuPref);
            $afterJutakuMuni = max(0, $afterHaitoMuni - $jutakuMuni);

            // 寄附金税額控除（サーバSoT：天井後の合計）
            $kifukin = max(0, $this->n($payload["kifukin_zeigaku_kojo_gokei_{$p}"] ?? null));
            $afterJutakuTotal = $afterJutakuPref + $afterJutakuMuni;
            $kifukinApplied = min($kifukin, $afterJutakuTotal);
            [$kifukinPref, $kifukinMuni] = $this->splitByShare($kifukinApplied, $prefShare, $muniShare);
            $afterKifukinPref = max(0, $afterJutakuPref - $kifukinPref);
            $afterKifukinMuni = max(0, $afterJutakuMuni - $kifukinMuni);

            // 災害減免（入力：適用額）
            $saigai = max(0, $this->n($payload["tax_saigai_genmen_jumin_{$p}"] ?? null));
            $afterKifukinTotal = $afterKifukinPref + $afterKifukinMuni;
            $saigaiApplied = min($saigai, $afterKifukinTotal);
            [$saigaiPref, $saigaiMuni] = $this->splitByShare($saigaiApplied, $prefShare, $muniShare);
            $finalPref = max(0, $afterKifukinPref - $saigaiPref);
            $finalMuni = max(0, $afterKifukinMuni - $saigaiMuni);

            $finalTotal = $finalPref + $finalMuni;

            // tax_kijun_jumin は UI 上「最終の残税額（所得割）」として扱う
            $out["tax_kijun_jumin_{$p}"] = $finalTotal;
            $out["tax_fukkou_jumin_{$p}"] = 0;
            $out["tax_gokei_jumin_{$p}"] = $finalTotal;
            $out["tax_gokei_jumin_pref_{$p}"] = $finalPref;
            $out["tax_gokei_jumin_muni_{$p}"] = $finalMuni;
        }

        return array_replace($payload, $out);
    }
 
    /**
     * 特例控除の按分比（pref/muni）を master から取得する。
     * - 指定都市/非指定都市の判定は syori_settings の shitei_toshi_flag_{p}
     * - master が無い場合の保険：非指定(0.4/0.6)
     *
     * @param array<int, array<string,mixed>> $rateRows
     * @return array{0:float,1:float}
     */
    private function resolveTokureiShares(array $settings, array $payload, array $rateRows, string $period): array
    {
        $shitei = $this->resolveFlag($settings, $payload, 'shitei_toshi_flag', $period);

        // master（特例控除）から share を引く（0.2/0.8 or 0.4/0.6）
        foreach ($rateRows as $r) {
            if ((string)($r['category'] ?? '') !== '特例控除') continue;
            // 指定都市のとき：pref_specified / city_specified
            if ($shitei) {
                $pref = (float)($r['pref_specified'] ?? 0.2);
                $muni = (float)($r['city_specified'] ?? 0.8);
                return [$this->clampShare($pref), $this->clampShare($muni)];
            }
            // 非指定都市：pref_non_specified / city_non_specified
            $pref = (float)($r['pref_non_specified'] ?? 0.4);
            $muni = (float)($r['city_non_specified'] ?? 0.6);
            return [$this->clampShare($pref), $this->clampShare($muni)];
        }

        // フォールバック
        return [0.4, 0.6];
    }

    private function clampShare(float $v): float
    {
        // 0〜1に丸める（念のため）
        if ($v < 0.0) return 0.0;
        if ($v > 1.0) return 1.0;
        return $v;
    }

    /**
     * 合計額を pref/muni に按分（muni を残り寄せ）
     * @return array{0:int,1:int}
     */
    private function splitByShare(int $total, float $prefShare, float $muniShare): array
    {
        $t = max(0, $total);
        if ($t === 0) return [0, 0];
        // muni 残り寄せ
        $pref = (int) floor($t * $prefShare);
        $muni = $t - $pref;
        return [max(0, $pref), max(0, $muni)];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildJuminRateRows(int $year, ?int $companyId, ?int $dataId): array
    {
        $collection = $this->masterProvider->getJuminRates($year, $companyId, $dataId);
        $rows = [];
        foreach ($collection as $row) {
            $rows[] = [
                'category' => isset($row->category) ? (string)$row->category : '',
                'sub_category' => isset($row->sub_category) && $row->sub_category !== ''
                    ? (string)$row->sub_category : null,
                'remark' => isset($row->remark) && $row->remark !== '' ? (string)$row->remark : null,
                'pref_specified' => isset($row->pref_specified) ? (float)$row->pref_specified : 0.0,
                'pref_non_specified' => isset($row->pref_non_specified) ? (float)$row->pref_non_specified : 0.0,
                'city_specified' => isset($row->city_specified) ? (float)$row->city_specified : 0.0,
                'city_non_specified' => isset($row->city_non_specified) ? (float)$row->city_non_specified : 0.0,
            ];
        }
        return $rows;
    }

    private function resolveFlag(array $settings, array $payload, string $baseKey, string $period): bool
    {
        $keys = [
            sprintf('%s_%s', $baseKey, $period),
            $baseKey,
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                return $this->n($settings[$key]) === 1;
            }
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $this->n($payload[$key]) === 1;
            }
        }
        return false;
    }

    private function n(mixed $v): int
    {
        if ($v === null || $v === '') return 0;
        if (is_string($v)) $v = str_replace([',',' '], '', $v);
        return is_numeric($v) ? (int) floor((float) $v) : 0;
    }
}
