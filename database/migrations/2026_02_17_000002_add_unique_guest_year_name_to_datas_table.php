<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 既に data_name が入っている前提だが、保険で default を補完
        DB::table('datas')->whereNull('data_name')->update(['data_name' => 'default']);
        DB::table('datas')->where('data_name', '')->update(['data_name' => 'default']);

        // 念のため重複クリーンアップ：
        // (guest_id, kihu_year, data_name) が万一重複していたら、古いものを残して他を suffix で退避
        // ※仕様上は発生しない想定だが、ユニーク作成で落ちないようにする保険
        $dups = DB::table('datas')
            ->select('guest_id', 'kihu_year', 'data_name', DB::raw('COUNT(*) as c'))
            ->groupBy('guest_id', 'kihu_year', 'data_name')
            ->having('c', '>', 1)
            ->get();

        foreach ($dups as $d) {
            $rows = DB::table('datas')
                ->where('guest_id', (int)$d->guest_id)
                ->where('kihu_year', (int)$d->kihu_year)
                ->where('data_name', (string)$d->data_name)
                ->orderBy('id', 'asc')
                ->get();

            $i = 1;
            foreach ($rows as $r) {
                // 先頭(最小id)はそのまま残す
                if ($i === 1) {
                    $i++;
                    continue;
                }
                // 退避：末尾に _dupN を付与（25文字上限を守る）
                $base = (string)$d->data_name;
                $suffix = '_dup' . ($i - 1);
                $new = mb_substr($base, 0, max(0, 25 - mb_strlen($suffix))) . $suffix;
                DB::table('datas')->where('id', (int)$r->id)->update(['data_name' => $new]);
                $i++;
            }
        }

        Schema::table('datas', function (Blueprint $table) {
            // 既存に近い命名
            $idx = 'uq_datas_guest_year_name';
            // すでに存在していたら落ちるので保険
            // Laravelは hasIndex が無いので try/catch で対応し、確実に通す
            try {
                $table->unique(['guest_id', 'kihu_year', 'data_name'], $idx);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('datas', function (Blueprint $table) {
            $idx = 'uq_datas_guest_year_name';
            try {
                $table->dropUnique($idx);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};