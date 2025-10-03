<?php
namespace Tests\Feature;

use Tests\TestCase;

final class FurusatoInputFlagsTest extends TestCase
{
    /** @test */
    public function flags_flow_from_input_to_service_response(): void
    {
        $payload = [
            'w17' => 2_000_000,
            'w18' => 3_000_000,
            'ab6' => 300_000,
            'ab56' => 10_000,
            'v6' => 1,
            'w6' => 2,
            'x6' => 0,
        ];

        $response = $this->post('/furusato/calc', $payload);

        $response->assertStatus(200);
        $response->assertViewHas('out', function ($out) use ($payload) {
            if (! isset($out['flags'])) {
                return false;
            }

            return $out['flags'] === [
                'v6' => $payload['v6'],
                'w6' => $payload['w6'],
                'x6' => $payload['x6'],
            ];
        });
    }
}
