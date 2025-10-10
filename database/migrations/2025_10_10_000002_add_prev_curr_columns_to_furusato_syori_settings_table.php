<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furusato_syori_settings', function (Blueprint $table): void {
            $table->string('syori_mode_prev', 32)->nullable()->after('payload');
            $table->string('syori_mode_curr', 32)->nullable()->after('syori_mode_prev');
            $table->decimal('shotokuwari_zeiritsu_prev', 5, 2)->nullable()->after('syori_mode_curr');
            $table->decimal('shotokuwari_zeiritsu_curr', 5, 2)->nullable()->after('shotokuwari_zeiritsu_prev');
            $table->unsignedInteger('kintowari_prev')->nullable()->after('shotokuwari_zeiritsu_curr');
            $table->unsignedInteger('kintowari_curr')->nullable()->after('kintowari_prev');
            $table->decimal('sonota_zeiritsu_prev', 5, 2)->nullable()->after('kintowari_curr');
            $table->decimal('sonota_zeiritsu_curr', 5, 2)->nullable()->after('sonota_zeiritsu_prev');
        });
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