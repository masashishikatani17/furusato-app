<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datas')) {
            // テーブルが無い環境では何もしない（先に create_datas_table_if_missing を実行）
            return;
        }
        Schema::table('datas', function (Blueprint $table) {
            if (!Schema::hasColumn('datas', 'kihu_year')) {
                $table->integer('kihu_year')->nullable()->after('date')
                      ->comment('寄付年(YYYY)。furusato用');
            }
        });
        // ユニークインデックス（NULL は複数可：既存NULLは後日補正）
        try {
            Schema::table('datas', function (Blueprint $table) {
                $table->unique(['guest_id','kihu_year'], 'datas_guest_year_unique');
            });
        } catch (\Throwable $e) {
            // 既に存在する等の例外は無視して冪等に
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('datas')) return;
        Schema::table('datas', function (Blueprint $table) {
            // ユニーク外す（列は残す：ダウングレード時のデータ保護）
            try { $table->dropUnique('datas_guest_year_unique'); } catch (\Throwable $e) {}
        });
        // 列は残す運用（必要ならコメントアウト解除で削除可）
        // Schema::table('datas', function (Blueprint $table) {
        //     if (Schema::hasColumn('datas', 'kihu_year')) {
        //         $table->dropColumn('kihu_year');
        //     }
        // });
    }
};
