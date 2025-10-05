<?php

namespace Tests\Feature;

use App\Models\Data;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FurusatoMasterViewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_allows_company_user_to_view_master(): void
    {
        $user = User::factory()->create();
        // SQLite の users テーブルに company_id 等は無い前提 → 非永続で属性を持たせる
        $user->forceFill([
            'company_id' => 1001,
            'group_id'  => 2001,
            'role'      => 'member',
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id' => 1001,
            'group_id'   => 3001,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2024,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('furusato.master', ['data_id' => $data->id], false));

        $response->assertOk();
        $response->assertSee('ふるさと納税：マスター一覧', false);
    }

    /** @test */
    public function it_forbids_other_company_to_view_master(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'company_id' => 2002,
            'group_id'  => 2003,
            'role'      => 'registrar',
        ]);

        $otherUser = User::factory()->create();
        $otherUser->forceFill([
            'company_id' => 9999,
            'group_id'  => 1003,
            'role'      => 'owner',
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id'   => 9999,
            'group_id'     => 1003,
            'user_id'      => $otherUser->id,
            'owner_user_id' => $otherUser->id,
            'kihu_year' => 2024,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('furusato.master', ['data_id' => $data->id], false));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_keeps_same_data_id_on_back_link(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'company_id' => 4500,
            'group_id'  => 4600,
            'role'      => 'owner',
        ]);

        $data = Data::create([
            'guest_id' => null,
            'company_id' => 4500,
            'group_id'   => 4601,
            'user_id' => $user->id,
            'owner_user_id' => $user->id,
            'kihu_year' => 2024,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('furusato.master', ['data_id' => $data->id], false));

        $response->assertOk();
        $response->assertSee('href="' . route('furusato.input', ['data_id' => $data->id], false) . '"', false);
    }
}