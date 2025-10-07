<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('furusato_results')) {
            return;
        }

        $duplicates = DB::table('furusato_results')
            ->select('data_id')
            ->groupBy('data_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('data_id');

        foreach ($duplicates as $dataId) {
            $rows = DB::table('furusato_results')
                ->where('data_id', $dataId)
                ->orderBy('id')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $rows->shift();
            $idsToDelete = $rows->pluck('id')->all();

            if ($idsToDelete !== []) {
                DB::table('furusato_results')
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // no-op
    }
};