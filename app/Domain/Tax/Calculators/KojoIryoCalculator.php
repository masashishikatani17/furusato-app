<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 医療費控除（所得控除）
 *
 * 方針：
 * - SoTはサーバで確定（A/Bのみ入力SoT）
 * - 派生値（ⒸⒺⒻⒼ）もサーバで生成して payload に保持（表示・帳票・集計を一致）
 *
 * 制度：
 *   C = max(A - B, 0)
 *   threshold = min(100,000, floor(max(0,D)*0.05))
 *   G = min( max(C - threshold, 0), 2,000,000 )
 *   ※上限 2,000,000 は「最後」に適用
 *
 * D（総所得金額等）は、可能なら CommonSumsCalculator の sum_for_sogoshotoku_etc_* を優先し、
 * 無い場合は shotoku_gokei_shotoku_* をフォールバックとして用いる。
 */
final class KojoIryoCalculator implements ProvidesKeys
{
    public const ID = 'kojo.iryo';
    // CommonSums 後、KojoAggregation 前で確定させる
    public const ORDER = 3250;
    public const BEFORE = [];
    public const AFTER = [
        CommonSumsCalculator::ID,
    ];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    /**
     * @return array<int, string>
     */
    public static function provides(): array
    {
        $keys = [];
        foreach (self::PERIODS as $p) {
            // A/B（入力SoT）はここでは provides に含めない（入力）
            // 表示/保存用の派生値
            $keys[] = "kojo_iryo_shotoku_gokei_{$p}";
            $keys[] = "kojo_iryo_sashihiki_{$p}";
            $keys[] = "kojo_iryo_shotoku_5pct_{$p}";
            $keys[] = "kojo_iryo_min_threshold_{$p}";
            $keys[] = "kojo_iryo_kojogaku_{$p}";

            // 第一表/集計用（所得税・住民税の医療費控除セルへ同額を入れる）
            $keys[] = "kojo_iryo_shotoku_{$p}";
            $keys[] = "kojo_iryo_jumin_{$p}";
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
        $updates = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            $a = $this->n($payload["kojo_iryo_shiharai_{$p}"] ?? null);
            $b = $this->n($payload["kojo_iryo_hotengaku_{$p}"] ?? null);

            // D: 総所得金額等（優先：sum_for_sogoshotoku_etc_*）
            $d = $this->n($payload["sum_for_sogoshotoku_etc_{$p}"] ?? null);
            if ($d === 0) {
                $d = $this->n($payload["shotoku_gokei_shotoku_{$p}"] ?? null);
            }
            $d = max(0, $d);

            $cRaw = $a - $b;
            // 表示用のⒸも制度どおり 0 下限に揃える
            $c = max(0, $cRaw);
            $updates["kojo_iryo_sashihiki_{$p}"] = $c;
            $updates["kojo_iryo_shotoku_gokei_{$p}"] = $d;

            $e = (int) floor($d * 0.05);      // Ⓔ
            $f = (int) min($e, 100_000);      // Ⓕ
            $gRaw = (int) max(0, $c - $f);    // Ⓒ−Ⓕ（0下限）
            $g = (int) min($gRaw, 2_000_000); // Ⓖ（上限は最後）

            $updates["kojo_iryo_shotoku_5pct_{$p}"] = $e;
            $updates["kojo_iryo_min_threshold_{$p}"] = $f;
            $updates["kojo_iryo_kojogaku_{$p}"] = $g;

            // 第一表/集計向け（所得控除なので所得税・住民税は同額）
            $updates["kojo_iryo_shotoku_{$p}"] = $g;
            $updates["kojo_iryo_jumin_{$p}"] = $g;
        }

        return array_replace($payload, $updates);
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
