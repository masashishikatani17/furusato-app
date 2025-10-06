<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 固定ユーザー: 何度実行しても重複しないように updateOrCreate を使用
        // パスワードは SEED_USER_PASSWORD 環境変数があればそれを採用、なければ "password"
        $seedEmail = 'test@example.com';
        $seedName  = 'Test User';
        $seedPass  = env('SEED_USER_PASSWORD', 'password');
        User::updateOrCreate(
            ['email' => $seedEmail], // 検索キー（ユニーク）
            [
                'name'              => $seedName,
                'email_verified_at' => now(),
                'password'          => Hash::make($seedPass),
                // remember_token / 2FA 系は nullable なので省略可
            ]
        );

        // 追加のダミーユーザーが必要なら Factory を使う（Factory 側は通常 unique な email を生成）
        // 例: 合計 10 件になるよう不足分だけ補充（任意）
        /*
        $need = max(0, 10 - User::count());
        if ($need > 0) {
            User::factory($need)->create();
        }
        */
        $this->call([
            RoleSeeder::class,
            FurusatoMasterSeeder::class,
        ]);
    }
}
