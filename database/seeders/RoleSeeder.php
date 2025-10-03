<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $member = Role::firstOrCreate(['name' => 'member']);

        // 最初のユーザーを管理者に（なければスキップ）
        if ($u = User::query()->orderBy('id')->first()) {
            $u->assignRole($admin);
        }
    }
}
