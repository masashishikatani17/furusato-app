<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'syori_mode_prev' => ['type' => 'string', 'after' => 'payload'],
            'syori_mode_curr' => ['type' => 'string', 'after' => 'syori_mode_prev'],
            'shotokuwari_zeiritsu_prev' => ['type' => 'decimal', 'after' => 'syori_mode_curr'],
            'shotokuwari_zeiritsu_curr' => ['type' => 'decimal', 'after' => 'shotokuwari_zeiritsu_prev'],
            'kintowari_prev' => ['type' => 'unsignedInteger', 'after' => 'shotokuwari_zeiritsu_curr'],
            'kintowari_curr' => ['type' => 'unsignedInteger', 'after' => 'kintowari_prev'],
            'sonota_zeiritsu_prev' => ['type' => 'decimal', 'after' => 'kintowari_curr'],
            'sonota_zeiritsu_curr' => ['type' => 'decimal', 'after' => 'sonota_zeiritsu_prev'],
        ];

        foreach ($columns as $column => $meta) {
            if (Schema::hasColumn('furusato_syori_settings', $column)) {
                continue;
            }

            Schema::table('furusato_syori_settings', function (Blueprint $table) use ($column, $meta): void {
                $after = $meta['after'];

                switch ($meta['type']) {
                    case 'string':
                        $table->string($column, 32)->nullable()->after($after);
                        break;
                    case 'decimal':
                        $table->decimal($column, 5, 2)->nullable()->after($after);
                        break;
                    case 'unsignedInteger':
                        $table->unsignedInteger($column)->nullable()->after($after);
                        break;
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('furusato_syori_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'syori_mode_prev',
                'syori_mode_curr',
                'shotokuwari_zeiritsu_prev',
                'shotokuwari_zeiritsu_curr',
                'kintowari_prev',
                'kintowari_curr',
                'sonota_zeiritsu_prev',
                'sonota_zeiritsu_curr',
            ]);
        });
    }
};