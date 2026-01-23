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
            // 既存環境への再適用耐性
            if (!Schema::hasColumn('datas', 'data_created_on')) {
                $table->date('data_created_on')->nullable()->after('kihu_year');
            }
            if (!Schema::hasColumn('datas', 'proposal_date')) {
                $table->date('proposal_date')->nullable()->after('data_created_on');
            }
        });

        // ===== 既存データのバックフィル =====
        // data_created_on が NULL のものは created_at の日付で埋める（created_at が NULL の場合は当日）
        DB::table('datas')
            ->whereNull('data_created_on')
            ->whereNotNull('created_at')
            ->update(['data_created_on' => DB::raw('DATE(created_at)')]);

        DB::table('datas')
            ->whereNull('data_created_on')
            ->update(['data_created_on' => now()->toDateString()]);

        // proposal_date が NULL のものは data_created_on と同日にする
        DB::table('datas')
            ->whereNull('proposal_date')
            ->update(['proposal_date' => DB::raw('data_created_on')]);

        // ===== NOT NULL 化（Doctrine DBAL 不要のため raw で） =====
        // 既に NULL が無い前提になっているので NOT NULL にする
        // ※環境差で失敗する場合はここをコメントアウトして nullable のまま運用も可
        try {
            DB::statement("ALTER TABLE `datas` MODIFY `data_created_on` date NOT NULL");
            DB::statement("ALTER TABLE `datas` MODIFY `proposal_date` date NOT NULL");
        } catch (\Throwable $e) {
            // ローカル環境差異（権限/エンジン等）で落ちないよう保険
            // NOT NULL 化できなくてもアプリ側で必ず値は入る
        }
    }

    public function down(): void
    {
        Schema::table('datas', function (Blueprint $table) {
            if (Schema::hasColumn('datas', 'proposal_date')) {
                $table->dropColumn('proposal_date');
            }
            if (Schema::hasColumn('datas', 'data_created_on')) {
                $table->dropColumn('data_created_on');
            }
        });
    }
};