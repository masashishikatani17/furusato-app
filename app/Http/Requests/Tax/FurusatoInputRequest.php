<?php

namespace App\Http\Requests\Tax;

use App\Domain\Tax\DTO\FurusatoInput;
use App\Models\Data;
use App\Models\FurusatoInput as FurusatoInputModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class FurusatoInputRequest extends FormRequest
{
    /**
     * 入力（input.blade.php / details）の整数系は最大11桁に制限する。
     * 例：99,999,999,999（11桁）
     * - カンマは prepareForValidation() で除去される前提
     * - 符号は digits に含まれない（-123 は 3桁扱い）
     */
    private const INPUT_INT_MAX_DIGITS = 11;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * nullable+integer と併用する「桁数制限」ルール
     * - 0 も許容（digits_between は 0 を 1桁として扱う）
     */
    private function intDigitsRule(): string
    {
        return 'digits_between:1,' . self::INPUT_INT_MAX_DIGITS;
    }

    /**
     * バリデーション前に数値系入力を正規化する。
     * - 3桁カンマ付き "1,234" → "1234"
     * - 全角数字 → 半角
     * - 空/ "－"/ "-" → null
     * ※ integer ルールを維持するための前処理
     */
    protected function prepareForValidation(): void
    {
        $all = $this->all();
        if (!is_array($all) || $all === []) {
            return;
        }

        $out = [];
        foreach ($all as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $out[$key] = $this->normalizeNumericLike($key, $value);
        }

        if ($out !== []) {
            $this->merge($out);
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeNumericLike(string $key, mixed $value): mixed
    {
        // 配列は触らない（万一 multi-value が来たら integer で落として検知する）
        if (is_array($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        // 数値そのものはそのまま
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }

        // 文字列以外はそのまま
        if (!is_string($value)) {
            return $value;
        }

        $s = trim($value);
        // 代表的な「入力なし」表現
        if ($s === '' || $s === '－' || $s === '-' || $s === '−' || $s === '―') {
            return null;
        }

        // 「明らかに数値系のキー」だけを正規化対象にする（ラベル等は触らない）
        // 例: syunyu_*, shotoku_*, kojo_*, tax_*, bunri_*, tb_*, 〜_prev/curr など
        $looksNumericKey =
            preg_match('/_(prev|curr)$/', $key) === 1 ||
            preg_match('/^(syunyu|shotoku|kojo|tax|bunri|tb|sum_for|chosei_|kifukin_|kurikoshi_|after_|tsusan|tokubetsu)/', $key) === 1 ||
            preg_match('/^(shotokuzei_|juminzei_)/', $key) === 1;

        if (!$looksNumericKey) {
            return $value;
        }

        // カンマ・空白除去（半角/全角/中華カンマまで）
        $s = str_replace([',', '，', ' ', '　'], '', $s);
        $s = preg_replace('/\s+/u', '', $s) ?? $s;

        // マイナス記号の揺れを統一（全角ハイフン等 → ASCII "-")
        $s = str_replace(['－', '−', '―'], '-', $s);

        // 全角数字→半角数字
        if (function_exists('mb_convert_kana')) {
            $s = mb_convert_kana($s, 'n', 'UTF-8');
        }

        $s = trim($s);
        if ($s === '' || $s === '-' ) {
            return null;
        }

        // 整数として成立するなら整数文字列へ（integer ルールが通る）
        if (preg_match('/^-?\d+$/', $s) === 1) {
            // 桁が巨大でもここでは文字列のまま返す（integer validation/DB側で吸収）
            return $s;
        }

        // 数値っぽいが整数でないもの（例: "0.7"）はそのまま返す（numeric ルールで検知）
        return $value;
    }

    public function rules(): array
    {
        // ▼ ルート名でバリデーション対象を最小化する（A案）
        //    - details/save は画面ごとに POST キーが異なるため、全部入り rules にすると管理が破綻する
        //    - route 名で必要キーのみ validate し、Controller 側は validated から payload を作る
        $routeName = $this->route()?->getName();
        if (is_string($routeName) && $routeName !== '') {
            switch ($routeName) {
                case 'furusato.save':
                    return $this->rulesForInputSave();

                case 'furusato.details.bunri_joto.save':
                    return $this->rulesForDetailsBunriJotoSave();
                case 'furusato.details.bunri_kabuteki.save':
                    return $this->rulesForDetailsBunriKabutekiSave();
                case 'furusato.details.bunri_sakimono.save':
                    return $this->rulesForDetailsBunriSakimonoSave();
                case 'furusato.details.bunri_sanrin.save':
                    return $this->rulesForDetailsBunriSanrinSave();
                case 'furusato.details.fudosan.save':
                    return $this->rulesForDetailsFudosanSave();
                case 'furusato.details.jigyo.save':
                    return $this->rulesForDetailsJigyoEigyoSave();
                case 'furusato.details.joto_ichiji.save':
                    return $this->rulesForDetailsJotoIchijiSave();
                case 'furusato.details.kifukin.save':
                    return $this->rulesForDetailsKifukinSave();
                case 'furusato.details.kojo_iryo.save':
                    return $this->rulesForDetailsKojoIryoSave();
                case 'furusato.details.kojo_jinteki.save':
                    return $this->rulesForDetailsKojoJintekiSave();
                case 'furusato.details.kojo_seimei_jishin.save':
                    return $this->rulesForDetailsKojoSeimeiJishinSave();
                case 'furusato.details.kojo_tokubetsu_jutaku_loan.save':
                    return $this->rulesForDetailsKojoTokubetsuJutakuLoanSave();
                case 'furusato.details.kyuyo_zatsu.save':
                    return $this->rulesForDetailsKyuyoZatsuSave();
            }
        }

        // calc（furusato.calc）など “保存以外” もここを使う
        return $this->buildFullInputRules();
    }

    /**
     * 第一表（input.blade.php）の保存（furusato.save）
     * - input は「全部入りルール」で検証する
     * - 保存なので data_id は必須
     */
    private function rulesForInputSave(): array
    {
        $rules = $this->buildFullInputRules();
        $rules['data_id'] = ['bail', 'required', 'integer', 'min:1'];
        return $rules;
    }

    /**
     * 既存の “全部入り rules” を生成する（switchに依存しない）
     * ※あなたが最初に貼ってくれていた rules() の本体を、そのままここへ移植したもの
     */
    private function buildFullInputRules(): array
    {
        $rules = [
            'data_id' => ['nullable', 'integer', 'min:1'],
        ];

        $incomeFields = [
            'jiryo_eigyo' => '事業所得（営業等）',
            'jiryo_nogyo' => '事業所得（農業）',
            'fudosan' => '不動産所得',
            'haito' => '配当所得',
            'kyuyo' => '給与所得',
            'zatsu_nenkin' => '雑所得（公的年金等）',
            'zatsu_gyomu' => '雑所得（業務）',
            'zatsu_sonota' => '雑所得（その他）',
            'sogo_joto_tanki' => '総合譲渡所得（短期）',
            'sogo_joto_choki' => '総合譲渡所得（長期）',
            'ichiji' => '一時所得',
            'bunri_tanki_ippan' => '分離課税短期譲渡所得金額 一般',
            'bunri_tanki_keigen' => '分離課税短期譲渡所得金額 軽減',
            'bunri_choki_ippan' => '分離課税長期譲渡所得金額 一般',
            'bunri_choki_tokutei' => '分離課税長期譲渡所得金額 特定',
            'bunri_choki_keika' => '分離課税長期譲渡所得金額 軽課',
            'ippan_kabu_joto' => '一般株式等に係る譲渡所得等の金額',
            'jojo_kabu_joto' => '上場株式等に係る譲渡所得の金額',
            'jojo_kabu_haito' => '上場株式等に係る配当所得等の金額',
            'sakimono_zatsu' => '先物取引に係る雑所得等の金額',
            'sanrin' => '山林所得金額',
            'taishoku' => '退職所得金額',
        ];

        $allowNegativeIncomeFields = [
            'ippan_kabu_joto',
            'jojo_kabu_joto',
        ];

        foreach (array_keys($incomeFields) as $field) {
            $baseRule = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            if (! in_array($field, $allowNegativeIncomeFields, true)) {
                $baseRule[] = 'min:0';
            }

            $rules[sprintf('%s_prev', $field)] = $baseRule;
            $rules[sprintf('%s_curr', $field)] = $baseRule;
        }

        $intFields = [
            'shakaihoken_kojo_curr',
            'shokibo_kyosai_kojo_curr',
            'seimei_hoken_kojo_curr',
            'jishin_hoken_kojo_curr',
            'shogaisha_count',
            'tokubetsu_shogaisha_count',
            'dokyo_tokubetsu_shogaisha_count',
            'haigusha_kojo_kingaku',
            'haigusha_tokubetsu_kojo_kingaku',
            'fuyo_ippan_count',
            'fuyo_tokutei_count',
            'fuyo_rojin_count',
            'fuyo_dokyo_rojin_count',
            'tokutei_shinzoku_tokubetsu_count',
            'zasson_kojo_kingaku',
            'iryo_hi_kojo_kingaku',
            'tokutei_kifukin_kingaku',
            'furusato_nozei_kingaku',
            'seitotou_kifukin_kingaku',
            'nintei_npo_kifukin_kingaku',
            'koueki_shadan_kifukin_kingaku',
            'kyobo_nisseki_kifukin_kingaku',
            'jorei_npo_kifukin_kingaku',
            'tokubetsu_zeigaku_kojo_kingaku',
            'gensen_choshu_zeigaku',
        ];

        foreach ($intFields as $field) {
            $rules[$field] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        $flagFields = [
            'kafu_kojo_flag',
            'hitori_oya_kojo_flag',
            'kinro_gakusei_kojo_flag',
            'one_stop_flag',
            'shitei_toshi_flag',
        ];

        foreach ($flagFields as $field) {
            $rules[$field] = ['bail', 'nullable', 'in:0,1'];
        }

        $sheetFieldGroups = [
            'syunyu' => [
                'jigyo_eigyo',
                'jigyo_nogyo',
                'fudosan',
                'haito',
                'kyuyo',
                'zatsu_nenkin',
                'zatsu_gyomu',
                'zatsu_sonota',
                'joto_tanki',
                'joto_choki',
                'ichiji',
            ],
            'shotoku' => [
                'jigyo_eigyo',
                'jigyo_nogyo',
                'fudosan',
                'rishi',
                'haito',
                'kyuyo',
                'zatsu_nenkin',
                'zatsu_gyomu',
                'zatsu_sonota',
                'joto_ichiji',
                'gokei',
            ],
            'kojo' => [
                'shakaihoken',
                'shokibo',
                'seimei',
                'jishin',
                'kafu',
                'hitorioya',
                'kinrogakusei',
                'shogaisha',
                'haigusha',
                'haigusha_tokubetsu',
                'fuyo',
                'tokutei_shinzoku',
                'kiso',
                'shokei',
                'zasson',
                'iryo',
                'kifukin',
                'gokei',
            ],
            'tax' => [
                'kazeishotoku',
                'zeigaku',
                'haito',
                'jutaku',
                'seito',
                'sashihiki',
                'tokubetsu_R6',
                'kijun',
                'fukkou',
                'gokei',
            ],
        ];

        foreach ($sheetFieldGroups as $group => $fields) {
            foreach ($fields as $field) {
                foreach (['shotoku', 'jumin'] as $tax) {
                    foreach (['prev', 'curr'] as $period) {
                        $rules[sprintf('%s_%s_%s_%s', $group, $field, $tax, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
                    }
                }
            }
        }

        // ============================================================
        // ▼ input.blade.php（第一表）でユーザーが直接入力する tax_*（保存対象）
        //   ※ FurusatoController@save() は validated() から updates を作るため、
        //      ここに無いキーは保存されず「入力しても消える」。
        // ============================================================
        foreach (['prev', 'curr'] as $period) {
            // 住宅耐震改修特別控除（所得税のみ入力）
            $rules[sprintf('tax_kaisyu_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // 災害減免額（所得税／住民税）
            $rules[sprintf('tax_saigai_genmen_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('tax_saigai_genmen_jumin_%s',   $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // 令和6年度分特別税額控除（所得税側は条件付で入力、住民税側は画面上ダッシュだが受領しても安全）
            $rules[sprintf('tax_tokubetsu_R6_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('tax_tokubetsu_R6_jumin_%s',   $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('shotokuzei_kojo_kifukin_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('juminzei_kojo_kifukin_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('shotokuzei_kojo_kiso_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('juminzei_kojo_kiso_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        $bunriIncomeParts = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
            'ippan_kabuteki_joto',
            'jojo_kabuteki_joto',
            'jojo_kabuteki_haito',
            'sakimono',
            'sanrin',
            'taishoku',
        ];

        foreach (['syunyu', 'shotoku'] as $category) {
            foreach ($bunriIncomeParts as $part) {
                foreach (['shotoku', 'jumin'] as $tax) {
                    foreach (['prev', 'curr'] as $period) {
                        $rules[sprintf('bunri_%s_%s_%s_%s', $category, $part, $tax, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
                    }
                }
            }
        }

        $bunriBases = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
            'ippan_kabuteki_joto',
            'jojo_kabuteki_joto',
            'jojo_kabuteki_haito',
            'sakimono',
            'sanrin',
            'taishoku',
            'sogo_gokeigaku',
            'sashihiki_gokei',
            'kazeishotoku_sogo',
            'kazeishotoku_tanki',
            'kazeishotoku_choki',
            'kazeishotoku_joto',
            'kazeishotoku_haito',
            'kazeishotoku_sakimono',
            'kazeishotoku_sanrin',
            'kazeishotoku_taishoku',
            'zeigaku_sogo',
            'zeigaku_tanki',
            'zeigaku_choki',
            'zeigaku_joto',
            'zeigaku_haito',
            'zeigaku_sakimono',
            'zeigaku_sanrin',
            'zeigaku_taishoku',
            'zeigaku_gokei',
        ];

        foreach ($bunriBases as $base) {
            foreach (['shotoku', 'jumin'] as $tax) {
                foreach (['prev', 'curr'] as $period) {
                    $rules[sprintf('bunri_%s_%s_%s', $base, $tax, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
                }
            }
        }

        $detailGroups = [
            'jigyo_eigyo' => [
                'uriage',
                'urigenka',
                'sashihiki_1',
                'keihi_1',
                'keihi_2',
                'keihi_3',
                'keihi_4',
                'keihi_5',
                'keihi_6',
                'keihi_7',
                'keihi_sonota',
                'keihi_gokei',
                'sashihiki_2',
                'senjuusha_kyuyo',
                'aoi_tokubetsu_kojo_mae',
                'aoi_tokubetsu_kojo_gaku',
                'shotoku',
            ],
            'fudosan' => [
                'shunyu',
                'keihi_1',
                'keihi_2',
                'keihi_3',
                'keihi_4',
                'keihi_5',
                'keihi_6',
                'keihi_7',
                'keihi_sonota',
                'keihi_gokei',
                'sashihiki',
                'senjuusha_kyuyo',
                'aoi_tokubetsu_kojo_mae',
                'aoi_tokubetsu_kojo_gaku',
                'shotoku',
                'fusairishi',
            ],
        ];

         // details の derived（差引/青色前/所得/合計）は負値があり得るため min:0 を付けない
         $detailDerivedNoMin = [
             // 事業
             'jigyo_eigyo_sashihiki_1',
             'jigyo_eigyo_sashihiki_2',
             'jigyo_eigyo_aoi_tokubetsu_kojo_mae',
             'jigyo_eigyo_shotoku',
             // 不動産
             'fudosan_sashihiki',
             'fudosan_aoi_tokubetsu_kojo_mae',
             'fudosan_shotoku',
         ];

         foreach ($detailGroups as $prefix => $fields) {
             foreach ($fields as $field) {
                 foreach (['prev', 'curr'] as $period) {
                     $key = sprintf('%s_%s_%s', $prefix, $field, $period);
                     $base = sprintf('%s_%s', $prefix, $field);
                     $rule = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
                     if (!in_array($base, $detailDerivedNoMin, true)) {
                         $rule[] = 'min:0';
                     }
                     $rules[$key] = $rule;
                 }
             }
         }

        $kihukinCategories = [
            'furusato',
            'kyodobokin_nisseki',
            'seito',
            'npo',
            'koueki',
            'kuni',
            'sonota',
        ];
        $kihukinFields = [
            'shotokuzei_shotokukojo',
            'shotokuzei_zeigakukojo',
            'juminzei_zeigakukojo',
        ];

        foreach ($kihukinCategories as $category) {
            foreach ($kihukinFields as $field) {
                foreach (['prev', 'curr'] as $period) {
                    $rules[sprintf('%s_%s_%s', $field, $category, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
                }
            }
        }

        // ▼ 人的控除（詳細画面）: 寡婦/ひとり親/勤労学生（UI選択）
        //   - ひとり親控除：父/母/×（旧データ互換で〇も許可）
        //   - 寡婦・勤労学生：〇/×
        foreach (['prev', 'curr'] as $period) {
            $rules["kojo_kafu_applicable_{$period}"] = ['bail', 'nullable', 'in:〇,×'];
            $rules["kojo_kinrogakusei_applicable_{$period}"] = ['bail', 'nullable', 'in:〇,×'];
            $rules["kojo_hitorioya_applicable_{$period}"] = ['bail', 'nullable', 'in:父,母,×,〇'];
        }

        return $rules;
    }

    // ==========================
    // details: 各画面の最小 rules
    // ==========================

    private function rulesForDetailsBunriJotoSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        $rowKeys = [
            'tanki_ippan',
            'tanki_keigen',
            'choki_ippan',
            'choki_tokutei',
            'choki_keika',
        ];
        $editablePrefixes = ['syunyu', 'keihi', 'tokubetsukojo'];

        foreach ($rowKeys as $rowKey) {
            foreach ($editablePrefixes as $prefix) {
                foreach (['prev', 'curr'] as $period) {
                    $rules[sprintf('%s_%s_%s', $prefix, $rowKey, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
                }
            }
        }

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('joto_choki_tokutei_sonshitsu_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        return $rules;
    }

    private function rulesForDetailsBunriKabutekiSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach (['ippan_joto', 'jojo_joto', 'jojo_haito'] as $kind) {
                $rules[sprintf('syunyu_%s_%s', $kind, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
                $rules[sprintf('keihi_%s_%s',  $kind, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
            $rules[sprintf('kurikoshi_jojo_joto_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        return $rules;
    }

    private function rulesForDetailsBunriSakimonoSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            foreach (['syunyu_sakimono', 'keihi_sakimono', 'kurikoshi_sakimono'] as $base) {
                $rules[sprintf('%s_%s', $base, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
        }

        return $rules;
    }

    private function rulesForDetailsBunriSanrinSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('syunyu_sanrin_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('keihi_sanrin_%s',  $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        return $rules;
    }

    private function rulesForDetailsFudosanSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach ([
            'fudosan_keihi_label_01',
            'fudosan_keihi_label_02',
            'fudosan_keihi_label_03',
            'fudosan_keihi_label_04',
            'fudosan_keihi_label_05',
            'fudosan_keihi_label_06',
            'fudosan_keihi_label_07',
        ] as $k) {
            $rules[$k] = ['bail', 'nullable', 'string', 'max:64'];
        }

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('fudosan_syunyu_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            for ($i = 1; $i <= 7; $i++) {
                $rules[sprintf('fudosan_keihi_%d_%s', $i, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
            $rules[sprintf('fudosan_keihi_sonota_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            // ▼ derived（JSでhidden生成されてPOSTされる。古い値の残留を防ぐため保存対象にする）
            //   - 差引/青色前/所得 は負値があり得る（min:0 しない）
            $rules[sprintf('fudosan_keihi_gokei_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('fudosan_sashihiki_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            $rules[sprintf('fudosan_senjuusha_kyuyo_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            $rules[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('fudosan_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            $rules[sprintf('fudosan_fusairishi_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
        }

        return $rules;
    }

    private function rulesForDetailsJigyoEigyoSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach ([
            'jigyo_eigyo_keihi_label_01',
            'jigyo_eigyo_keihi_label_02',
            'jigyo_eigyo_keihi_label_03',
            'jigyo_eigyo_keihi_label_04',
            'jigyo_eigyo_keihi_label_05',
            'jigyo_eigyo_keihi_label_06',
            'jigyo_eigyo_keihi_label_07',
        ] as $k) {
            $rules[$k] = ['bail', 'nullable', 'string', 'max:64'];
        }

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('jigyo_eigyo_uriage_%s',   $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('jigyo_eigyo_urigenka_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            // ▼ derived（JSでhidden生成されてPOSTされる。古い値の残留を防ぐため保存対象にする）
            //   - 差引/青色前/所得 は負値があり得る（min:0 しない）
            $rules[sprintf('jigyo_eigyo_sashihiki_1_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            for ($i = 1; $i <= 7; $i++) {
                $rules[sprintf('jigyo_eigyo_keihi_%d_%s', $i, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
            $rules[sprintf('jigyo_eigyo_keihi_sonota_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('jigyo_eigyo_keihi_gokei_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('jigyo_eigyo_sashihiki_2_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            $rules[sprintf('jigyo_eigyo_senjuusha_kyuyo_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_mae_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            $rules[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('jigyo_eigyo_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
        }

        return $rules;
    }

    private function rulesForDetailsJotoIchijiSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('syunyu_joto_tanki_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('keihi_joto_tanki_%s',  $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('syunyu_joto_choki_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('keihi_joto_choki_%s',  $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            $rules[sprintf('syunyu_ichiji_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('keihi_ichiji_%s',  $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // Calculator入力用（負値もあり得る）
            $rules[sprintf('sashihiki_joto_tanki_sogo_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
            $rules[sprintf('sashihiki_joto_choki_sogo_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule()];
        }

        return $rules;
    }

    private function rulesForDetailsKifukinSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        $categories = ['furusato','kyodobokin_nisseki','seito','npo','koueki','kuni','sonota'];
        $periods = ['prev','curr'];

        foreach ($categories as $cat) {
            foreach ($periods as $period) {
                $rules[sprintf('shotokuzei_shotokukojo_%s_%s', $cat, $period)] = ['bail','nullable','integer','min:0'];
                $rules[sprintf('shotokuzei_zeigakukojo_%s_%s', $cat, $period)] = ['bail','nullable','integer','min:0'];
                $rules[sprintf('juminzei_zeigakukojo_pref_%s_%s', $cat, $period)] = ['bail','nullable','integer','min:0'];
                $rules[sprintf('juminzei_zeigakukojo_muni_%s_%s', $cat, $period)] = ['bail','nullable','integer','min:0'];
            }
        }

        return $rules;
    }

    private function rulesForDetailsKojoIryoSave(): array
    {
        return [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
            'kojo_iryo_shiharai_prev'  => ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'],
            'kojo_iryo_shiharai_curr'  => ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'],
            'kojo_iryo_hotengaku_prev' => ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'],
            'kojo_iryo_hotengaku_curr' => ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'],
        ];
    }

    private function rulesForDetailsKojoJintekiSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev','curr'] as $period) {
            $rules["kojo_kafu_applicable_{$period}"] = ['bail','nullable','in:〇,×'];
            $rules["kojo_kinrogakusei_applicable_{$period}"] = ['bail','nullable','in:〇,×'];
            $rules["kojo_hitorioya_applicable_{$period}"] = ['bail','nullable','in:父,母,×,〇'];
            $rules["kojo_haigusha_category_{$period}"] = ['bail','nullable','in:ippan,roujin,none'];
        }

        $countFields = [
            'kojo_shogaisha_count_%s',
            'kojo_tokubetsu_shogaisha_count_%s',
            'kojo_doukyo_tokubetsu_shogaisha_count_%s',
            'kojo_fuyo_ippan_count_%s',
            'kojo_fuyo_tokutei_count_%s',
            'kojo_fuyo_roujin_doukyo_count_%s',
            'kojo_fuyo_roujin_sonota_count_%s',
        ];
        foreach ($countFields as $fmt) {
            foreach (['prev','curr'] as $period) {
                $rules[sprintf($fmt, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            }
        }

        foreach (['prev','curr'] as $period) {
            $rules["kojo_haigusha_tokubetsu_gokeishotoku_{$period}"] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            $rules["kojo_tokutei_shinzoku_1_shotoku_{$period}"] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            $rules["kojo_tokutei_shinzoku_2_shotoku_{$period}"] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            $rules["kojo_tokutei_shinzoku_3_shotoku_{$period}"] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
        }

        return $rules;
    }

    private function rulesForDetailsKojoSeimeiJishinSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        $keys = [
            'kojo_seimei_shin_%s',
            'kojo_seimei_kyu_%s',
            'kojo_seimei_nenkin_shin_%s',
            'kojo_seimei_nenkin_kyu_%s',
            'kojo_seimei_kaigo_iryo_%s',
            'kojo_jishin_%s',
            'kojo_kyuchoki_songai_%s',
        ];

        foreach (['prev','curr'] as $period) {
            foreach ($keys as $fmt) {
                $rules[sprintf($fmt, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            }
        }
        return $rules;
    }

    private function rulesForDetailsKojoTokubetsuJutakuLoanSave(): array
    {
        return [
            'data_id' => ['bail','required','integer','min:1'],
            'itax_borrow_cap_prev'          => ['bail','nullable','integer','min:0','max:5000000000'],
            'itax_borrow_cap_curr'          => ['bail','nullable','integer','min:0','max:5000000000'],
            'itax_year_end_balance_prev'    => ['bail','nullable','integer','min:0','max:5000000000'],
            'itax_year_end_balance_curr'    => ['bail','nullable','integer','min:0','max:5000000000'],
            'itax_credit_rate_percent_prev' => ['bail','nullable','numeric','min:0','max:100','regex:/^\d{1,2}(\.\d)?$/'],
            'itax_credit_rate_percent_curr' => ['bail','nullable','numeric','min:0','max:100','regex:/^\d{1,2}(\.\d)?$/'],
            'rtax_income_rate_percent_prev' => ['bail','nullable','in:5,7'],
            'rtax_income_rate_percent_curr' => ['bail','nullable','in:5,7'],
            'rtax_carry_cap_prev'           => ['bail','nullable','integer','min:0'],
            'rtax_carry_cap_curr'           => ['bail','nullable','integer','min:0'],
        ];
    }

    private function rulesForDetailsKyuyoZatsuSave(): array
    {
        $rules = [
            'data_id' => ['bail','required','integer','min:1'],
        ];

        $numeric = [
            'kyuyo_syunyu_%s',
            'zatsu_nenkin_syunyu_%s',
            'zatsu_gyomu_syunyu_%s',
            'zatsu_gyomu_shiharai_%s',
            'zatsu_sonota_syunyu_%s',
            'zatsu_sonota_shiharai_%s',
        ];
        foreach (['prev','curr'] as $period) {
            foreach ($numeric as $fmt) {
                $rules[sprintf($fmt, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            }
            $rules[sprintf('kyuyo_chosei_applicable_%s', $period)] = ['bail','nullable','in:0,1'];
        }
        return $rules;
    }

    // ▼ 画面固有の追加バリデーション（Controller の after バリデーション移植先）
    public function withValidator($validator): void
    {
        if (!($validator instanceof Validator)) {
            return;
        }

        $routeName = $this->route()?->getName();
        if ($routeName === 'furusato.details.kojo_jinteki.save') {
            $validator->after(function (Validator $v): void {
                $dataId = (int) ($this->input('data_id') ?? 0);
                if ($dataId <= 0) {
                    return;
                }
                $data = Data::with('guest')->find($dataId);
                if (! $data) {
                    return;
                }

                // CommonSumsCalculator の SoT：sum_for_gokeishotoku_{prev|curr} を参照
                $payload = FurusatoInputModel::query()
                    ->where('data_id', $data->id)
                    ->value('payload');
                $payload = is_array($payload) ? $payload : [];

                $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;

                foreach (['prev', 'curr'] as $period) {
                    $sumKey = sprintf('sum_for_gokeishotoku_%s', $period);
                    $total  = isset($payload[$sumKey]) ? (int) $payload[$sumKey] : 0;

                    $targetYear = null;
                    if ($kihuYear !== null) {
                        $targetYear = ($period === 'prev') ? $kihuYear - 1 : $kihuYear;
                    }

                    // 1) 寡婦控除・ひとり親控除：合計所得金額 5,000,000 超は不可
                    if ($total > 5_000_000) {
                        $kafuKey      = sprintf('kojo_kafu_applicable_%s', $period);
                        $hitorioyaKey = sprintf('kojo_hitorioya_applicable_%s', $period);
                        $kafuVal      = (string) $this->input($kafuKey, '');
                        $hitorioyaVal = (string) $this->input($hitorioyaKey, '');

                        if ($kafuVal === '〇') {
                            $v->errors()->add($kafuKey, '合計所得金額が500万円を超えるため、この年分について寡婦控除は適用できません。');
                        }
                        if (in_array($hitorioyaVal, ['父', '母', '〇'], true)) {
                            $v->errors()->add($hitorioyaKey, '合計所得金額が500万円を超えるため、この年分についてひとり親控除は適用できません。');
                        }
                    }

                    // 2) 勤労学生控除：年次により 75万円/85万円
                    if ($targetYear !== null) {
                        $threshold = ($targetYear >= 2025) ? 850_000 : 750_000;
                        $kinroKey  = sprintf('kojo_kinrogakusei_applicable_%s', $period);
                        $kinroVal  = (string) $this->input($kinroKey, '');
                        if ($kinroVal === '〇' && $total > $threshold) {
                            $man = ($threshold === 850_000) ? '85万円' : '75万円';
                            $v->errors()->add($kinroKey, "合計所得金額が{$man}を超えるため、この年分について勤労学生控除は適用できません。");
                        }
                    }

                    // 3) 配偶者控除／配偶者特別控除（本人：1,000万円以下）
                    $haigushaCategoryKey = sprintf('kojo_haigusha_category_%s', $period);
                    $haigushaCategoryVal = (string) $this->input($haigushaCategoryKey, '');
                    $spouseIncomeKey     = sprintf('kojo_haigusha_tokubetsu_gokeishotoku_%s', $period);
                    $spouseIncomeRaw     = $this->input($spouseIncomeKey);
                    $spouseIncome        = is_numeric(str_replace([',',' '], '', (string)$spouseIncomeRaw))
                        ? (int) str_replace([',',' '], '', (string)$spouseIncomeRaw)
                        : 0;

                    if ($total > 10_000_000) {
                        if ($haigushaCategoryVal !== 'none' && $haigushaCategoryVal !== '') {
                            $v->errors()->add($haigushaCategoryKey, '合計所得金額が1,000万円を超えるため、この年分について配偶者控除は適用できません。');
                        }
                        if ($spouseIncome > 0) {
                            $v->errors()->add($spouseIncomeKey, '合計所得金額が1,000万円を超えるため、この年分について配偶者特別控除は適用できません。');
                        }
                        continue;
                    }

                    if ($targetYear !== null && $spouseIncome > 0) {
                        $startThreshold = ($targetYear >= 2025) ? 580_000 : 480_000;

                        if ($spouseIncome > 1_330_000) {
                            $v->errors()->add($spouseIncomeKey, '配偶者の合計所得金額が133万円を超えているため、この年分について配偶者特別控除は適用できません。');
                        }

                        if ($spouseIncome <= $startThreshold && $haigushaCategoryVal === 'none') {
                            $man = ($startThreshold === 580_000) ? '58' : '48';
                            $v->errors()->add($spouseIncomeKey, "この年分の配偶者の合計所得金額は{$man}万円以下のため、配偶者特別控除ではなく配偶者控除の対象です。");
                        }
                    }
                }
            });
        }
    }

    public function messages(): array
    {
        return [
            'required' => ':attributeは必須です。',
            'integer'  => ':attributeは整数で入力してください。',
            'min'      => ':attributeは:min以上で入力してください。',
            'in'       => ':attributeの選択が不正です。',

            'kojo_hitorioya_applicable_prev.in' => 'ひとり親控除（前年）は「父」「母」「×」から選択してください。',
            'kojo_hitorioya_applicable_curr.in' => 'ひとり親控除（当年）は「父」「母」「×」から選択してください。',
        ];
    }

    public function attributes(): array
    {
        $attributes = [];

        $incomeLabels = [
            'jiryo_eigyo' => '事業所得（営業等）',
            'jiryo_nogyo' => '事業所得（農業）',
            'fudosan' => '不動産所得',
            'haito' => '配当所得',
            'kyuyo' => '給与所得',
            'zatsu_nenkin' => '雑所得（公的年金等）',
            'zatsu_gyomu' => '雑所得（業務）',
            'zatsu_sonota' => '雑所得（その他）',
            'sogo_joto_tanki' => '総合譲渡所得（短期）',
            'sogo_joto_choki' => '総合譲渡所得（長期）',
            'ichiji' => '一時所得',
            'bunri_tanki_ippan' => '分離課税短期譲渡所得金額 一般',
            'bunri_tanki_keigen' => '分離課税短期譲渡所得金額 軽減',
            'bunri_choki_ippan' => '分離課税長期譲渡所得金額 一般',
            'bunri_choki_tokutei' => '分離課税長期譲渡所得金額 特定',
            'bunri_choki_keika' => '分離課税長期譲渡所得金額 軽課',
            'ippan_kabu_joto' => '一般株式等に係る譲渡所得等の金額',
            'jojo_kabu_joto' => '上場株式等に係る譲渡所得の金額',
            'jojo_kabu_haito' => '上場株式等に係る配当所得等の金額',
            'sakimono_zatsu' => '先物取引に係る雑所得等の金額',
            'sanrin' => '山林所得金額',
            'taishoku' => '退職所得金額',
        ];

        foreach ($incomeLabels as $key => $label) {
            $attributes[sprintf('%s_prev', $key)] = sprintf('%s（前期）', $label);
            $attributes[sprintf('%s_curr', $key)] = sprintf('%s（当期）', $label);
        }

        $attributes += [
            'shakaihoken_kojo_curr' => '社会保険料控除',
            'shokibo_kyosai_kojo_curr' => '小規模企業共済等掛金控除',
            'seimei_hoken_kojo_curr' => '生命保険料控除',
            'jishin_hoken_kojo_curr' => '地震保険料控除',
            'kafu_kojo_flag' => '寡婦控除',
            'hitori_oya_kojo_flag' => 'ひとり親控除',
            'kinro_gakusei_kojo_flag' => '勤労学生控除',
            'shogaisha_count' => '障害者控除（障害者）人数',
            'tokubetsu_shogaisha_count' => '障害者控除（特別障害者）人数',
            'dokyo_tokubetsu_shogaisha_count' => '障害者控除（同居特別障害者）人数',
            'haigusha_kojo_kingaku' => '配偶者控除金額',
            'haigusha_tokubetsu_kojo_kingaku' => '配偶者特別控除金額',
            'fuyo_ippan_count' => '扶養控除（一般）人数',
            'fuyo_tokutei_count' => '扶養控除（特定扶養親族）人数',
            'fuyo_rojin_count' => '扶養控除（老人扶養親族）人数',
            'fuyo_dokyo_rojin_count' => '扶養控除（同居老人扶養親族）人数',
            'tokutei_shinzoku_tokubetsu_count' => '特定親族特別控除 対象人数',
            'zasson_kojo_kingaku' => '雑損控除',
            'iryo_hi_kojo_kingaku' => '医療費控除',
            'tokutei_kifukin_kingaku' => '特定寄付金',
            'furusato_nozei_kingaku' => 'ふるさと納税',
            'seitotou_kifukin_kingaku' => '政党等寄付金',
            'nintei_npo_kifukin_kingaku' => '認定NPO法人寄付金',
            'koueki_shadan_kifukin_kingaku' => '公益社団法人等寄付金',
            'kyobo_nisseki_kifukin_kingaku' => '共同募金・日赤',
            'jorei_npo_kifukin_kingaku' => '条例指定NPO',
            'one_stop_flag' => 'ワンストップ適用フラグ',
            'tokubetsu_zeigaku_kojo_kingaku' => '特別税額控除',
            'gensen_choshu_zeigaku' => '源泉徴収税額',
            'shitei_toshi_flag' => '指定都市区分',
            'kojo_kafu_applicable_prev' => '寡婦控除（前年）',
            'kojo_kafu_applicable_curr' => '寡婦控除（当年）',
            'kojo_hitorioya_applicable_prev' => 'ひとり親控除（前年）',
            'kojo_hitorioya_applicable_curr' => 'ひとり親控除（当年）',
            'kojo_kinrogakusei_applicable_prev' => '勤労学生控除（前年）',
            'kojo_kinrogakusei_applicable_curr' => '勤労学生控除（当年）',
        ];

        $kihukinCategoryLabels = [
            'furusato' => '都道府県・市区町村に対する寄付金（ふるさと納税）',
            'kyodobokin_nisseki' => '住所地の共同募金、日赤その他に対する寄付金',
            'seito' => '政党等に対する寄付金',
            'npo' => 'NPO法人等に対する寄付金',
            'koueki' => '公益社団法人等に対する寄付金',
            'kuni' => '国に対する寄付金',
            'sonota' => 'その他の寄付金',
        ];
        $kihukinFieldLabels = [
            'shotokuzei_shotokukojo' => '所得税・所得控除',
            'shotokuzei_zeigakukojo' => '所得税・税額控除',
            'juminzei_zeigakukojo' => '住民税・税額控除',
        ];
        $periodLabels = [
            'prev' => '前年',
            'curr' => '当年',
        ];

        foreach ($kihukinCategoryLabels as $category => $categoryLabel) {
            foreach ($kihukinFieldLabels as $field => $fieldLabel) {
                foreach ($periodLabels as $period => $periodLabel) {
                    $name = sprintf('%s_%s_%s', $field, $category, $period);
                    $attributes[$name] = sprintf('%s（%s・%s）', $categoryLabel, $fieldLabel, $periodLabel);
                }
            }
        }

        return $attributes;
    }

    public function toDto(): FurusatoInput
    {
        $payload = $this->validated();
        unset($payload['data_id']);

        return FurusatoInput::fromArray($payload);
    }
}
