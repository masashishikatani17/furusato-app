<?php
namespace Tests\Feature;

use Tests\TestCase;

final class DonationOverviewTest extends TestCase
{
    /** @test */
    public function donation_overview_is_calculated_from_input_rates(): void
    {
        $payload = [
            'w17' => 2_000_000,
            'w18' => 3_000_000,
            'ab6' => 300_000,
            'ab56' => 10_000,
            'v6' => 0,
            'w6' => 1,
            'x6' => 2,
            'q2' => 0.30,
            'q3' => 0.25,
            'q4' => 0.20,
            'q5' => 0.15,
        ];

        $response = $this->post('/furusato/calc', $payload);

        $response->assertStatus(200);
        $response->assertViewHas('donation');

        $donation = $response->viewData('donation');
        $this->assertIsArray($donation);
        $this->assertArrayHasKey('rows', $donation);
        $this->assertCount(4, $donation['rows']);

        $expected = [
            ['q' => 0.30, 's' => 0.30 * 1.021, 'u' => 1.0 - 0.10 - 0.30 * 1.021],
            ['q' => 0.25, 's' => 0.25 * 1.021, 'u' => 1.0 - 0.10 - 0.25 * 1.021],
            ['q' => 0.20, 's' => 0.20 * 1.021, 'u' => 1.0 - 0.10 - 0.20 * 1.021],
            ['q' => 0.15, 's' => 0.15 * 1.021, 'u' => 1.0 - 0.10 - 0.15 * 1.021],
        ];

        foreach ($expected as $index => $row) {
            $actual = $donation['rows'][$index];
            $this->assertEqualsWithDelta($row['q'], $actual['q'], 1e-6);
            $this->assertEqualsWithDelta($row['s'], $actual['s'], 1e-6);
            $this->assertEqualsWithDelta($row['u'], $actual['u'], 1e-6);
        }
    }
}
