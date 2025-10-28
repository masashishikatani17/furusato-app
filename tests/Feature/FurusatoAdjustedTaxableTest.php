<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tax\Calculators\BunriSeparatedMinRateCalculator;
use App\Domain\Tax\Calculators\FurusatoResultCalculator;
use App\Domain\Tax\Calculators\TokureiRateCalculator;
use App\Http\Controllers\Tax\FurusatoController;
use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\FurusatoSyoriSetting;
use App\Models\TokureiRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class FurusatoAdjustedTaxableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_adjusted_taxable_and_looks_up_standard_rate(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'group_id' => 1,
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id' => 1,
            'group_id' => 1,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2025,
            'visibility' => 'private',
        ]);

        $input = new FurusatoInput();
        $input->data_id = $data->id;
        $input->company_id = 1;
        $input->group_id = 1;
        $input->payload = [
            'tax_kazeishotoku_shotoku_prev' => 5_000_000,
            'tax_kazeishotoku_shotoku_curr' => 1_200_000,
            'syunyu_sanrin_prev' => 300_000,
            'syunyu_sanrin_curr' => 0,
            'kojo_gokei_shotoku_prev' => 150_000,
            'kojo_gokei_shotoku_curr' => 120_000,
            
            'kojo_kafu_shotoku_prev' => 200_000,
            'kojo_kafu_jumin_prev'   => 0,
            'kojo_kafu_shotoku_curr' => 2_000_000,
            'kojo_kafu_jumin_curr'   => 500_000,

            // 内部通算の「差引」入力は保存済み入力に置く（ここ重要）
            'sashihiki_joto_tanki_sogo_prev' => 120_000,
            'sashihiki_joto_choki_sogo_prev' => 340_000,
            'sashihiki_ichiji_prev'          => -50_000,
            'sashihiki_joto_tanki_sogo_curr' => 100_000,
            'sashihiki_joto_choki_sogo_curr' => 200_000,
            'sashihiki_ichiji_curr'          => 50_000,
        ];
        $input->save();

        TokureiRate::query()->create([
            'company_id' => null,
            'kihu_year' => 2025,
            'year' => 2025,
            'version' => 1,
            'seq' => 1,
            'sort' => 1,
            'income_rate' => 0.000,
            'ninety_minus_rate' => 0.000,
            'income_rate_with_recon' => 0.000,
            'lower' => 0,
            'upper' => 1_950_000,
            'tokurei_deduction_rate' => 84.895,
        ]);

        TokureiRate::query()->create([
            'company_id' => null,
            'kihu_year' => 2025,
            'year' => 2025,
            'version' => 1,
            'seq' => 2,
            'sort' => 2,
            'income_rate' => 0.000,
            'ninety_minus_rate' => 0.000,
            'income_rate_with_recon' => 0.000,
            'lower' => 1_951_000,
            'upper' => 3_300_000,
            'tokurei_deduction_rate' => 80.000,
        ]);

        TokureiRate::query()->create([
            'company_id' => null,
            'kihu_year' => 2025,
            'year' => 2025,
            'version' => 1,
            'seq' => 3,
            'sort' => 3,
            'income_rate' => 0.000,
            'ninety_minus_rate' => 0.000,
            'income_rate_with_recon' => 0.000,
            'lower' => 3_301_000,
            'upper' => 6_950_000,
            'tokurei_deduction_rate' => 69.580,
        ]);

        $request = Request::create('/dummy', 'GET', ['data_id' => $data->id]);
        $request->setUserResolver(static fn () => $user);

        $controller = app(FurusatoController::class);
        $method = new ReflectionMethod($controller, 'makeInputContext');
        $method->setAccessible(true);

        /** @var array<string, mixed> $context */
        $context = $method->invoke($controller, $request, $data->id);

        $this->assertSame(4_800_000, $context['jintekiDiff']['adjusted_taxable']['prev']);
        $this->assertSame(0, $context['jintekiDiff']['adjusted_taxable']['curr']);
        $this->assertArrayHasKey('tokureiStandardRate', $context);
        $this->assertEqualsWithDelta(69.58,  $context['tokureiStandardRate']['prev'], 0.0001); // 4,800,000 → 69.580
        $this->assertEqualsWithDelta(84.895, $context['tokureiStandardRate']['curr'], 0.0001); // 0 → 84.895

        /** @var FurusatoResultCalculator $calculator */
        $calculator = app(FurusatoResultCalculator::class);
        $calculatorPayload = [
            'tax_kazeishotoku_shotoku_prev' => 5_000_000,
            'tax_kazeishotoku_shotoku_curr' => 1_200_000,
            'kojo_kafu_shotoku_prev' => 200_000,
            'kojo_kafu_jumin_prev' => 0,
            'kojo_kafu_shotoku_curr' => 2_000_000,
            'kojo_kafu_jumin_curr' => 500_000,
        ];
        $calculatorCtx = [
            'syori_settings' => [],
            'master_kihu_year' => 0,
            'kihu_year' => 0,
        ];

        $result = $calculator->compute($calculatorPayload, $calculatorCtx);
        $this->assertSame(4_800_000, $result['human_adjusted_taxable_prev']);
        $this->assertSame(-300_000, $result['human_adjusted_taxable_curr']);
    }

    #[Test]
    public function it_mirrors_third_stage_totals_into_inputs(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'group_id' => 1,
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id' => 1,
            'group_id' => 1,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2025,
            'visibility' => 'private',
        ]);

        $input = new FurusatoInput();
        $input->data_id = $data->id;
        $input->company_id = 1;
        $input->group_id = 1;
        $input->payload = [
            'syunyu_sanrin_prev' => 300_000,
            'syunyu_sanrin_curr' => 0,
            'kojo_gokei_shotoku_prev' => 150_000,
            'kojo_gokei_shotoku_curr' => 120_000,
            'kojo_gokei_jumin_prev'   => 150_000,
            'sashihiki_joto_tanki_sogo_prev' => 120_000,
            'sashihiki_joto_choki_sogo_prev' => 340_000,
            'sashihiki_ichiji_prev'          => -50_000,
            'sashihiki_joto_tanki_sogo_curr' => 100_000,
            'sashihiki_joto_choki_sogo_curr' => 200_000,
            'sashihiki_ichiji_curr'          => 50_000,
        ];
        $input->save();

        FurusatoSyoriSetting::create([
            'data_id' => $data->id,
            'company_id' => 1,
            'group_id' => 1,
            'payload' => [
                'bunri_flag_prev' => 0,
                'bunri_flag_curr' => 1,
            ],
        ]);

        $previewPayload = [
            'sashihiki_joto_tanki_sogo_prev' => 120_000,
            'sashihiki_joto_choki_sogo_prev' => 340_000,
            'sashihiki_ichiji_prev'         => -50_000,
            'sashihiki_joto_tanki_sogo_curr' => 100_000,
            'sashihiki_joto_choki_sogo_curr' => 200_000,
            'sashihiki_ichiji_curr'          => 50_000,
            'after_3jitsusan_joto_tanki_sogo_prev' => 120_000,
            'after_3jitsusan_joto_choki_sogo_prev' => 340_000,
            'after_3jitsusan_ichiji_prev' => -50_000,
            'after_3jitsusan_sanrin_prev' => 60_000,
            'after_3jitsusan_taishoku_prev' => 70_000,
            'after_joto_ichiji_tousan_joto_tanki_sogo_prev' => 50_000,
            'after_joto_ichiji_tousan_joto_choki_sogo_prev' => 25_000,
            'after_joto_ichiji_tousan_ichiji_prev' => 10_000,
            'after_1jitsusan_sanrin_prev' => 45_000,
            'shotoku_keijo_prev' => 200_000,
            'shotoku_joto_tanki_sogo_prev' => 120_000,
            'shotoku_joto_choki_sogo_prev' => 340_000,
            'shotoku_ichiji_prev' => -50_000,
            'shotoku_sanrin_prev' => 80_000,
            'shotoku_taishoku_prev' => 90_000,
            'shotoku_gokei_prev' => 710_000,
            'after_3jitsusan_joto_tanki_sogo_curr' => 100_000,
            'after_3jitsusan_joto_choki_sogo_curr' => 200_000,
            'after_3jitsusan_ichiji_curr' => 50_000,
            'after_3jitsusan_sanrin_curr' => 60_000,
            'after_3jitsusan_taishoku_curr' => 70_000,
            'after_1jitsusan_sanrin_curr' => 55_000,
            'shotoku_keijo_curr' => 500_000,
            'shotoku_joto_tanki_sogo_curr' => 100_000,
            'shotoku_joto_choki_sogo_curr' => 200_000,
            'shotoku_ichiji_curr' => 50_000,
            'shotoku_sanrin_curr' => 40_000,
            'shotoku_taishoku_curr' => 30_000,
            'shotoku_gokei_curr' => 880_000,
            'kojo_gokei_shotoku_prev' => 150_000,
            'kojo_gokei_shotoku_curr' => 120_000,
            'bunri_kazeishotoku_sogo_shotoku_curr' => 732_345,
            'kazeisoushotoku_curr' => 481_234,
        ];

        $details = [
            'prev' => ['AA50' => null, 'AA56' => null],
            'curr' => ['AA50' => null, 'AA56' => null],
        ];

        app()->instance(
            TokureiRateCalculator::class,
            new class($previewPayload)
            {
                public function __construct(private array $preview)
                {
                }

                public function compute(array $payload, array $ctx): array
                {
                    return array_replace($payload, $this->preview);
                }
            }
        );

        app()->instance(
            BunriSeparatedMinRateCalculator::class,
            new class()
            {
                public function compute(array $payload, array $ctx): array
                {
                    return $payload;
                }
            }
        );

        app()->instance(
            FurusatoResultCalculator::class,
            new class($details)
            {
                public function __construct(private array $details)
                {
                }

                public function buildDetails(array $payload, array $ctx): array
                {
                    return $this->details;
                }
            }
        );

        try {
            $request = Request::create('/dummy', 'GET', ['data_id' => $data->id]);
            $request->setUserResolver(static fn () => $user);

            $controller = app(FurusatoController::class);
            $method = new ReflectionMethod($controller, 'makeInputContext');
            $method->setAccessible(true);

            /** @var array<string, mixed> $context */
            $context = $method->invoke($controller, $request, $data->id);

            $this->assertArrayHasKey('outInputs', $context);
            $inputs = $context['outInputs'];

            $this->assertSame(120_000, $inputs['tsusango_joto_tanki_sogo_prev']);
            $this->assertSame(340_000, $inputs['tsusango_joto_choki_sogo_prev']);
            $this->assertSame(0, $inputs['tsusango_ichiji_prev']);
            $this->assertSame(50_000, $inputs['tsusanmae_joto_tanki_sogo_prev']);
            $this->assertSame(25_000, $inputs['tsusanmae_joto_choki_sogo_prev']);
            $this->assertSame(10_000, $inputs['tsusanmae_ichiji_prev']);
            $this->assertSame(45_000, $inputs['after_1jitsusan_sanrin_prev']);
            $this->assertSame(300_000, $inputs['bunri_syunyu_sanrin_shotoku_prev']);
            $this->assertSame(460_000, $inputs['shotoku_joto_ichiji_shotoku_prev']);
            $this->assertSame(460_000, $inputs['shotoku_joto_ichiji_jumin_prev']);
            $this->assertSame(510_000, $inputs['tax_kazeishotoku_shotoku_prev']);
            $this->assertSame(510_000, $inputs['tax_kazeishotoku_jumin_prev']);
            $this->assertSame(0, $inputs['bunri_sogo_gokeigaku_shotoku_prev']);
            $this->assertSame(0, $inputs['bunri_sogo_gokeigaku_jumin_prev']);

            $this->assertSame(100_000, $inputs['tsusango_joto_tanki_sogo_curr']);
            $this->assertSame(200_000, $inputs['tsusango_joto_choki_sogo_curr']);
            $this->assertSame(50_000, $inputs['tsusango_ichiji_curr']);
            $this->assertSame(350_000, $inputs['shotoku_joto_ichiji_shotoku_curr']);
            $this->assertSame(350_000, $inputs['shotoku_joto_ichiji_jumin_curr']);
            $this->assertSame(480_000, $inputs['bunri_sogo_gokeigaku_shotoku_curr']);
            $this->assertSame(480_000, $inputs['bunri_sogo_gokeigaku_jumin_curr']);
            $this->assertSame(732_000, $inputs['tax_kazeishotoku_shotoku_curr']);
            $this->assertSame(481_000, $inputs['tax_kazeishotoku_jumin_curr']);
            $this->assertSame(30_000, $inputs['bunri_shotoku_taishoku_shotoku_curr']);
            $this->assertSame(30_000, $inputs['bunri_shotoku_taishoku_jumin_curr']);
            $this->assertSame(880_000, $inputs['shotoku_gokei_curr']);
        } finally {
            app()->forgetInstance(TokureiRateCalculator::class);
            app()->forgetInstance(BunriSeparatedMinRateCalculator::class);
            app()->forgetInstance(FurusatoResultCalculator::class);
        }
    }

    #[Test]
    public function it_renders_adjusted_taxable_and_standard_rate(): void
    {
        $html = view('tax.furusato.tabs.result_details', [
            'results' => ['details' => []],
            'jintekiDiff' => [
                'sum' => ['prev' => 200_000, 'curr' => 1_500_000],
                'adjusted_taxable' => ['prev' => 4_800_000, 'curr' => 0],
            ],
            'tokureiStandardRate' => ['prev' => 84.895, 'curr' => 69.58],
            'warekiPrev' => '前年',
            'warekiCurr' => '当年',
        ])->render();

        $this->assertStringContainsString('課税総所得金額-人的控除差調整額', $html);
        $this->assertStringContainsString('4,800,000', $html);
        $this->assertStringContainsString('0</td>', $html);
        $this->assertStringContainsString('特例控除率（標準） 前年：84.895%', $html);
        $this->assertStringContainsString('当年：69.58%', $html);
    }
}