<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Tax\Support\PayloadNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PayloadNormalizerTest extends TestCase
{
    #[Test]
    public function it_splits_legacy_kifukin_resident_tax_keys(): void
    {
        $normalizer = new PayloadNormalizer();

        $payload = $normalizer->normalize([
            'juminzei_zeigakukojo_furusato_prev' => '18',
        ]);

        $this->assertArrayNotHasKey('juminzei_zeigakukojo_furusato_prev', $payload);
        $this->assertSame(0, $payload['juminzei_zeigakukojo_pref_furusato_prev']);
        $this->assertSame(18, $payload['juminzei_zeigakukojo_muni_furusato_prev']);
    }

    #[Test]
    public function it_keeps_split_values_when_present(): void
    {
        $normalizer = new PayloadNormalizer();

        $payload = $normalizer->normalize([
            'juminzei_zeigakukojo_pref_npo_curr' => '12',
            'juminzei_zeigakukojo_muni_npo_curr' => '8',
            'juminzei_zeigakukojo_npo_curr' => '999',
        ]);

        $this->assertSame(12, $payload['juminzei_zeigakukojo_pref_npo_curr']);
        $this->assertSame(8, $payload['juminzei_zeigakukojo_muni_npo_curr']);
        $this->assertArrayNotHasKey('juminzei_zeigakukojo_npo_curr', $payload);
    }
}