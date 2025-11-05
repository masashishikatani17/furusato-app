<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Application\UseCases\Tax\RecalculateFurusatoPayload;
use App\Models\Data;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\DetailsSourceAliasCalculator;
use App\Domain\Tax\Calculators\ResultToDetailsAliasCalculator;
use App\Domain\Tax\Calculators\TaxBaseMirrorCalculator;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\Kojo\HaigushaKojoService;
use App\Services\Tax\Kojo\JintekiKojoService;
use App\Services\Tax\Kojo\KifukinShotokuKojoService;
use App\Services\Tax\Kojo\KihonService;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ServicesProvideKeysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_populates_all_provided_keys(): void
    {
        $data = Data::create([
            'guest_id' => null,
            'company_id' => 1,
            'group_id' => 1,
            'user_id' => 1,
            'owner_user_id' => 1,
            'kihu_year' => 2025,
            'visibility' => 'private',
        ]);

        /** @var RecalculateFurusatoPayload $useCase */
        $useCase = app(RecalculateFurusatoPayload::class);
        $result = $useCase->handle($data, [], ['should_flash_results' => false]);
        $payload = $result['payload'];

        $services = [
            app(KifukinShotokuKojoService::class),
            app(KihonService::class),
            app(JintekiKojoService::class),
            app(HaigushaKojoService::class),
            app(SeitotoKihukinTokubetsuService::class),
            app(DetailsSourceAliasCalculator::class),
            app(SogoShotokuNettingCalculator::class),
            app(SogoShotokuNettingStagesCalculator::class),
            app(BunriNettingCalculator::class),
            app(BunriKabutekiNettingCalculator::class),
            app(ResultToDetailsAliasCalculator::class),
        ];

        foreach ($services as $service) {
            $this->assertInstanceOf(ProvidesKeys::class, $service);
            foreach ($service::provides() as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $payload,
                    sprintf('Failed asserting that %s populates %s', $service::class, $key)
                );
            }
        }
    }

    #[Test]
    public function tax_base_mirror_calculator_provides_all_expected_keys(): void
    {
        $provided = TaxBaseMirrorCalculator::provides();

        $expected = [];
        foreach (['prev', 'curr'] as $period) {
            foreach ([
                'tsusanmae_joto_tanki_sogo_%s',
                'tsusanmae_joto_choki_sogo_%s',
                'tsusanmae_ichiji_%s',
                'shotoku_keijo_%s',
                'shotoku_joto_tanki_sogo_%s',
                'shotoku_joto_choki_sogo_%s',
                'shotoku_ichiji_%s',
                'shotoku_sanrin_%s',
                'shotoku_taishoku_%s',
                'shotoku_gokei_%s',
                'shotoku_joto_ichiji_shotoku_%s',
                'shotoku_joto_ichiji_jumin_%s',
                'tax_kazeishotoku_shotoku_%s',
                'tax_kazeishotoku_jumin_%s',
                'bunri_sogo_gokeigaku_shotoku_%s',
                'bunri_sogo_gokeigaku_jumin_%s',
                'bunri_sashihiki_gokei_shotoku_%s',
                'bunri_sashihiki_gokei_jumin_%s',
                'bunri_kazeishotoku_sogo_shotoku_%s',
                'bunri_kazeishotoku_sogo_jumin_%s',
                'tokurei_kojo_sanrin_%s',
                'after_2jitsusan_taishoku_%s',
            ] as $format) {
                $expected[] = sprintf($format, $period);
            }
        }

        foreach ($expected as $key) {
            $this->assertContains($key, $provided, sprintf('TaxBaseMirrorCalculator::provides is missing %s', $key));
        }
    }
}