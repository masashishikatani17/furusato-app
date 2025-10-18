<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Tax\FurusatoController;
use App\Models\Data;
use App\Models\FurusatoInput;
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
            'kojo_kafu_shotoku_prev' => 200_000,
            'kojo_kafu_jumin_prev' => 0,
            'kojo_kafu_shotoku_curr' => 2_000_000,
            'kojo_kafu_jumin_curr' => 500_000,
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