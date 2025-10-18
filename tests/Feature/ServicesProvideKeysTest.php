<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Tax\FurusatoController;
use App\Models\Data;
use App\Domain\Tax\Calculators\BunriNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingCalculator;
use App\Domain\Tax\Calculators\SogoShotokuNettingStagesCalculator;
use App\Domain\Tax\Calculators\BunriKabutekiNettingCalculator;
use App\Domain\Tax\Calculators\DetailsSourceAliasCalculator;
use App\Domain\Tax\Calculators\ResultToDetailsAliasCalculator;
use App\Services\Tax\Contracts\ProvidesKeys;
use App\Services\Tax\Kojo\HaigushaKojoService;
use App\Services\Tax\Kojo\JintekiKojoService;
use App\Services\Tax\Kojo\KifukinShotokuKojoService;
use App\Services\Tax\Kojo\KihonService;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
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

        $controller = app(FurusatoController::class);
        $method = new ReflectionMethod($controller, 'applyAutoCalculatedFields');
        $method->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $method->invoke($controller, $data, [], []);

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
                $this->assertArrayHasKey($key, $result, sprintf('Failed asserting that %s populates %s', $service::class, $key));
            }
        }
    }
}