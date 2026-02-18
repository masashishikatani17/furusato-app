<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 住宅耐震改修特別控除（所得税）
 * - input の tax_kaisyu_shotoku_{prev,curr} を税額控除として扱う（ユーザー手入力）
 * - 政党等寄付金等特別控除適用後の tax_sashihiki_shotoku_* をベースに控除し、
 *   残税額を tax_after_kaisyu_shotoku_* として出す（後続：寄附金税額控除 等）
 */
final class ItaxKaisyuCreditCalculator implements ProvidesKeys
{
    public const ID    = 'tax.shotoku.kaisyu';
    // 【制度順】政党等（Seitoto）→ 本Calculator →（後段）寄附金税額控除・最終税額
    public const ORDER = 5185;
    public const AFTER  = [SeitotoTokubetsuZeigakuKojoCalculator::ID];
    public const BEFORE = [TaxGokeiCalculator::ID];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        return [
            'tax_kaisyu_applied_shotoku_prev',
            'tax_kaisyu_applied_shotoku_curr',
            'tax_after_kaisyu_shotoku_prev',
            'tax_after_kaisyu_shotoku_curr',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $ctx
     * @return array<string,mixed>
     */
    public function compute(array $payload, array $ctx): array
    {
        unset($ctx);

        $out = array_fill_keys(self::provides(), 0);

        foreach (self::PERIODS as $p) {
            // 政党等適用後の残税額（所得税）
            $baseTax = max(0, (int)($payload["tax_sashihiki_shotoku_{$p}"] ?? 0));

            // ユーザー手入力の耐震改修控除（所得税のみ）
            $kaisyu = (int)($payload["tax_kaisyu_shotoku_{$p}"] ?? 0);
            $kaisyu = max(0, $kaisyu);

            $applied = min($kaisyu, $baseTax);
            $after   = max($baseTax - $applied, 0);

            $out["tax_kaisyu_applied_shotoku_{$p}"] = $applied;
            $out["tax_after_kaisyu_shotoku_{$p}"]   = $after;
        }

        return array_replace($payload, $out);
    }
}
