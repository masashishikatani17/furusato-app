<?php

namespace Tests\Feature;

use Tests\TestCase;

final class InputSheetSkeletonTest extends TestCase
{
    /** @test */
    public function it_returns_inputs_from_posted_payload(): void
    {
        $payload = $this->makePayload();

        $response = $this->post('/furusato/calc', $payload);

        $response->assertStatus(200);
        $response->assertViewIs('tax.furusato.input');
        $response->assertViewHas('out.inputs', function ($inputs) use ($payload) {
            $this->assertIsArray($inputs);

            foreach ([
                'jiryo_eigyo_prev',
                'jiryo_eigyo_curr',
                'shogaisha_count',
                'kafu_kojo_flag',
                'tokutei_kifukin_kingaku',
                'tokubetsu_zeigaku_kojo_kingaku',
                'gensen_choshu_zeigaku',
                'shitei_toshi_flag',
            ] as $key) {
                $this->assertArrayHasKey($key, $inputs);
                $this->assertSame($payload[$key], $inputs[$key]);
            }

            $this->assertArrayNotHasKey('basic_kifukin_gokei_resident', $inputs);
            $this->assertArrayNotHasKey('tokurei_kifukin_gokei_resident', $inputs);

            return true;
        });
    }

    /** @test */
    public function json_request_returns_inputs_fragment(): void
    {
        $payload = $this->makePayload();

        $response = $this->postJson('/furusato/calc', $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['shitei_toshi_flag' => $payload['shitei_toshi_flag']])
            ->assertJsonMissingPath('inputs.basic_kifukin_gokei_resident')
            ->assertJsonMissingPath('inputs.tokurei_kifukin_gokei_resident');
    }

    private function makePayload(): array
    {
        $payload = [
            'data_id' => 1,
        ];

        $incomeFields = [
            'jiryo_eigyo',
            'jiryo_nogyo',
            'fudosan',
            'haito',
            'kyuyo',
            'zatsu_nenkin',
            'zatsu_gyomu',
            'zatsu_sonota',
            'sogo_joto_tanki',
            'sogo_joto_choki',
            'ichiji',
            'bunri_tanki_ippan',
            'bunri_tanki_keigen',
            'bunri_choki_ippan',
            'bunri_choki_tokutei',
            'bunri_choki_keika',
            'ippan_kabu_joto',
            'jojo_kabu_joto',
            'jojo_kabu_haito',
            'sakimono_zatsu',
            'sanrin',
            'taishoku',
        ];

        foreach ($incomeFields as $index => $field) {
            $payload[sprintf('%s_prev', $field)] = ($index + 1) * 10;
            $payload[sprintf('%s_curr', $field)] = ($index + 1) * 20;
        }

        $payload += [
            'shakaihoken_kojo_curr' => 1000,
            'shokibo_kyosai_kojo_curr' => 2000,
            'seimei_hoken_kojo_curr' => 3000,
            'jishin_hoken_kojo_curr' => 4000,
            'shogaisha_count' => 1,
            'tokubetsu_shogaisha_count' => 2,
            'dokyo_tokubetsu_shogaisha_count' => 3,
            'haigusha_kojo_kingaku' => 150000,
            'haigusha_tokubetsu_kojo_kingaku' => 200000,
            'fuyo_ippan_count' => 1,
            'fuyo_tokutei_count' => 2,
            'fuyo_rojin_count' => 0,
            'fuyo_dokyo_rojin_count' => 1,
            'tokutei_shinzoku_tokubetsu_count' => 0,
            'zasson_kojo_kingaku' => 50000,
            'iryo_hi_kojo_kingaku' => 60000,
            'tokutei_kifukin_kingaku' => 70000,
            'furusato_nozei_kingaku' => 80000,
            'seitotou_kifukin_kingaku' => 90000,
            'nintei_npo_kifukin_kingaku' => 100000,
            'koueki_shadan_kifukin_kingaku' => 110000,
            'kyobo_nisseki_kifukin_kingaku' => 120000,
            'jorei_npo_kifukin_kingaku' => 130000,
            'kafu_kojo_flag' => 1,
            'hitori_oya_kojo_flag' => 0,
            'kinro_gakusei_kojo_flag' => 1,
            'one_stop_flag' => 0,
            'tokubetsu_zeigaku_kojo_kingaku' => 140000,
            'gensen_choshu_zeigaku' => 150000,
            'shitei_toshi_flag' => 1,
        ];

        return $payload;
    }
}