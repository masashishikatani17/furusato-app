<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Tax\FurusatoController;
use App\Services\Tax\Kojo\SeitotoKihukinTokubetsuService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class KojoKeyNormalizationTest extends TestCase
{
    #[Test]
    public function it_copies_legacy_values_to_canonical_keys(): void
    {
        $controller = app(FurusatoController::class);
        $method = new ReflectionMethod($controller, 'normalizeKojoRenamedKeys');
        $method->setAccessible(true);

        $payload = [
            'kojo_kiso_shotoku_prev' => '123',
            'kojo_kifukin_jumin_prev' => '456',
            'tax_seito_shotoku_curr' => '789',
            'kojo_shogaisha_shotoku_prev' => '1420000',
            'kojo_shogaisha_jumin_prev' => '1090000',
        ];

        $method->invokeArgs($controller, [&$payload]);

        $this->assertSame(123, $payload['shotokuzei_kojo_kiso_prev']);
        $this->assertSame(123, $payload['kojo_kiso_shotoku_prev']);
        $this->assertSame(456, $payload['juminzei_kojo_kifukin_prev']);
        $this->assertSame(456, $payload['kojo_kifukin_jumin_prev']);
        $this->assertSame(789, $payload['shotokuzei_zeigakukojo_seitoto_tokubetsu_curr']);
        $this->assertSame(789, $payload['tax_seito_shotoku_curr']);
        $this->assertSame(1_420_000, $payload['kojo_shogaisyo_shotoku_prev']);
        $this->assertSame(1_420_000, $payload['kojo_shogaisha_shotoku_prev']);
        $this->assertSame(1_090_000, $payload['kojo_shogaisyo_jumin_prev']);
        $this->assertSame(1_090_000, $payload['kojo_shogaisha_jumin_prev']);
    }

    #[Test]
    public function it_backfills_legacy_aliases_from_canonical_values(): void
    {
        $controller = app(FurusatoController::class);
        $method = new ReflectionMethod($controller, 'normalizeKojoRenamedKeys');
        $method->setAccessible(true);

        $payload = [
            'shotokuzei_kojo_kiso_curr' => 321,
            'juminzei_kojo_kifukin_curr' => 654,
            'shotokuzei_zeigakukojo_seitoto_tokubetsu_prev' => 987,
            'kojo_shogaisyo_shotoku_curr' => 200_000,
            'kojo_shogaisyo_jumin_curr' => 150_000,
        ];

        $method->invokeArgs($controller, [&$payload]);

        $this->assertSame(321, $payload['kojo_kiso_shotoku_curr']);
        $this->assertSame(654, $payload['kojo_kifukin_jumin_curr']);
        $this->assertSame(987, $payload['tax_seito_shotoku_prev']);
        $this->assertArrayNotHasKey('tax_seito_jumin_prev', $payload);
        $this->assertArrayNotHasKey('juminzei_zeigakukojo_seitoto_tokubetsu_prev', $payload);
        $this->assertSame(200_000, $payload['kojo_shogaisha_shotoku_curr']);
        $this->assertSame(150_000, $payload['kojo_shogaisha_jumin_curr']);
    }

    #[Test]
    public function seitoto_service_computes_expected_tax_credit(): void
    {
        $service = app(SeitotoKihukinTokubetsuService::class);

        $payload = [
            'shotoku_gokei_shotoku_curr' => '13,831,070',
            'shotokuzei_shotokukojo_furusato_curr' => '448,000',
            'shotokuzei_shotokukojo_sonota_curr' => '13,000',
            'shotokuzei_zeigakukojo_npo_curr' => '13,000',
            'tax_zeigaku_shotoku_curr' => '2,441,820',
        ];

        $results = $service->compute($payload);

        $this->assertSame(5200, $results['shotokuzei_zeigakukojo_seitoto_tokubetsu_curr']);
        $this->assertSame(0, $results['shotokuzei_zeigakukojo_seitoto_tokubetsu_prev']);
        $this->assertSame(0, $results['juminzei_zeigakukojo_seitoto_tokubetsu_prev']);
        $this->assertSame(0, $results['juminzei_zeigakukojo_seitoto_tokubetsu_curr']);

        $controller = app(FurusatoController::class);
        $normalize = new ReflectionMethod($controller, 'normalizeKojoRenamedKeys');
        $normalize->setAccessible(true);
        $normalize->invokeArgs($controller, [&$results]);

        $this->assertSame(5200, $results['tax_seito_shotoku_curr']);
        $this->assertSame(0, $results['tax_seito_jumin_curr']);
    }
}