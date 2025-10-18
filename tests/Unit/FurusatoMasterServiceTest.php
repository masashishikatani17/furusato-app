<?php

namespace Tests\Unit;

use App\Services\Tax\FurusatoMasterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class FurusatoMasterServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tokurei_rates_include_note_on_each_object(): void
    {
        $service = $this->app->make(FurusatoMasterService::class);

        $rates = $service->getTokureiRates(2025, null);

        $this->assertInstanceOf(Collection::class, $rates);
        $this->assertNotEmpty($rates);

        $rates->each(function ($rate): void {
            $this->assertIsObject($rate);
            $this->assertTrue(property_exists($rate, 'note'));
            $this->assertIsString($rate->note);
        });
    }

    /** @test */
    public function jumin_rates_are_returned_as_objects(): void
    {
        $service = $this->app->make(FurusatoMasterService::class);

        $rates = $service->getJuminRates(2025, null);

        $this->assertInstanceOf(Collection::class, $rates);
        $this->assertNotEmpty($rates);

        $rates->each(function ($rate): void {
            $this->assertIsObject($rate);
            $this->assertTrue(property_exists($rate, 'category'));
            $this->assertIsString($rate->category);
        });
    }
}