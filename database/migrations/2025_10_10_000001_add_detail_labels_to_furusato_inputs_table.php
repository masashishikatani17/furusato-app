<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'fudosan_keihi_label_01' => 'payload',
            'fudosan_keihi_label_02' => 'fudosan_keihi_label_01',
            'fudosan_keihi_label_03' => 'fudosan_keihi_label_02',
            'fudosan_keihi_label_04' => 'fudosan_keihi_label_03',
            'fudosan_keihi_label_05' => 'fudosan_keihi_label_04',
            'fudosan_keihi_label_06' => 'fudosan_keihi_label_05',
            'fudosan_keihi_label_07' => 'fudosan_keihi_label_06',
            'jigyo_eigyo_keihi_label_01' => 'fudosan_keihi_label_07',
            'jigyo_eigyo_keihi_label_02' => 'jigyo_eigyo_keihi_label_01',
            'jigyo_eigyo_keihi_label_03' => 'jigyo_eigyo_keihi_label_02',
            'jigyo_eigyo_keihi_label_04' => 'jigyo_eigyo_keihi_label_03',
            'jigyo_eigyo_keihi_label_05' => 'jigyo_eigyo_keihi_label_04',
            'jigyo_eigyo_keihi_label_06' => 'jigyo_eigyo_keihi_label_05',
            'jigyo_eigyo_keihi_label_07' => 'jigyo_eigyo_keihi_label_06',
        ];

        foreach ($columns as $column => $after) {
            if (! Schema::hasColumn('furusato_inputs', $column)) {
                Schema::table('furusato_inputs', function (Blueprint $table) use ($column, $after): void {
                    $table->string($column, 64)->nullable()->after($after);
                });
            }
        }
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