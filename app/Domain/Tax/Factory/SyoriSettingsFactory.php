<?php

namespace App\Domain\Tax\Factory;

use App\Models\Data;
use App\Models\FurusatoSyoriSetting;

/**
 * 住民税処理設定（syori_settings）の SoT（Single Source of Truth）
 *
 * - デフォルト構造の定義
 * - DB payload とのマージ
 * - 均等割・その他（jumin_master 管理分）の更新
 * - syori_menu 管理分の更新
 */
final class SyoriSettingsFactory
{
    /**
     * jumin_master で管理するキー群
     */
    public const JUMIN_KEYS = [
        'pref_equal_share_prev',
        'pref_equal_share_curr',
        'muni_equal_share_prev',
        'muni_equal_share_curr',
        'other_taxes_amount_prev',
        'other_taxes_amount_curr',
    ];

    /**
     * syori_menu で管理するキー群
     */
    public const SYORI_MENU_KEYS = [
        'detail_mode_prev',
        'detail_mode_curr',
        'bunri_flag_prev',
        'bunri_flag_curr',
        'one_stop_flag_prev',
        'one_stop_flag_curr',
        'shitei_toshi_flag_prev',
        'shitei_toshi_flag_curr',
        'pref_applied_rate_prev',
        'pref_applied_rate_curr',
        'muni_applied_rate_prev',
        'muni_applied_rate_curr',
        // legacy の単数キーは applyStandardRates() 側で面倒を見る
    ];

    /**
     * Data 単位の syori_settings を構築する
     *
     * @return array<string,mixed>
     */
    public function buildInitial(Data $data): array
    {
        $payload = FurusatoSyoriSetting::query()
            ->where('data_id', $data->id)
            ->value('payload');

        $default = $this->defaultPayload();

        if (is_array($payload)) {
            $payload = array_replace($default, array_intersect_key($payload, $default));
        } else {
            $payload = $default;
        }

        return $this->applyStandardRates($payload);
    }

    /**
     * syori_menu 画面用の設定ペイロードを構築する。
     *
     * 現時点では Data 単位の有効 syori_settings（buildInitial）の結果を
     * そのまま返すだけにしておく。
     *
     * 将来的に syori_menu 専用の付加情報（選択肢リストやヘルパ値など）が
     * 必要になった場合は、このメソッド内でラップして拡張する。
     *
     * @return array<string,mixed>
     */
    public function buildMenuPayload(Data $data): array
    {
        return $this->buildInitial($data);
    }

    /**
     * 保存用の設定ペイロードを構築する
     *
     * @param array<string,mixed> $validated
     * @return array<string,mixed>
     */
    public function buildPayloadForSave(array $validated): array
    {
    // validatedからデータIDを取得
        $data = Data::find($validated['data_id']);
        
        // buildInitial() で基本設定を取得
        $settings = $this->buildInitial($data);
        
        // 必要に応じて値を正規化して返却（例えば、百分率を小数に変換するなど）
        return $this->applySaveAdjustments($settings, $validated);
    }

