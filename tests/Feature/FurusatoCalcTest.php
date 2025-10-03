<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Domain\Tax\Services\FurusatoCalcService;
use App\Domain\Tax\DTO\FurusatoInput;

final class FurusatoCalcTest extends TestCase
{
    /** @test */
    public function calc_upper_limit_matches_examples()
    {
        $svc = new FurusatoCalcService();
        $in = new FurusatoInput(w17: 2_000_000, w18: 3_000_000, ab6: 300_000, ab56: 10_000);
        $out = $svc->calcUpperLimit($in);
        $this->assertSame(2100, $out['b8']);
        $this->assertSame(2100, $out['b9']);
        $this->assertSame(122000, $out['b12']);
        $this->assertSame( 92000, $out['b13']);
        $this->assertSame(  2100, $out['b16']);
        $this->assertSame(  2100, $out['b17']);
    }
}