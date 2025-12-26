<?php

namespace App\Domain\Tax\Calculators;

use App\Services\Tax\Contracts\ProvidesKeys;

/**
 * 互換用：旧キー（shotokuzei_zeigakukojo_seitoto_tokubetsu_* 等）を
 * 新SoT（SeitotoTokubetsuZeigakuKojoCalculator の tax_credit_shotoku_total_*）から生成する。
 *
 * - “計算”はしない（新SoTの単純ミラー）
 * - これにより、旧UI/旧テスト/旧保存データ参照が残っていても破綻しない
 */
final class SeitotoTokubetsuLegacyMirrorCalculator implements ProvidesKeys
{
    public const ID    = 'credit.seitoto.legacy_mirror';
    // Seitoto（新）後に必ず実行して互換キーを確定
    public const ORDER = 6050;
    public const AFTER = [SeitotoTokubetsuZeigakuKojoCalculator::ID];
    public const BEFORE = [];

    /** @var string[] */
    private const PERIODS = ['prev', 'curr'];

    public static function provides(): array
    {
        return [
            'shotokuzei_zeigakukojo_seitoto_tokubetsu_prev',
            'shotokuzei_zeigakukojo_seitoto_tokubetsu_curr',
            'juminzei_zeigakukojo_seitoto_tokubetsu_prev',
            'juminzei_zeigakukojo_seitoto_tokubetsu_curr',
        ];
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
            // 新SoT：所得税の寄附金税額控除 合計（政党+NPO+公益）
            $total = $this->n($payload["tax_credit_shotoku_total_{$p}"] ?? null);
            $updates["shotokuzei_zeigakukojo_seitoto_tokubetsu_{$p}"] = $total;

            // 住民税側の同名キーは現行ロジックでは未使用（互換として 0 固定）
            $updates["juminzei_zeigakukojo_seitoto_tokubetsu_{$p}"] = 0;
        }

        return array_replace($payload, $updates);
    }

    private function n(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        if (is_string($value)) $value = str_replace([',',' '], '', $value);
        return is_numeric($value) ? (int) floor((float) $value) : 0;
    }
}