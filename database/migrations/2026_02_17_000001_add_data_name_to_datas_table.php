<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datas', function (Blueprint $table) {
            if (!Schema::hasColumn('datas', 'data_name')) {
                // 最大長：25（仕様）
                $table->string('data_name', 25)->nullable()->after('kihu_year');
            }
        });

        // 既存データは default を付与（既存は同一年1件の想定なので衝突しない）
        DB::table('datas')
            ->whereNull('data_name')
            ->update(['data_name' => 'default']);

        // 念のため：空文字も default に寄せる（入力どおり保存が原則だが、既存移行の保険）
        DB::table('datas')
            ->where('data_name', '')
            ->update(['data_name' => 'default']);

        // NOT NULL 化（安全のため data_name を必須にする）
        Schema::table('datas', function (Blueprint $table) {
            // MariaDB 10.5 / Laravel 10 では change() に doctrine が必要な場合があるため、
            // nullable のままでも運用は可能だが、アプリ側で必須にするのでここは nullable のまま残す。
            // （確実に動かす方針）
        });
    }

    public function down(): void
    {
        Schema::table('datas', function (Blueprint $table) {
            if (Schema::hasColumn('datas', 'data_name')) {
                $table->dropColumn('data_name');
            }
        });
    }
};
