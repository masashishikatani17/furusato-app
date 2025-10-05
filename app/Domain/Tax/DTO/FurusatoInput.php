<?php

namespace App\Domain\Tax\DTO;

final class FurusatoInput
{
    public int $jiryo_eigyo_prev;
    public int $jiryo_eigyo_curr;
    public int $jiryo_nogyo_prev;
    public int $jiryo_nogyo_curr;
    public int $fudosan_prev;
    public int $fudosan_curr;
    public int $haito_prev;
    public int $haito_curr;
    public int $kyuyo_prev;
    public int $kyuyo_curr;
    public int $zatsu_nenkin_prev;
    public int $zatsu_nenkin_curr;
    public int $zatsu_gyomu_prev;
    public int $zatsu_gyomu_curr;
    public int $zatsu_sonota_prev;
    public int $zatsu_sonota_curr;
    public int $sogo_joto_tanki_prev;
    public int $sogo_joto_tanki_curr;
    public int $sogo_joto_choki_prev;
    public int $sogo_joto_choki_curr;
    public int $ichiji_prev;
    public int $ichiji_curr;
    public int $bunri_tanki_ippan_prev;
    public int $bunri_tanki_ippan_curr;
    public int $bunri_tanki_keigen_prev;
    public int $bunri_tanki_keigen_curr;
    public int $bunri_choki_ippan_prev;
    public int $bunri_choki_ippan_curr;
    public int $bunri_choki_tokutei_prev;
    public int $bunri_choki_tokutei_curr;
    public int $bunri_choki_keika_prev;
    public int $bunri_choki_keika_curr;
    public int $ippan_kabu_joto_prev;
    public int $ippan_kabu_joto_curr;
    public int $jojo_kabu_joto_prev;
    public int $jojo_kabu_joto_curr;
    public int $jojo_kabu_haito_prev;
    public int $jojo_kabu_haito_curr;
    public int $sakimono_zatsu_prev;
    public int $sakimono_zatsu_curr;
    public int $sanrin_prev;
    public int $sanrin_curr;
    public int $taishoku_prev;
    public int $taishoku_curr;
    public int $shakaihoken_kojo_curr;
    public int $shokibo_kyosai_kojo_curr;
    public int $seimei_hoken_kojo_curr;
    public int $jishin_hoken_kojo_curr;
    public int $kafu_kojo_flag;
    public int $hitori_oya_kojo_flag;
    public int $kinro_gakusei_kojo_flag;
    public int $shogaisha_count;
    public int $tokubetsu_shogaisha_count;
    public int $dokyo_tokubetsu_shogaisha_count;
    public int $haigusha_kojo_kingaku;
    public int $haigusha_tokubetsu_kojo_kingaku;
    public int $fuyo_ippan_count;
    public int $fuyo_tokutei_count;
    public int $fuyo_rojin_count;
    public int $fuyo_dokyo_rojin_count;
    public int $tokutei_shinzoku_tokubetsu_count;
    public int $zasson_kojo_kingaku;
    public int $iryo_hi_kojo_kingaku;
    public int $tokutei_kifukin_kingaku;
    public int $furusato_nozei_kingaku;
    public int $seitotou_kifukin_kingaku;
    public int $nintei_npo_kifukin_kingaku;
    public int $koueki_shadan_kifukin_kingaku;
    public int $kyobo_nisseki_kifukin_kingaku;
    public int $jorei_npo_kifukin_kingaku;
    public int $one_stop_flag;
    public int $tokubetsu_zeigaku_kojo_kingaku;
    public int $gensen_choshu_zeigaku;
    public int $shitei_toshi_flag;

    private const FIELD_NAMES = [
        'jiryo_eigyo_prev',
        'jiryo_eigyo_curr',
        'jiryo_nogyo_prev',
        'jiryo_nogyo_curr',
        'fudosan_prev',
        'fudosan_curr',
        'haito_prev',
        'haito_curr',
        'kyuyo_prev',
        'kyuyo_curr',
        'zatsu_nenkin_prev',
        'zatsu_nenkin_curr',
        'zatsu_gyomu_prev',
        'zatsu_gyomu_curr',
        'zatsu_sonota_prev',
        'zatsu_sonota_curr',
        'sogo_joto_tanki_prev',
        'sogo_joto_tanki_curr',
        'sogo_joto_choki_prev',
        'sogo_joto_choki_curr',
        'ichiji_prev',
        'ichiji_curr',
        'bunri_tanki_ippan_prev',
        'bunri_tanki_ippan_curr',
        'bunri_tanki_keigen_prev',
        'bunri_tanki_keigen_curr',
        'bunri_choki_ippan_prev',
        'bunri_choki_ippan_curr',
        'bunri_choki_tokutei_prev',
        'bunri_choki_tokutei_curr',
        'bunri_choki_keika_prev',
        'bunri_choki_keika_curr',
        'ippan_kabu_joto_prev',
        'ippan_kabu_joto_curr',
        'jojo_kabu_joto_prev',
        'jojo_kabu_joto_curr',
        'jojo_kabu_haito_prev',
        'jojo_kabu_haito_curr',
        'sakimono_zatsu_prev',
        'sakimono_zatsu_curr',
        'sanrin_prev',
        'sanrin_curr',
        'taishoku_prev',
        'taishoku_curr',
        'shakaihoken_kojo_curr',
        'shokibo_kyosai_kojo_curr',
        'seimei_hoken_kojo_curr',
        'jishin_hoken_kojo_curr',
        'kafu_kojo_flag',
        'hitori_oya_kojo_flag',
        'kinro_gakusei_kojo_flag',
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
        'one_stop_flag',
        'tokubetsu_zeigaku_kojo_kingaku',
        'gensen_choshu_zeigaku',
        'shitei_toshi_flag',
    ];

    public static function fromArray(array $values): self
    {
        $instance = new self();

        foreach (self::FIELD_NAMES as $field) {
            $instance->{$field} = (int) ($values[$field] ?? 0);
        }

        return $instance;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}