    /**
     * syori_settings を保存する
     *
     * @param array<string,mixed> $settings
     */
    public function save(Data $data, array $settings, ?int $userId): void
    {
        FurusatoSyoriSetting::unguarded(function () use ($data, $settings, $userId): void {
            $record = FurusatoSyoriSetting::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->fill([
                    'data_id'    => $data->id,
                    'company_id' => $data->company_id,
                    'group_id'   => $data->group_id,
                    'created_by' => $userId ?: null,
                ]);
            }

            $record->company_id = $data->company_id;
            $record->group_id   = $data->group_id;
            $record->payload    = $settings;
            $record->updated_by = $userId ?: null;
            $record->save();
        });
    }

    /**
     * 保存用ペイロードに必要な調整を加える（例えば、パーセントから小数に変換）
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $validated
     * @return array<string,mixed>
     */
    private function applySaveAdjustments(array $settings, array $validated): array
    {
        // ▼ syori_menu で管理するキーだけを対象にする
        foreach (self::SYORI_MENU_KEYS as $key) {
            if (!array_key_exists($key, $validated)) {
                // 画面から送られていないキーは既存設定をそのまま利用
                continue;
            }

            $raw = $validated[$key];

            // 数値に寄せておく（カンマ等を除去）
            if (is_string($raw)) {
                $raw = str_replace([',', ' '], '', $raw);
            }

            // ▼ 適用率（百分率で送られてくる想定）が POST されている場合だけ 0〜1 に正規化
            //   現状の画面では rate 入力自体が無いため、ここは基本的にスキップされる。
            if (str_contains($key, 'pref_applied_rate_') || str_contains($key, 'muni_applied_rate_')) {
                if ($raw === null || $raw === '') {
                    // 未入力なら既存値を維持（DEFAULT or DB）
                    continue;
                }
                if (!is_numeric($raw)) {
                    // 数値でなければ既存値を維持（バリデーション側で弾かれている想定）
                    continue;
                }

                // 例：4 → 0.04, 6 → 0.06
                $settings[$key] = max(0.0, min(100.0, (float) $raw)) / 100.0;
                continue;
            }

            // ▼ それ以外（detail_mode / bunri_flag / one_stop_flag / shitei_toshi_flag）は
            //    0/1 フラグとして整数化して上書き
            if ($raw === null || $raw === '') {
                // 空なら既存値をそのまま使う
                continue;
            }

            $settings[$key] = (int) $raw;
        }

        // shitei_toshi_flag などの変更に応じて標準税率や legacy キーを揃える
        return $this->applyStandardRates($settings);
    }

    /**
     * jumin_master からの入力で「均等割・その他」だけを更新する
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $jumin
     * @return array<string,mixed>
     */
    public function applyJuminFromRequest(array $settings, array $jumin): array
    {
        $map = [
            'pref_equal_share_prev' => 'pref_equal_share_prev',
            'pref_equal_share_curr' => 'pref_equal_share_curr',
            'muni_equal_share_prev' => 'muni_equal_share_prev',
            'muni_equal_share_curr' => 'muni_equal_share_curr',
            // view 側 other_taxes_xxx → settings 側 other_taxes_amount_xxx
            'other_taxes_prev'      => 'other_taxes_amount_prev',
            'other_taxes_curr'      => 'other_taxes_amount_curr',
        ];

        foreach ($map as $inputKey => $settingsKey) {
            if (! array_key_exists($inputKey, $jumin)) {
                continue;
            }

            $value = $jumin[$inputKey];
            if (is_string($value)) {
                $value = str_replace(',', '', $value);
            }
            $settings[$settingsKey] = (int) $value;
        }

        return $this->applyStandardRates($settings);
    }

    /**
     * syori_menu の入力で、syori_menu 管理分だけを更新する
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function applySyoriMenuFromRequest(array $settings, array $input): array
    {
        foreach (self::SYORI_MENU_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = $input[$key];
            }
        }

        return $this->applyStandardRates($settings);
    }
    /**
     * syori_settings のデフォルト構造
     *
     * @return array<string,mixed>
     */
    private function defaultPayload(): array
    {
        return [
            'detail_mode_prev' => 1,
            'detail_mode_curr' => 1,
            'bunri_flag_prev' => 0,
            'bunri_flag_curr' => 0,
            'one_stop_flag_prev' => 1,
            'one_stop_flag_curr' => 1,
            'shitei_toshi_flag_prev' => 0,
            'shitei_toshi_flag_curr' => 0,
            'pref_standard_rate' => 0.04,
            'muni_standard_rate' => 0.06,
            'pref_applied_rate_prev' => 0.04,
            'pref_applied_rate_curr' => 0.04,
            'muni_applied_rate_prev' => 0.06,
            'muni_applied_rate_curr' => 0.06,
            'pref_equal_share_prev' => 1500,
            'pref_equal_share_curr' => 1500,
            'muni_equal_share_prev' => 3500,
            'muni_equal_share_curr' => 3500,
            'other_taxes_amount_prev' => 0,
            'other_taxes_amount_curr' => 0,
            // Legacy keys for backward compatibility
            'detail_mode' => 1,
            'bunri_flag' => 0,
            'one_stop_flag' => 1,
            'shitei_toshi_flag' => 0,
            'pref_applied_rate' => 0.04,
            'muni_applied_rate' => 0.06,
            'pref_equal_share' => 1500,
            'muni_equal_share' => 3500,
            'other_taxes_amount' => 0,
        ];
    }

    /**
     * 標準税率・均等割・その他税額などを一貫した形に正規化する
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function applyStandardRates(array $payload): array
    {
        $detailPrev = (int) ($payload['detail_mode_prev'] ?? $payload['detail_mode'] ?? 1);
        $detailCurr = (int) ($payload['detail_mode_curr'] ?? $payload['detail_mode'] ?? $detailPrev);

        $bunriPrev = (int) ($payload['bunri_flag_prev'] ?? $payload['bunri_flag'] ?? 0);
        $bunriCurr = (int) ($payload['bunri_flag_curr'] ?? $payload['bunri_flag'] ?? $bunriPrev);

        $oneStopPrev = (int) ($payload['one_stop_flag_prev'] ?? $payload['one_stop_flag'] ?? 1);
        $oneStopCurr = (int) ($payload['one_stop_flag_curr'] ?? $payload['one_stop_flag'] ?? $oneStopPrev);

        $shiteiPrev = (int) ($payload['shitei_toshi_flag_prev'] ?? $payload['shitei_toshi_flag'] ?? 0);
        $shiteiCurr = (int) ($payload['shitei_toshi_flag_curr'] ?? $payload['shitei_toshi_flag'] ?? $shiteiPrev);
        $shiteiForStandard = $shiteiCurr;

        if ($shiteiForStandard === 1) {
            $prefStandard = 0.02;
            $muniStandard = 0.08;
        } else {
            $prefStandard = 0.04;
            $muniStandard = 0.06;
        }

        $prefAppliedPrev = $payload['pref_applied_rate_prev'] ?? $payload['pref_applied_rate'] ?? null;
        if ($prefAppliedPrev === null) {
            $prefAppliedPrev = $prefStandard;
        }

        $prefAppliedCurr = $payload['pref_applied_rate_curr'] ?? $payload['pref_applied_rate'] ?? null;
        if ($prefAppliedCurr === null) {
            $prefAppliedCurr = $prefAppliedPrev;
        }

        $muniAppliedPrev = $payload['muni_applied_rate_prev'] ?? $payload['muni_applied_rate'] ?? null;
        if ($muniAppliedPrev === null) {
            $muniAppliedPrev = $muniStandard;
        }

        $muniAppliedCurr = $payload['muni_applied_rate_curr'] ?? $payload['muni_applied_rate'] ?? null;
        if ($muniAppliedCurr === null) {
            $muniAppliedCurr = $muniAppliedPrev;
        }

        $prefEqualPrev = (int) ($payload['pref_equal_share_prev'] ?? $payload['pref_equal_share'] ?? 1500);
        $prefEqualCurr = (int) ($payload['pref_equal_share_curr'] ?? $payload['pref_equal_share'] ?? $prefEqualPrev);

        $muniEqualPrev = (int) ($payload['muni_equal_share_prev'] ?? $payload['muni_equal_share'] ?? 3500);
        $muniEqualCurr = (int) ($payload['muni_equal_share_curr'] ?? $payload['muni_equal_share'] ?? $muniEqualPrev);

        $otherTaxesPrev = (int) ($payload['other_taxes_amount_prev'] ?? $payload['other_taxes_amount'] ?? 0);
        $otherTaxesCurr = (int) ($payload['other_taxes_amount_curr'] ?? $payload['other_taxes_amount'] ?? $otherTaxesPrev);

        $payload['pref_standard_rate'] = (float) $prefStandard;
        $payload['muni_standard_rate'] = (float) $muniStandard;

        $payload['detail_mode_prev'] = $detailPrev;
        $payload['detail_mode_curr'] = $detailCurr;
        $payload['detail_mode'] = $detailPrev;

        $payload['bunri_flag_prev'] = $bunriPrev;
        $payload['bunri_flag_curr'] = $bunriCurr;
        $payload['bunri_flag'] = $bunriPrev;

        $payload['one_stop_flag_prev'] = $oneStopPrev;
        $payload['one_stop_flag_curr'] = $oneStopCurr;
        $payload['one_stop_flag'] = $oneStopPrev;

        $payload['shitei_toshi_flag_prev'] = $shiteiPrev;
        $payload['shitei_toshi_flag_curr'] = $shiteiCurr;
        $payload['shitei_toshi_flag'] = $shiteiPrev;

        $payload['pref_applied_rate_prev'] = (float) $prefAppliedPrev;
        $payload['pref_applied_rate_curr'] = (float) $prefAppliedCurr;
        $payload['pref_applied_rate'] = (float) $prefAppliedPrev;

        $payload['muni_applied_rate_prev'] = (float) $muniAppliedPrev;
        $payload['muni_applied_rate_curr'] = (float) $muniAppliedCurr;
        $payload['muni_applied_rate'] = (float) $muniAppliedPrev;

        $payload['pref_equal_share_prev'] = $prefEqualPrev;
        $payload['pref_equal_share_curr'] = $prefEqualCurr;
        $payload['pref_equal_share'] = $prefEqualPrev;

        $payload['muni_equal_share_prev'] = $muniEqualPrev;
        $payload['muni_equal_share_curr'] = $muniEqualCurr;
        $payload['muni_equal_share'] = $muniEqualPrev;

        $payload['other_taxes_amount_prev'] = $otherTaxesPrev;
        $payload['other_taxes_amount_curr'] = $otherTaxesCurr;
        $payload['other_taxes_amount'] = $otherTaxesPrev;

        return $payload;
    }
}