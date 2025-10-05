<?php

namespace Tests\Feature;

use App\Models\Data;
use App\Models\FurusatoInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class FurusatoSaveTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_saves_payload_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'company_id' => 101,
            'group_id' => 202,
            'role' => 'groupadmin',
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id' => 101,
            'group_id' => 202,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2024,
            'visibility' => 'private',
        ]);

        $payload = $this->makePayload($data->id, [
            'jiryo_eigyo_prev' => 1000,
            'jiryo_eigyo_curr' => 2000,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('furusato.save'), $payload);

        $response->assertRedirect(route('furusato.input', ['data_id' => $data->id], false));
        $response->assertSessionHas('success', '保存しました');

        $this->assertDatabaseHas('furusato_inputs', [
            'data_id' => $data->id,
            'company_id' => $data->company_id,
            'group_id' => $data->group_id,
        ]);

        $record = FurusatoInput::where('data_id', $data->id)->firstOrFail();
        $this->assertSame($user->id, $record->created_by);
        $this->assertSame($user->id, $record->updated_by);
        $this->assertSame(1000, Arr::get($record->payload, 'jiryo_eigyo_prev'));
        $this->assertSame(2000, Arr::get($record->payload, 'jiryo_eigyo_curr'));
        $this->assertSame($data->id, $record->data_id);

        $secondPayload = $this->makePayload($data->id, [
            'jiryo_eigyo_prev' => 3000,
            'jiryo_eigyo_curr' => 4000,
        ]);

        $secondResponse = $this->post(route('furusato.save'), $secondPayload);
        $secondResponse->assertRedirect(route('furusato.input', ['data_id' => $data->id], false));

        $this->assertSame(1, FurusatoInput::where('data_id', $data->id)->count());
        $record->refresh();
        $this->assertSame(3000, Arr::get($record->payload, 'jiryo_eigyo_prev'));
        $this->assertSame(4000, Arr::get($record->payload, 'jiryo_eigyo_curr'));
    }

    /** @test */
    public function it_blocks_group_admin_from_other_groups(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'company_id' => 500,
            'group_id' => 600,
            'role' => 'groupadmin',
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id' => 500,
            'group_id' => 601,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2024,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('furusato.save'), [
            'data_id' => $data->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('furusato_inputs', 0);
    }

    private function makePayload(int $inputDataId, array $overrides = []): array
    {
        $base = [
            'data_id' => $inputDataId,
            'jiryo_eigyo_prev' => 10,
            'jiryo_eigyo_curr' => 20,
            'jiryo_nogyo_prev' => 30,
            'jiryo_nogyo_curr' => 40,
            'shitei_toshi_flag' => 0,
        ];

        return array_replace($base, $overrides);
    }
}