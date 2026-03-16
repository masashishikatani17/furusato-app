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

    /**
     * input.blade.php の「ユーザーが編集できる（readonlyでない）キー」だけを whitelist としてバリデーションする。
     * ※それ以外はサーバ側SoTのため、第一表保存（furusato.save）ではバリデーション対象にしない。
     *
     * - ここに列挙するのは「name属性」そのもの（prev/currは下の生成で展開）
     * - マイナス許容は shotoku_jigyo_nogyo_shotoku_{prev,curr} のみ（-11桁まで）
     */
    private const INPUT_EDITABLE_JUMIN_BASES = [
        'kojo_zasson_jumin_%s',
        'tax_haito_jumin_%s',
        'tax_saigai_genmen_jumin_%s',
        'bunri_syunyu_taishoku_jumin_%s',
        'bunri_shotoku_taishoku_jumin_%s',
    ];

    private const INPUT_EDITABLE_SHOTOKU_BASES = [
        'syunyu_jigyo_nogyo_shotoku_%s',
        'syunyu_haito_shotoku_%s',
        'bunri_syunyu_taishoku_shotoku_%s',
        'shotoku_jigyo_nogyo_shotoku_%s', // ★マイナスOK（-11桁まで）
        'shotoku_rishi_shotoku_%s',
        'shotoku_haito_shotoku_%s',
        'bunri_shotoku_taishoku_shotoku_%s',
        'kojo_shakaihoken_shotoku_%s',
        'kojo_shokibo_shotoku_%s',
        'kojo_zasson_shotoku_%s',
        'tax_haito_shotoku_%s',
        'tax_kaisyu_shotoku_%s',
        'tax_saigai_genmen_shotoku_%s',
        'tax_tokubetsu_R6_shotoku_%s',
    ];

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
     * 3桁（人数）: 0〜999（空欄OK）
     * - integer + min/max で統一（digits_betweenは0を1桁扱いだが、上限999の意図を明確にする）
     */
    private function count3Rule(): array
    {
        return ['bail', 'nullable', 'integer', 'min:0', 'max:999'];
    }

    /** 9桁（円）: 0〜999,999,999（空欄OK） */
    private function money9Rule(): array
    {
        return ['bail', 'nullable', 'integer', 'min:0', 'max:999999999'];
    }

    /**
     * 10桁（0〜9,999,999,999）
     */
    private function int10Rule(): array
    {
        return ['bail', 'nullable', 'integer', 'min:0', 'max:9999999999'];
    }

    /**
     * 符号付き10桁（-9,999,999,999〜9,999,999,999）
     * - "-0" も許容
     */
    private function signedInt10Rule(): array
    {
        return ['bail', 'nullable', 'integer', 'min:-9999999999', 'max:9999999999'];
    }

    /**
     * マイナス許容（-11桁まで）の整数ルール（農業所得：所得金額のみ）
     * - "-0" も許容
     * - カンマは prepareForValidation() で除去済み前提
     */
    private function signedInt11DigitsRule(): string
    {
        return 'regex:/^-?\d{1,' . self::INPUT_INT_MAX_DIGITS . '}$/';
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
        // ============================================================
        // ▼ input.blade.php：ユーザーが編集できる項目のみ validate（whitelist）
        //   - readonly / data-server-lock などサーバSoTは validate しない
        //   - マイナス許容は「事業所得・農業（所得金額）」のみ（-11桁まで）
        // ============================================================
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            // 住民税側（編集可）
            foreach (self::INPUT_EDITABLE_JUMIN_BASES as $fmt) {
                $key = sprintf($fmt, $period);
                $rules[$key] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }

            // 所得税側（編集可）
            foreach (self::INPUT_EDITABLE_SHOTOKU_BASES as $fmt) {
                $key = sprintf($fmt, $period);

                // ★農業所得（所得金額）のみ：マイナスOK（-11桁まで）
                if ($key === sprintf('shotoku_jigyo_nogyo_shotoku_%s', $period)) {
                    $rules[$key] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
                    continue;
                }

                // それ以外：マイナス禁止（0以上）＋11桁
                $rules[$key] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
        }

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
 
        // ============================================================
        // ▼ readonly（display-only）だが hidden で POST される派生値もバリデーション対象に含める
        //   - 差引金額 / 損益通算後 / 譲渡所得金額 / 課税所得金額（区分合計）
        //   - 許容：-11桁〜11桁（-0 OK）
        // ============================================================
        foreach ($rowKeys as $rowKey) {
            foreach (['prev', 'curr'] as $period) {
                $rules[sprintf('before_tsusan_%s_%s', $rowKey, $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
                $rules[sprintf('tsusango_%s_%s', $rowKey, $period)]      = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
                $rules[sprintf('joto_shotoku_%s_%s', $rowKey, $period)]  = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
            }
        }
        foreach (['prev', 'curr'] as $period) {
            $rules[sprintf('joto_shotoku_tanki_gokei_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
            $rules[sprintf('joto_shotoku_choki_gokei_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
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

                // ▼ derived（readonlyだが hidden でPOSTされるため、バリデーション対象に含める）
                //   - 所得金額（収入−経費）
                //   - 損益通算後（サーバ計算）
                //   - 繰越控除後の所得金額（サーバ計算）
                //   許容：-11桁〜11桁（-0 OK）
                $rules[sprintf('shotoku_%s_%s', $kind, $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
                $rules[sprintf('tsusango_%s_%s', $kind, $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
                $rules[sprintf('shotoku_after_kurikoshi_%s_%s', $kind, $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
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

            // ▼ derived（readonlyだが hidden でPOSTされるため、バリデーション対象に含める）
            //   - 所得金額（収入−経費）
            //   - 繰越控除後の所得金額（所得−繰越損失）
            //   許容：-11桁〜11桁（-0 OK）
            $rules[sprintf('shotoku_sakimono_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
            $rules[sprintf('shotoku_sakimono_after_kurikoshi_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
        }

        return $rules;
    }

    private function rulesForDetailsBunriSanrinSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            // ▼ ユーザー入力：0以上・11桁
            $rules[sprintf('syunyu_sanrin_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('keihi_sanrin_%s',  $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（readonlyだが hidden でPOSTされるため、バリデーション対象に含める）
            //   許容：-11桁〜11桁（-0 OK）
            $rules[sprintf('sashihiki_sanrin_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];

            // ▼ derived（特別控除）：0以上・11桁（最大50万だが統一）
            $rules[sprintf('tokubetsukojo_sanrin_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（山林所得金額）：-11桁〜11桁（-0 OK）
            $rules[sprintf('shotoku_sanrin_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
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
            $rules[$k] = ['bail', 'nullable', 'string', 'max:10'];
        }

        foreach (['prev', 'curr'] as $period) {
            // ▼ ユーザー入力（0以上・11桁）
            $rules[sprintf('fudosan_syunyu_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            for ($i = 1; $i <= 7; $i++) {
                $rules[sprintf('fudosan_keihi_%d_%s', $i, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
            $rules[sprintf('fudosan_keihi_sonota_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（readonlyだが hidden でPOSTされるため、バリデーション対象に含める）
            //   - 許容：-11桁〜11桁（-0 OK）
            $rules[sprintf('fudosan_keihi_gokei_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
            $rules[sprintf('fudosan_sashihiki_%s',   $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];

            // ▼ ユーザー入力（0以上・11桁）
            $rules[sprintf('fudosan_senjuusha_kyuyo_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（青色前/所得）：-11桁〜11桁 OK
            $rules[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];

            // ▼ ユーザー入力（0以上・11桁）
            $rules[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（所得金額）：-11桁〜11桁 OK
            $rules[sprintf('fudosan_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];

            // ▼ ユーザー入力（0以上・11桁）
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
            $rules[$k] = ['bail', 'nullable', 'string', 'max:10'];
        }

        foreach (['prev', 'curr'] as $period) {
            // ▼ ユーザー入力（0以上・11桁）
            $rules[sprintf('jigyo_eigyo_uriage_%s',   $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('jigyo_eigyo_urigenka_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（readonlyだが hidden でPOSTされるため、バリデーション対象に含める）
            //   - 許容：-11桁〜11桁（-0 OK）
            $rules[sprintf('jigyo_eigyo_sashihiki_1_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
            for ($i = 1; $i <= 7; $i++) {
                $rules[sprintf('jigyo_eigyo_keihi_%d_%s', $i, $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];
            }
            $rules[sprintf('jigyo_eigyo_keihi_sonota_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（経費合計・差引2）：-11桁〜11桁 OK
            $rules[sprintf('jigyo_eigyo_keihi_gokei_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
            $rules[sprintf('jigyo_eigyo_sashihiki_2_%s', $period)]   = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];

            // ▼ ユーザー入力（0以上・11桁）
            $rules[sprintf('jigyo_eigyo_senjuusha_kyuyo_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（青色前）：-11桁〜11桁 OK
            $rules[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_mae_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];

            // ▼ ユーザー入力（0以上・11桁）
            $rules[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_%s', $period)] = ['bail', 'nullable', 'integer', $this->intDigitsRule(), 'min:0'];

            // ▼ derived（所得金額）：-11桁〜11桁 OK
            $rules[sprintf('jigyo_eigyo_shotoku_%s', $period)] = ['bail', 'nullable', 'integer', $this->signedInt11DigitsRule()];
        }

        return $rules;
    }

    private function rulesForDetailsJotoIchijiSave(): array
    {
        $rules = [
            'data_id' => ['bail', 'required', 'integer', 'min:1'],
        ];

        foreach (['prev', 'curr'] as $period) {
            // ▼ ユーザー入力（収入/経費）：10桁・min0
            $rules[sprintf('syunyu_joto_tanki_%s', $period)] = $this->int10Rule();
            $rules[sprintf('keihi_joto_tanki_%s',  $period)] = $this->int10Rule();
            $rules[sprintf('syunyu_joto_choki_%s', $period)] = $this->int10Rule();
            $rules[sprintf('keihi_joto_choki_%s',  $period)] = $this->int10Rule();
            $rules[sprintf('syunyu_ichiji_%s',      $period)] = $this->int10Rule();
            $rules[sprintf('keihi_ichiji_%s',       $period)] = $this->int10Rule();

            // ▼ 派生（この画面は readonly でも hidden でPOSTされる前提）：符号付き10桁
            $rules[sprintf('sashihiki_joto_tanki_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('sashihiki_joto_choki_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('sashihiki_ichiji_%s',      $period)] = $this->signedInt10Rule();

            $rules[sprintf('after_naibutsusan_joto_tanki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('after_naibutsusan_joto_choki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('after_naibutsusan_ichiji_%s',          $period)] = $this->signedInt10Rule();

            $rules[sprintf('tokubetsukojo_joto_tanki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('tokubetsukojo_joto_choki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('tokubetsukojo_ichiji_%s',           $period)] = $this->signedInt10Rule();

            $rules[sprintf('after_joto_ichiji_tousan_joto_tanki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('after_joto_ichiji_tousan_joto_choki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('after_joto_ichiji_tousan_ichiji_%s',          $period)] = $this->signedInt10Rule();

            $rules[sprintf('after_3jitsusan_joto_tanki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('after_3jitsusan_joto_choki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('after_3jitsusan_ichiji_%s',          $period)] = $this->signedInt10Rule();

            $rules[sprintf('half_joto_choki_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('half_ichiji_%s',     $period)] = $this->signedInt10Rule();

            $rules[sprintf('shotoku_joto_tanki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('shotoku_joto_choki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('shotoku_ichiji_%s',          $period)] = $this->signedInt10Rule();

            // ▼ Calculator入力用（必ずPOSTされる hidden）：符号付き10桁
            $rules[sprintf('sashihiki_joto_tanki_sogo_%s', $period)] = $this->signedInt10Rule();
            $rules[sprintf('sashihiki_joto_choki_sogo_%s', $period)] = $this->signedInt10Rule();
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
                // すべて：0以上・11桁（空欄OK）
                $rules[sprintf('shotokuzei_shotokukojo_%s_%s', $cat, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
                $rules[sprintf('shotokuzei_zeigakukojo_%s_%s', $cat, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
                $rules[sprintf('juminzei_zeigakukojo_pref_%s_%s', $cat, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
                $rules[sprintf('juminzei_zeigakukojo_muni_%s_%s', $cat, $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
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

        // 人数：3桁（0〜999）
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
                $rules[sprintf($fmt, $period)] = $this->count3Rule();
            }
        }

        foreach (['prev','curr'] as $period) {
            // 金額：9桁（0〜999,999,999）
            $rules["kojo_haigusha_tokubetsu_gokeishotoku_{$period}"] = $this->money9Rule();
            $rules["kojo_tokutei_shinzoku_1_shotoku_{$period}"] = $this->money9Rule();
            $rules["kojo_tokutei_shinzoku_2_shotoku_{$period}"] = $this->money9Rule();
            $rules["kojo_tokutei_shinzoku_3_shotoku_{$period}"] = $this->money9Rule();
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
            // ▼ 合計（readonlyだが hidden でPOSTされるためバリデーション対象に含める）
            //   - いずれも 0以上・11桁
            $rules[sprintf('kojo_seimei_gokei_%s', $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
            $rules[sprintf('kojo_jishin_gokei_%s', $period)] = ['bail','nullable','integer', $this->intDigitsRule(), 'min:0'];
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
            // digits_between の英語デフォルトを出さない（11桁制限は全てこの文言に統一）
            'digits_between' => ':attributeは11桁までの整数を入力してください。',

            // 10桁（収入/経費）・符号付き10桁（派生値）の説明を明確化
            'syunyu_joto_tanki_prev.max' => ':attributeは10桁までの整数を入力してください。',
            'syunyu_joto_tanki_curr.max' => ':attributeは10桁までの整数を入力してください。',
            'keihi_joto_tanki_prev.max'  => ':attributeは10桁までの整数を入力してください。',
            'keihi_joto_tanki_curr.max'  => ':attributeは10桁までの整数を入力してください。',
            'syunyu_joto_choki_prev.max' => ':attributeは10桁までの整数を入力してください。',
            'syunyu_joto_choki_curr.max' => ':attributeは10桁までの整数を入力してください。',
            'keihi_joto_choki_prev.max'  => ':attributeは10桁までの整数を入力してください。',
            'keihi_joto_choki_curr.max'  => ':attributeは10桁までの整数を入力してください。',
            'syunyu_ichiji_prev.max'      => ':attributeは10桁までの整数を入力してください。',
            'syunyu_ichiji_curr.max'      => ':attributeは10桁までの整数を入力してください。',
            'keihi_ichiji_prev.max'       => ':attributeは10桁までの整数を入力してください。',
            'keihi_ichiji_curr.max'       => ':attributeは10桁までの整数を入力してください。',
            // 符号付き10桁（-9,999,999,999〜9,999,999,999）
            'sashihiki_joto_tanki_sogo_prev.min' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_tanki_sogo_curr.min' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_choki_sogo_prev.min' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_choki_sogo_curr.min' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_tanki_sogo_prev.max' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_tanki_sogo_curr.max' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_choki_sogo_prev.max' => ':attributeは-10桁までの整数を入力してください。',
            'sashihiki_joto_choki_sogo_curr.max' => ':attributeは-10桁までの整数を入力してください。',

            // 人的控除（詳細）：人数は3桁、金額は9桁
            'max' => ':attributeは:max以下で入力してください。',
            // 3桁人数（999）
            'kojo_shogaisha_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_shogaisha_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_tokubetsu_shogaisha_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_tokubetsu_shogaisha_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_doukyo_tokubetsu_shogaisha_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_doukyo_tokubetsu_shogaisha_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_ippan_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_ippan_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_tokutei_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_tokutei_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_roujin_doukyo_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_roujin_doukyo_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_roujin_sonota_count_prev.max' => ':attributeは3桁までの整数を入力してください。',
            'kojo_fuyo_roujin_sonota_count_curr.max' => ':attributeは3桁までの整数を入力してください。',
            // 9桁金額（999,999,999）
            'kojo_haigusha_tokubetsu_gokeishotoku_prev.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_haigusha_tokubetsu_gokeishotoku_curr.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_tokutei_shinzoku_1_shotoku_prev.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_tokutei_shinzoku_1_shotoku_curr.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_tokutei_shinzoku_2_shotoku_prev.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_tokutei_shinzoku_2_shotoku_curr.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_tokutei_shinzoku_3_shotoku_prev.max' => ':attributeは9桁までの整数を入力してください。',
            'kojo_tokutei_shinzoku_3_shotoku_curr.max' => ':attributeは9桁までの整数を入力してください。',

            // 農業所得（所得金額：マイナスOK）は「-11桁まで」を明示
            'shotoku_jigyo_nogyo_shotoku_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'shotoku_jigyo_nogyo_shotoku_curr.regex' => ':attributeは-11桁までの整数を入力してください。',

            // details: 事業・営業等（derivedの符号付き11桁）
            'jigyo_eigyo_sashihiki_1_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_sashihiki_1_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_keihi_gokei_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_keihi_gokei_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_sashihiki_2_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_sashihiki_2_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_shotoku_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'jigyo_eigyo_shotoku_curr.regex' => ':attributeは-11桁までの整数を入力してください。',

            // details: 不動産（derivedの符号付き11桁）
            'fudosan_keihi_gokei_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_keihi_gokei_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_sashihiki_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_sashihiki_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_aoi_tokubetsu_kojo_mae_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_aoi_tokubetsu_kojo_mae_curr.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_shotoku_prev.regex' => ':attributeは-11桁までの整数を入力してください。',
            'fudosan_shotoku_curr.regex' => ':attributeは-11桁までの整数を入力してください。',

            // details: 分離課税 譲渡所得（派生値：符号付き11桁）
            // ※ワイルドカードで網羅（英語デフォルト防止）
            'before_tsusan_*.regex' => ':attributeは-11桁までの整数を入力してください。',
            'tsusango_*.regex'      => ':attributeは-11桁までの整数を入力してください。',
            'joto_shotoku_*.regex'  => ':attributeは-11桁までの整数を入力してください。',
            // details: 分離課税 株式等（派生値：符号付き11桁）
            'shotoku_*.regex'              => ':attributeは-11桁までの整数を入力してください。',
            'shotoku_after_kurikoshi_*.regex' => ':attributeは-11桁までの整数を入力してください。',

            // details: 派生値（差引）系（英語デフォルト防止）
            'sashihiki_*.regex' => ':attributeは-11桁までの整数を入力してください。',

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

        // details: 事業・営業等（表示名）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('jigyo_eigyo_uriage_%s', $p)] = sprintf('売上(収入)金額（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_urigenka_%s', $p)] = sprintf('売上原価（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_sashihiki_1_%s', $p)] = sprintf('差引金額（%s）', $pLabel);
            for ($i = 1; $i <= 7; $i++) {
                $attributes[sprintf('jigyo_eigyo_keihi_%d_%s', $i, $p)] = sprintf('経費%d（%s）', $i, $pLabel);
            }
            $attributes[sprintf('jigyo_eigyo_keihi_sonota_%s', $p)] = sprintf('経費（その他）（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_keihi_gokei_%s', $p)] = sprintf('経費合計（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_sashihiki_2_%s', $p)] = sprintf('差引金額（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_senjuusha_kyuyo_%s', $p)] = sprintf('専従者給与（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_mae_%s', $p)] = sprintf('青色申告特別控除前の所得金額（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_%s', $p)] = sprintf('青色申告特別控除額（%s）', $pLabel);
            $attributes[sprintf('jigyo_eigyo_shotoku_%s', $p)] = sprintf('所得金額（%s）', $pLabel);
        }
        foreach ([1,2,3,4,5,6,7] as $i) {
            $k = sprintf('jigyo_eigyo_keihi_label_%02d', $i);
            $attributes[$k] = sprintf('経費項目名%d', $i);
        }

        // details: 不動産（表示名）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('fudosan_syunyu_%s', $p)] = sprintf('収入金額（%s）', $pLabel);
            for ($i = 1; $i <= 7; $i++) {
                $attributes[sprintf('fudosan_keihi_%d_%s', $i, $p)] = sprintf('必要経費%d（%s）', $i, $pLabel);
            }
            $attributes[sprintf('fudosan_keihi_sonota_%s', $p)] = sprintf('必要経費（その他）（%s）', $pLabel);
            $attributes[sprintf('fudosan_keihi_gokei_%s', $p)] = sprintf('必要経費 合計（%s）', $pLabel);
            $attributes[sprintf('fudosan_sashihiki_%s', $p)] = sprintf('差引金額（%s）', $pLabel);
            $attributes[sprintf('fudosan_senjuusha_kyuyo_%s', $p)] = sprintf('専従者給与（%s）', $pLabel);
            $attributes[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $p)] = sprintf('青色申告特別控除前の所得金額（%s）', $pLabel);
            $attributes[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $p)] = sprintf('青色申告特別控除額（%s）', $pLabel);
            $attributes[sprintf('fudosan_shotoku_%s', $p)] = sprintf('所得金額（%s）', $pLabel);
            $attributes[sprintf('fudosan_fusairishi_%s', $p)] = sprintf('土地等を取得するための負債利子（%s）', $pLabel);
        }
        foreach ([1,2,3,4,5,6,7] as $i) {
            $k = sprintf('fudosan_keihi_label_%02d', $i);
            $attributes[$k] = sprintf('必要経費項目名%d', $i);
        }

        // details: 給与・雑所得（表示名）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('kyuyo_syunyu_%s', $p)] = sprintf('給与収入金額（%s）', $pLabel);
            $attributes[sprintf('kyuyo_chosei_applicable_%s', $p)] = sprintf('所得金額調整控除の適用（%s）', $pLabel);

            $attributes[sprintf('zatsu_nenkin_syunyu_%s', $p)] = sprintf('公的年金等収入金額（%s）', $pLabel);
            $attributes[sprintf('zatsu_gyomu_syunyu_%s', $p)] = sprintf('雑所得（業務）収入金額（%s）', $pLabel);
            $attributes[sprintf('zatsu_gyomu_shiharai_%s', $p)] = sprintf('雑所得（業務）支払金額（%s）', $pLabel);
            $attributes[sprintf('zatsu_sonota_syunyu_%s', $p)] = sprintf('雑所得（その他）収入金額（%s）', $pLabel);
            $attributes[sprintf('zatsu_sonota_shiharai_%s', $p)] = sprintf('雑所得（その他）支払金額（%s）', $pLabel);
        }

        // details: 分離課税 譲渡所得（短期/長期）内訳（表示名）
        $bunriRowLabels = [
            'tanki_ippan'   => '短期譲渡（一般分）',
            'tanki_keigen'  => '短期譲渡（軽減分）',
            'choki_ippan'   => '長期譲渡（一般分）',
            'choki_tokutei' => '長期譲渡（特定分）',
            'choki_keika'   => '長期譲渡（軽課分）',
        ];
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            foreach ($bunriRowLabels as $rowKey => $rowLabel) {
                $attributes[sprintf('syunyu_%s_%s', $rowKey, $p)] = sprintf('%s 収入金額（%s）', $rowLabel, $pLabel);
                $attributes[sprintf('keihi_%s_%s', $rowKey, $p)] = sprintf('%s 必要経費（%s）', $rowLabel, $pLabel);
                $attributes[sprintf('tokubetsukojo_%s_%s', $rowKey, $p)] = sprintf('%s 特別控除額（%s）', $rowLabel, $pLabel);

                // derived（readonlyだがPOSTされる）
                $attributes[sprintf('before_tsusan_%s_%s', $rowKey, $p)] = sprintf('%s 差引金額（%s）', $rowLabel, $pLabel);
                $attributes[sprintf('tsusango_%s_%s', $rowKey, $p)]      = sprintf('%s 損益通算後（%s）', $rowLabel, $pLabel);
                $attributes[sprintf('joto_shotoku_%s_%s', $rowKey, $p)]  = sprintf('%s 譲渡所得金額（%s）', $rowLabel, $pLabel);
            }
            // 区分合計（課税所得金額：行rowspan）
            $attributes[sprintf('joto_shotoku_tanki_gokei_%s', $p)] = sprintf('短期譲渡 合計（%s）', $pLabel);
            $attributes[sprintf('joto_shotoku_choki_gokei_%s', $p)] = sprintf('長期譲渡 合計（%s）', $pLabel);
        }

        // details: 分離課税 株式等の譲渡所得等 内訳（表示名）
        $kabutekiLabels = [
            'ippan_joto' => '一般株式等の譲渡',
            'jojo_joto'  => '上場株式等の譲渡',
            'jojo_haito' => '上場株式等の配当等',
        ];
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            foreach ($kabutekiLabels as $k => $lbl) {
                $attributes[sprintf('syunyu_%s_%s', $k, $p)] = sprintf('%s 収入金額（%s）', $lbl, $pLabel);
                $attributes[sprintf('keihi_%s_%s',  $k, $p)] = sprintf('%s 必要経費（%s）', $lbl, $pLabel);
                $attributes[sprintf('shotoku_%s_%s', $k, $p)] = sprintf('%s 所得金額（%s）', $lbl, $pLabel);
                $attributes[sprintf('tsusango_%s_%s', $k, $p)] = sprintf('%s 損益通算後（%s）', $lbl, $pLabel);
                $attributes[sprintf('shotoku_after_kurikoshi_%s_%s', $k, $p)] = sprintf('%s 繰越控除後の所得金額（%s）', $lbl, $pLabel);
            }
            $attributes[sprintf('kurikoshi_jojo_joto_%s', $p)] = sprintf('繰越損失の金額（上場株式等の譲渡）（%s）', $pLabel);
        }

        // details: 分離課税 先物取引 内訳（表示名）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('syunyu_sakimono_%s', $p)] = sprintf('先物取引 収入金額（%s）', $pLabel);
            $attributes[sprintf('keihi_sakimono_%s', $p)] = sprintf('先物取引 必要経費（%s）', $pLabel);
            $attributes[sprintf('shotoku_sakimono_%s', $p)] = sprintf('先物取引 所得金額（%s）', $pLabel);
            $attributes[sprintf('kurikoshi_sakimono_%s', $p)] = sprintf('先物取引 繰越損失の金額（%s）', $pLabel);
            $attributes[sprintf('shotoku_sakimono_after_kurikoshi_%s', $p)] = sprintf('先物取引 繰越控除後の所得金額（%s）', $pLabel);
        }

        // details: 分離課税 山林所得 内訳（表示名）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('syunyu_sanrin_%s', $p)] = sprintf('山林所得 収入金額（%s）', $pLabel);
            $attributes[sprintf('keihi_sanrin_%s',  $p)] = sprintf('山林所得 必要経費（%s）', $pLabel);
            $attributes[sprintf('sashihiki_sanrin_%s', $p)] = sprintf('山林所得 差引金額（%s）', $pLabel);
            $attributes[sprintf('tokubetsukojo_sanrin_%s', $p)] = sprintf('山林所得 特別控除額（%s）', $pLabel);
            $attributes[sprintf('shotoku_sanrin_%s', $p)] = sprintf('山林所得金額（%s）', $pLabel);
        }

        // details: 生命保険料・地震保険料（表示名）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('kojo_seimei_shin_%s', $p)] = sprintf('新生命保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_seimei_kyu_%s', $p)] = sprintf('旧生命保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_seimei_nenkin_shin_%s', $p)] = sprintf('新個人年金保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_seimei_nenkin_kyu_%s', $p)] = sprintf('旧個人年金保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_seimei_kaigo_iryo_%s', $p)] = sprintf('介護医療保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_seimei_gokei_%s', $p)] = sprintf('生命保険料 合計（%s）', $pLabel);
            $attributes[sprintf('kojo_jishin_%s', $p)] = sprintf('地震保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_kyuchoki_songai_%s', $p)] = sprintf('旧長期損害保険料（%s）', $pLabel);
            $attributes[sprintf('kojo_jishin_gokei_%s', $p)] = sprintf('地震保険料 合計（%s）', $pLabel);
        }

        // details: 医療費控除（表示名：入力項目のみ）
        foreach (['prev' => '前年', 'curr' => '当年'] as $p => $pLabel) {
            $attributes[sprintf('kojo_iryo_shiharai_%s', $p)] = sprintf('支払った医療費（%s）', $pLabel);
            $attributes[sprintf('kojo_iryo_hotengaku_%s', $p)] = sprintf('保険金などで補填される金額（%s）', $pLabel);
        }

        // ============================================================
        // ▼ input.blade.php：編集可能（readonlyでない）キーの表示名
        //   ※whitelist のバリデーションメッセージが field名のまま出るのを防ぐ
        // ============================================================
        $labelByBase = [
            // 住民税側
            'kojo_zasson_jumin_%s' => '雑損控除（住民税）',
            'tax_haito_jumin_%s' => '配当控除（住民税）',
            'tax_saigai_genmen_jumin_%s' => '災害減免額（住民税）',
            // 所得税側
            'syunyu_jigyo_nogyo_shotoku_%s' => '事業所得（農業）収入金額（所得税）',
            'syunyu_haito_shotoku_%s' => '配当所得 収入金額（所得税）',
            'bunri_syunyu_taishoku_shotoku_%s' => '退職（分離）収入金額（所得税）',
            'shotoku_jigyo_nogyo_shotoku_%s' => '事業所得（農業）所得金額（所得税）',
            'shotoku_rishi_shotoku_%s' => '利子所得 所得金額（所得税）',
            'shotoku_haito_shotoku_%s' => '配当所得 所得金額（所得税）',
            'bunri_shotoku_taishoku_shotoku_%s' => '退職（分離）所得金額（所得税）',
            'kojo_shakaihoken_shotoku_%s' => '社会保険料控除（所得税）',
            'kojo_shokibo_shotoku_%s' => '小規模企業共済等掛金控除（所得税）',
            'kojo_zasson_shotoku_%s' => '雑損控除（所得税）',
            'tax_haito_shotoku_%s' => '配当控除（所得税）',
            'tax_kaisyu_shotoku_%s' => '住宅耐震改修特別控除（所得税）',
            'tax_saigai_genmen_shotoku_%s' => '災害減免額（所得税）',
            'tax_tokubetsu_R6_shotoku_%s' => '令和6年度分特別税額控除（所得税）',
        ];
        foreach (['prev' => '前年', 'curr' => '当年'] as $period => $pLabel) {
            foreach ($labelByBase as $fmt => $baseLabel) {
                $attributes[sprintf($fmt, $period)] = sprintf('%s（%s）', $baseLabel, $pLabel);
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
