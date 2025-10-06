<?php

namespace App\Http\Requests\Tax;

use App\Domain\Tax\DTO\FurusatoInput;
use Illuminate\Foundation\Http\FormRequest;

final class FurusatoInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
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

        foreach (array_keys($incomeFields) as $field) {
            $rules[sprintf('%s_prev', $field)] = ['required', 'integer', 'min:0'];
            $rules[sprintf('%s_curr', $field)] = ['required', 'integer', 'min:0'];
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
            $rules[$field] = ['required', 'integer', 'min:0'];
        }

        $flagFields = [
            'kafu_kojo_flag',
            'hitori_oya_kojo_flag',
            'kinro_gakusei_kojo_flag',
            'one_stop_flag',
            'shitei_toshi_flag',
        ];

        foreach ($flagFields as $field) {
            $rules[$field] = ['required', 'in:0,1'];
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
                'joto_tanki',
                'joto_choki',
                'ichiji',
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
                        $rules[sprintf('%s_%s_%s_%s', $group, $field, $tax, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
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
                    $rules[sprintf('bunri_%s_%s_%s', $base, $tax, $period)] = ['bail', 'nullable', 'integer', 'min:0'];
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => ':attributeは必須です。',
            'integer' => ':attributeは整数で入力してください。',
            'min' => ':attributeは:min以上で入力してください。',
            'in' => ':attributeは0または1を選択してください。',
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
        ];

        return $attributes;
    }

    public function toDto(): FurusatoInput
    {
        $payload = $this->validated();
        unset($payload['data_id']);

        return FurusatoInput::fromArray($payload);
    }
}