<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Tax\Support\PayloadNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PayloadNormalizerTest extends TestCase
{
    #[Test]
    public function it_sums_legacy_kifukin_resident_tax_keys(): void
    {
        $normalizer = new PayloadNormalizer();

        $payload = $normalizer->normalize([
            'juminzei_zeigakukojo_pref_furusato_prev' => '10',
            'juminzei_zeigakukojo_muni_furusato_prev' => '5',
            'juminzei_zeigakukojo_furusato_prev' => '3',
        ]);

        $this->assertSame(18, $payload['juminzei_zeigakukojo_furusato_prev']);
        $this->assertArrayNotHasKey('juminzei_zeigakukojo_pref_furusato_prev', $payload);
        $this->assertArrayNotHasKey('juminzei_zeigakukojo_muni_furusato_prev', $payload);
    }

    #[Test]
    public function it_keeps_canonical_values_when_no_legacy_keys_exist(): void
    {
        $normalizer = new PayloadNormalizer();

        $payload = $normalizer->normalize([
            'juminzei_zeigakukojo_npo_curr' => '12',
        ]);

        $this->assertSame(12, $payload['juminzei_zeigakukojo_npo_curr']);
    }
}