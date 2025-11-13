<?php

namespace App\Services\Tax;

use App\Domain\Tax\Contracts\MasterProviderContract;
use Illuminate\Support\Collection;
use App\Models\FurusatoInput;
use App\Services\Tax\FurusatoMasterDefaults;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class FurusatoMasterService implements MasterProviderContract
{
    private const CACHE_TTL = 300; // seconds

    /**
     * @var array<int, array{label: string, text: string}>
     */
    private const TOKUREI_NOTE_TEMPLATES = [
        80 => [
            'label' => '山林所得の特例',
            'text' => '山林所得がある場合は課税標準額を5で除した金額に対応する控除率を使用します。',
        ],
        90 => [
            'label' => '退職所得の特例',
            'text' => '退職所得がある場合は課税標準額に対応する控除率を使用します。',
        ],
        100 => [
            'label' => '採用控除率',
            'text' => '山林所得と退職所得の双方がある場合は低い控除率を採用します。',
        ],
    ];

    public function getShotokuRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('shotoku', 'shotoku_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'shotoku'])
            ->map(static function (array $rate): array {
                $upper = $rate['upper'];

                return [
                    'lower' => (int) $rate['lower'],
                    'upper' => $upper !== null ? (int) $upper : null,
                    'rate' => (float) $rate['rate'],
                    'deduction_amount' => (int) $rate['deduction_amount'],
                ];
            })
            ->sort(function (array $a, array $b): int {
                $lowerCompare = $a['lower'] <=> $b['lower'];
                
                if ($lowerCompare !== 0) {
                    return $lowerCompare;
                }

                $aUpper = $a['upper'] ?? PHP_INT_MAX;
                $bUpper = $b['upper'] ?? PHP_INT_MAX;

                return $aUpper <=> $bUpper;
            })
            ->values()
            ->map(static fn (array $rate): object => (object) $rate);
    }

    /**
     * 住民税率マスター
     *
     * 優先順位：
     *   1) data_id ごとの FurusatoInput.payload.jumin_master
     *   2) FurusatoMasterDefaults::jumin()（year が一致する行）
     *
     * companyId / 既存 jumin_rates テーブルは本画面では利用しない。
     */
    public function getJuminRates(int $year, ?int $companyId = null, ?int $dataId = null): Collection
    {
        // ★ まずは 2025 Defaults を年で絞り込む
        $defaultsAll = FurusatoMasterDefaults::jumin();
        $defaultRows = array_values(array_filter($defaultsAll, static function (array $row) use ($year): bool {
            return isset($row['year']) && (int) $row['year'] === $year;
        }));

        // もし year 一致が無ければ、全行を採用（フォールバック）
        if ($defaultRows === []) {
            $defaultRows = $defaultsAll;
        }

        // data_id ごとの JSON（jumin_master）があれば「上書き値」として扱う
        $savedBySort = [];

        if ($dataId !== null) {
            $payload = FurusatoInput::query()
                ->where('data_id', $dataId)
                ->value('payload');

            if (is_array($payload) && isset($payload['jumin_master']) && is_array($payload['jumin_master'])) {
                foreach ($payload['jumin_master'] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $sort = (int) ($row['sort'] ?? 0);
                    if ($sort === 0) {
                        continue;
                    }
                    $savedBySort[$sort] = $row;
                }
            }
        }

        // ★ Defaults をベースに「必要なセルだけ JSON の値で上書き」して返す
        $merged = [];

        foreach ($defaultRows as $defaultRow) {
            $sort     = (int)   ($defaultRow['sort']     ?? 0);
            $category = (string)($defaultRow['category'] ?? '');

            $saved = $savedBySort[$sort] ?? null;

            // 調整・基本・特例は常に Defaults のまま（編集不可）
            $useSaved = $saved !== null
                && ! in_array($category, ['調整控除', '基本控除', '特例控除'], true);

            $valueFor = static function (string $key, array $defaultRow, ?array $savedRow) {
                if ($savedRow !== null && array_key_exists($key, $savedRow)) {
                    // 0 も有効値として尊重
                    return (float) $savedRow[$key];
                }
                return (float) ($defaultRow[$key] ?? 0.0);
            };

            $merged[] = (object) [
                'year'               => $year,
                'company_id'         => null,
                'sort'               => $sort,
                'category'           => $category,
                'sub_category'       => $defaultRow['sub_category']       ?? null,
                // ★ 率：JSON に値があればそれを優先／無ければ Defaults
                'city_specified'     => $valueFor('city_specified',     $defaultRow, $useSaved ? $saved : null),
                'pref_specified'     => $valueFor('pref_specified',     $defaultRow, $useSaved ? $saved : null),
                'city_non_specified' => $valueFor('city_non_specified', $defaultRow, $useSaved ? $saved : null),
                'pref_non_specified' => $valueFor('pref_non_specified', $defaultRow, $useSaved ? $saved : null),
                // ★ 備考は常に Defaults のものをそのまま表示（編集不可）
                'remark'             => $defaultRow['remark'] ?? null,
            ];
        }

        return collect($merged)->sortBy('sort')->values();
    }

    public function getTokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('tokurei', 'tokurei_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'tokurei'])
            ->map(function (array $rate): array {
                $lower = $rate['lower'];
                $upper = $rate['upper'];

                return [
                    'sort' => isset($rate['sort']) ? (int) $rate['sort'] : null,
                    'lower' => $lower !== null ? (int) $lower : null,
                    'upper' => $upper !== null ? (int) $upper : null,
                    'income_rate' => (float) $rate['income_rate'],
                    'ninety_minus_rate' => (float) $rate['ninety_minus_rate'],
                    'income_rate_with_recon' => (float) $rate['income_rate_with_recon'],
                    'tokurei_deduction_rate' => (float) $rate['tokurei_deduction_rate'],
                    'note' => array_key_exists('note', $rate) && $rate['note'] !== null ? (string) $rate['note'] : '',
                ];
            })
            ->map(static function (array $rate): object {
                if ($rate['sort'] === null) {
                    unset($rate['sort']);
                }

                return (object) $rate;
            });
    }

    public function getShinkokutokureiRates(int $year, ?int $companyId = null): Collection
    {
        return $this->rememberRates('shinkokutokurei', 'shinkokutokurei_rates', $year, $companyId, [FurusatoMasterDefaults::class, 'shinkokutokurei'])
            ->map(static function (array $rate): array {
                $upper = $rate['upper'];

                return [
                    'lower' => (int) $rate['lower'],
                    'upper' => $upper !== null ? (int) $upper : null,
                    'ratio_a' => (float) $rate['ratio_a'],
                    'ratio_b' => (float) $rate['ratio_b'],
                ];
            })
            ->map(static fn (array $rate): object => (object) $rate);
    }

    private function rememberRates(string $key, string $table, int $year, ?int $companyId, callable $fallback): Collection
    {
        $cacheKey = sprintf('furusato_master:%s:%d:%s', $key, $year, $companyId ?? 'default');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($table, $year, $companyId, $fallback): Collection {
            $rates = $this->fetchRates($table, $year, $companyId);

            if ($rates->isNotEmpty()) {
                return $rates;
            }

            return collect($fallback())->map(static fn ($row): array => (array) $row);
        });
    }

    private function fetchRates(string $table, int $year, ?int $companyId): Collection
    {
        $rows = DB::table($table)
            ->select('*')
            ->selectRaw('COALESCE(year, kifu_year) as effective_year')
            ->whereRaw('COALESCE(year, kifu_year) <= ?', [$year]);

        if ($companyId === null) {
            $rows->whereNull('company_id');
        } else {
            $rows->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        $rows = $rows
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(year, kifu_year) DESC')
            ->orderBy('sort')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $effectiveYear = $rows->first()->effective_year;
        if ($effectiveYear === null) {
            return collect();
        }

        $filtered = $rows->filter(static function ($row) use ($effectiveYear): bool {
            return (int) ($row->effective_year ?? 0) === (int) $effectiveYear;
        });

        $grouped = $filtered
            ->groupBy('sort')
            ->map(function (Collection $group) use ($companyId) {
                return $group->sortBy(function ($row) use ($companyId) {
                    if ($companyId !== null) {
                        if ($row->company_id !== null && (int) $row->company_id === $companyId) {
                            return 0;
                        }

                        if ($row->company_id === null) {
                            return 1;
                        }

                        return 2;
                    }

                    return $row->company_id === null ? 0 : 1;
                })->first();
            })
            ->sortKeys()
            ->values();

        return $grouped->map(static function ($row): array {
            $data = (array) $row;
            unset($data['effective_year']);

            return $data;
        });
    }

    private function buildTokureiNote(array $rate): string
    {
        $sort = isset($rate['sort']) ? (int) $rate['sort'] : null;

        if ($sort === null || ! array_key_exists($sort, self::TOKUREI_NOTE_TEMPLATES)) {
            return '||';
        }

        $template = self::TOKUREI_NOTE_TEMPLATES[$sort];

        return sprintf('%s||%s', $template['label'], $template['text']);
    }
 
    /**
     * 住民税率マスターを年×会社スコープで一括保存（upsert）。
     * rows: [
     *   ['id'?,'sort','category','sub_category', 'city_specified','pref_specified','city_non_specified','pref_non_specified','remark']
     * ]
     */
    public function saveJuminRates(int $year, ?int $companyId, array $rows): void
    {
        $table = 'jumin_rates';
        // 0/空文字はNULLへ、数値は小数3桁に正規化
        $norm = static function ($v): ?float {
            if ($v === null) return null;
            // 全角→半角、カンマ・空白除去
            if (is_string($v)) {
                $v = mb_convert_kana($v, 'as', 'UTF-8'); // 全角記号/数字→半角
                $v = str_replace([',',' '], '', $v);
                $v = trim($v);
            }
            if ($v === '' || $v === '－' || $v === '-') return null;
            if (!is_numeric($v)) return null;
            return round((float)$v, 3);
        };

        DB::transaction(function () use ($rows, $year, $companyId, $table, $norm) {
            foreach ($rows as $row) {
                $category     = trim((string)($row['category'] ?? ''));
                // 表記ゆれ吸収：UI/既定で「総合」「総合課税」の両方があり得る
                if ($category === '総合') {
                    $category = '総合課税';
                }
                $subCategory  = ($row['sub_category'] ?? '') === '' ? null : trim((string)$row['sub_category']);
                $sort         = (int)($row['sort'] ?? 0);

                // 共通マスター（company_id NULL）の“同キー”最新行（<= year）を取得（新規INSERT時の補完に使用）
                $baseRow = DB::table($table)
                    ->whereNull('company_id')
                    ->where('category', $category)
                    ->where(function ($q) use ($subCategory) {
                        if ($subCategory === null) {
                            $q->whereNull('sub_category');
                        } else {
                            $q->where('sub_category', $subCategory);
                        }
                    })
                    ->where('year', '<=', $year)
                    ->orderBy('year', 'desc')
                    ->orderBy('sort')
                    ->first();
                // POST された sort が 0（既定値不明）の場合は、既定行の sort を採用
                if ($sort === 0 && $baseRow) {
                    $sort = (int)$baseRow->sort;
                }
                // まず受信値を正規化
                $incoming = [
                    'year'                => $year,
                    'company_id'          => $companyId,
                    'sort'                => $sort,
                    'category'            => $category,
                    'sub_category'        => $subCategory,
                    'city_specified'      => $norm(Arr::get($row, 'city_specified')),
                    'pref_specified'      => $norm(Arr::get($row, 'pref_specified')),
                    'city_non_specified'  => $norm(Arr::get($row, 'city_non_specified')),
                    'pref_non_specified'  => $norm(Arr::get($row, 'pref_non_specified')),
                    'remark'              => $baseRow->remark ?? null,
                ];
                // 新規行ガード：4率すべて未入力（null）で remark も空 → 何もしない（INSERTしない）
                $allRatesNull =
                    $incoming['city_specified']     === null &&
                    $incoming['pref_specified']     === null &&
                    $incoming['city_non_specified'] === null &&
                    $incoming['pref_non_specified'] === null;

                // 既存行の取得（id優先 → 一意キー近似）
                $existing = null;
                // id一致（年/会社も一致）の場合はそれを更新
                if (!empty($row['id'])) {
                    $hit = DB::table($table)->where('id', (int)$row['id'])->first();
                    if ($hit && (int)$hit->year === (int)$year && (int)($hit->company_id ?? 0) === (int)($companyId ?? 0)) {
                        $existing = $hit;
                    }
                }
                if (!$existing) {
                    $where = [
                        'year'        => $year,
                        'company_id'  => $companyId,
                        'category'    => $category,
                        'sub_category'=> $subCategory,
                        'sort'        => $sort,
                    ];
                    $existing = DB::table($table)->where($where)->first();
                }

                // 「空欄は現状維持」：入力が null の列は、既存値を温存
                $payload = $incoming;
                if ($existing) {
                    foreach (['city_specified','pref_specified','city_non_specified','pref_non_specified','remark'] as $col) {
                        if ($payload[$col] === null) {
                            $payload[$col] = $existing->$col; // keep old
                        }
                    }
                    DB::table($table)->where('id', $existing->id)->update($payload);
                } else {
                    // 新規行：いずれかの率が入力されている場合のみ INSERT
                    if (!$allRatesNull) {
                        // 未入力の列は共通マスターの値で自動補完（NOT NULL 制約を満たす）
                        foreach (['city_specified','pref_specified','city_non_specified','pref_non_specified'] as $col) {
                            if ($payload[$col] === null && $baseRow) {
                                $payload[$col] = (float)$baseRow->$col;
                            }
                        }
                        // それでも null が残る（共通マスターにも該当が無い）場合は挿入をスキップ
                        if (
                            $payload['city_specified']     === null ||
                            $payload['pref_specified']     === null ||
                            $payload['city_non_specified'] === null ||
                            $payload['pref_non_specified'] === null
                        ) {
                            continue; // 不完全な行はINSERTしない
                        }
                        DB::table($table)->insert($payload);
                    }
                    // 全nullならスキップ（INSERTしない）
                }
            }
        });
        // キャッシュを素直にクリア（年×会社）
        Cache::forget(sprintf('furusato_master:jumin:%d:%s', $year, $companyId ?? 'default'));
    }
    
}