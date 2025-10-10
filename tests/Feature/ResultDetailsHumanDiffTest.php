<?php

namespace Tests\Feature;

use App\Http\Controllers\Tax\FurusatoController;
use ReflectionMethod;
use Tests\TestCase;

class ResultDetailsHumanDiffTest extends TestCase
{
    public function testComputesHumanDeductionDiffFromPayload(): void
    {
        $controller = $this->app->make(FurusatoController::class);

        $map = [
            'kafu' => ['shotoku' => 'kojo_kafu_shotoku', 'jumin' => 'kojo_kafu_jumin'],
            'hitorioya' => ['shotoku' => 'kojo_hitorioya_shotoku', 'jumin' => 'kojo_hitorioya_jumin'],
            'kinrogakusei' => ['shotoku' => 'kojo_kinrogakusei_shotoku', 'jumin' => 'kojo_kinrogakusei_jumin'],
            'shogaisyo' => ['shotoku' => 'kojo_shogaisyo_shotoku', 'jumin' => 'kojo_shogaisyo_jumin'],
            'haigusha' => ['shotoku' => 'kojo_haigusha_shotoku', 'jumin' => 'kojo_haigusha_jumin'],
            'haigusha_tokubetsu' => ['shotoku' => 'kojo_haigusha_tokubetsu_shotoku', 'jumin' => 'kojo_haigusha_tokubetsu_jumin'],
            'fuyo' => ['shotoku' => 'kojo_fuyo_shotoku', 'jumin' => 'kojo_fuyo_jumin'],
            'kiso' => ['shotoku' => 'shotokuzei_kojo_kiso', 'jumin' => 'juminzei_kojo_kiso'],
        ];

        $values = [
            'kafu' => [
                'prev' => ['shotoku' => 120000, 'jumin' => '80000'],
                'curr' => ['shotoku' => 90000, 'jumin' => '95000'],
            ],
            'hitorioya' => [
                'prev' => ['shotoku' => 30000, 'jumin' => 20000],
                'curr' => ['shotoku' => 0, 'jumin' => 0],
            ],
            'kinrogakusei' => [
                'prev' => ['shotoku' => 15000, 'jumin' => 7000],
                'curr' => ['shotoku' => 20000, 'jumin' => 12000],
            ],
            'shogaisyo' => [
                'prev' => ['shotoku' => 40000, 'jumin' => '45000'],
                'curr' => ['shotoku' => 50000, 'jumin' => 30000],
            ],
            'haigusha' => [
                'prev' => ['shotoku' => 60000, 'jumin' => 55000],
                'curr' => ['shotoku' => 65000, 'jumin' => 50000],
            ],
            'haigusha_tokubetsu' => [
                'prev' => ['shotoku' => 10000, 'jumin' => 12000],
                'curr' => ['shotoku' => 15000, 'jumin' => 16000],
            ],
            'fuyo' => [
                'prev' => ['shotoku' => 80000, 'jumin' => 60000],
                'curr' => ['shotoku' => 82000, 'jumin' => 62000],
            ],
            'kiso' => [
                'prev' => ['shotoku' => 430000, 'jumin' => 330000],
                'curr' => ['shotoku' => 430000, 'jumin' => 330000],
            ],
        ];

        $payload = [];
        foreach ($map as $key => $fields) {
            foreach (['prev', 'curr'] as $period) {
                $payload[sprintf('%s_%s', $fields['shotoku'], $period)] = $values[$key][$period]['shotoku'];
                $payload[sprintf('%s_%s', $fields['jumin'], $period)] = $values[$key][$period]['jumin'];
            }
        }

        $method = new ReflectionMethod(FurusatoController::class, 'computeJintekiDiff');
        $method->setAccessible(true);

        $diff = $method->invoke($controller, $payload);

        $expected = [];
        $totals = ['prev' => 0, 'curr' => 0];
        foreach ($map as $key => $fields) {
            foreach (['prev', 'curr'] as $period) {
                $shotoku = (int) $values[$key][$period]['shotoku'];
                $jumin = (int) $values[$key][$period]['jumin'];
                $value = $shotoku - $jumin;
                $expected[$key][$period] = $value;
                $totals[$period] += $value;
            }
        }
        $expected['sum'] = $totals;

        $this->assertSame($expected, $diff);
    }
}