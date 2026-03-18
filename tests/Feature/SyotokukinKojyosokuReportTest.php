<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tax\Services\FurusatoDryRunCalculatorRunner;
use App\Domain\Tax\Services\FurusatoPracticalUpperLimitService;
use App\Models\Data;
use App\Models\FurusatoResult;
use App\Models\User;
use App\Reports\Shotoku\SyotokukinKojyosokuReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SyotokukinKojyosokuReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_forces_retirement_jumin_to_zero_in_curr_variant(): void
    {
        $data = $this->createData();

        FurusatoResult::query()->create([
            'data_id' => $data->id,
            'company_id' => $data->company_id,
            'group_id' => $data->group_id,
            'payload' => [
                'payload' => [
                    'shotokuzei_shotokukojo_furusato_curr' => 0,
                    'juminzei_zeigakukojo_pref_furusato_curr' => 12_000,
                    'juminzei_zeigakukojo_muni_furusato_curr' => 0,
                    'bunri_shotoku_taishoku_jumin_curr' => 2_000_000,
                    'tb_taishoku_jumin_curr' => 0,
                ],
            ],
        ]);

        $runner = new class() {
            /** @var array<string,mixed> */
            public array $received = [];

            /** @param array<string,mixed> $payload @param array<string,mixed> $ctx @return array<string,mixed> */
            public function run(array $payload, array $ctx): array
            {
                $this->received = $payload;
                unset($ctx);

                return array_replace($payload, [
                    'bunri_shotoku_taishoku_shotoku_curr' => 777_000,
                    'bunri_shotoku_taishoku_jumin_curr' => 2_000_000,
                    'tb_taishoku_jumin_curr' => 0,
                ]);
            }
        };

        app()->instance(FurusatoDryRunCalculatorRunner::class, $runner);

        try {
            $report = app(SyotokukinKojyosokuReport::class);
            $viewData = $report->buildViewDataWithContext($data, ['report_key' => 'syotokukinkojyosoku_curr']);

            $this->assertSame(0, $runner->received['bunri_shotoku_taishoku_jumin_curr'] ?? null);
            $this->assertSame(0, $viewData['income_table_curr']['bunri']['taishoku']['rtax'] ?? null);
            $this->assertSame(777_000, $viewData['income_table_curr']['bunri']['taishoku']['itax'] ?? null);
        } finally {
            app()->forgetInstance(FurusatoDryRunCalculatorRunner::class);
        }
    }

    #[Test]
    public function it_forces_retirement_jumin_to_zero_in_max_variant(): void
    {
        $data = $this->createData();

        FurusatoResult::query()->create([
            'data_id' => $data->id,
            'company_id' => $data->company_id,
            'group_id' => $data->group_id,
            'payload' => [
                'payload' => [
                    'bunri_shotoku_taishoku_jumin_curr' => 2_000_000,
                    'tb_taishoku_jumin_curr' => 0,
                ],
            ],
        ]);

        $runner = new class() {
            /** @var array<string,mixed> */
            public array $received = [];

            /** @param array<string,mixed> $payload @param array<string,mixed> $ctx @return array<string,mixed> */
            public function run(array $payload, array $ctx): array
            {
                $this->received = $payload;
                unset($ctx);

                return array_replace($payload, [
                    'bunri_shotoku_taishoku_shotoku_curr' => 555_000,
                    'bunri_shotoku_taishoku_jumin_curr' => 2_000_000,
                    'tb_taishoku_jumin_curr' => 0,
                ]);
            }
        };

        $upperSvc = new class() {
            /** @param array<string,mixed> $payload @param array<string,mixed> $ctx @return array<string,mixed> */
            public function compute(array $payload, array $ctx): array
            {
                unset($payload, $ctx);

                return ['y_max_total' => 123_456];
            }
        };

        app()->instance(FurusatoDryRunCalculatorRunner::class, $runner);
        app()->instance(FurusatoPracticalUpperLimitService::class, $upperSvc);

        try {
            $report = app(SyotokukinKojyosokuReport::class);
            $viewData = $report->buildViewDataWithContext($data, ['report_key' => 'syotokukinkojyosoku']);

            $this->assertSame(0, $runner->received['bunri_shotoku_taishoku_jumin_curr'] ?? null);
            $this->assertSame(0, $viewData['income_table_curr']['bunri']['taishoku']['rtax'] ?? null);
            $this->assertSame(555_000, $viewData['income_table_curr']['bunri']['taishoku']['itax'] ?? null);
        } finally {
            app()->forgetInstance(FurusatoDryRunCalculatorRunner::class);
            app()->forgetInstance(FurusatoPracticalUpperLimitService::class);
        }
    }

    private function createData(): Data
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'group_id' => 1,
        ]);

        return Data::query()->create([
            'guest_id' => null,
            'company_id' => 1,
            'group_id' => 1,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2025,
            'visibility' => 'private',
        ]);
    }
}
