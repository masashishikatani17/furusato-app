<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furusato_inputs', function (Blueprint $table): void {
            $table->string('fudosan_keihi_label_01', 64)->nullable()->after('payload');
            $table->string('fudosan_keihi_label_02', 64)->nullable()->after('fudosan_keihi_label_01');
            $table->string('fudosan_keihi_label_03', 64)->nullable()->after('fudosan_keihi_label_02');
            $table->string('fudosan_keihi_label_04', 64)->nullable()->after('fudosan_keihi_label_03');
            $table->string('fudosan_keihi_label_05', 64)->nullable()->after('fudosan_keihi_label_04');
            $table->string('fudosan_keihi_label_06', 64)->nullable()->after('fudosan_keihi_label_05');
            $table->string('fudosan_keihi_label_07', 64)->nullable()->after('fudosan_keihi_label_06');
            $table->string('jigyo_eigyo_keihi_label_01', 64)->nullable()->after('fudosan_keihi_label_07');
            $table->string('jigyo_eigyo_keihi_label_02', 64)->nullable()->after('jigyo_eigyo_keihi_label_01');
            $table->string('jigyo_eigyo_keihi_label_03', 64)->nullable()->after('jigyo_eigyo_keihi_label_02');
            $table->string('jigyo_eigyo_keihi_label_04', 64)->nullable()->after('jigyo_eigyo_keihi_label_03');
            $table->string('jigyo_eigyo_keihi_label_05', 64)->nullable()->after('jigyo_eigyo_keihi_label_04');
            $table->string('jigyo_eigyo_keihi_label_06', 64)->nullable()->after('jigyo_eigyo_keihi_label_05');
            $table->string('jigyo_eigyo_keihi_label_07', 64)->nullable()->after('jigyo_eigyo_keihi_label_06');
        });
    }

    public function down(): void
    {
        Schema::table('furusato_inputs', function (Blueprint $table): void {
            $table->dropColumn([
                'fudosan_keihi_label_01',
                'fudosan_keihi_label_02',
                'fudosan_keihi_label_03',
                'fudosan_keihi_label_04',
                'fudosan_keihi_label_05',
                'fudosan_keihi_label_06',
                'fudosan_keihi_label_07',
                'jigyo_eigyo_keihi_label_01',
                'jigyo_eigyo_keihi_label_02',
                'jigyo_eigyo_keihi_label_03',
                'jigyo_eigyo_keihi_label_04',
                'jigyo_eigyo_keihi_label_05',
                'jigyo_eigyo_keihi_label_06',
                'jigyo_eigyo_keihi_label_07',
            ]);
        });
    }
};