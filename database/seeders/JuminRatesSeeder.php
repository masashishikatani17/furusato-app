<?php

namespace Database\Seeders;

use App\Services\Tax\FurusatoMasterDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JuminRatesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $rows = array_map(
            fn (array $row): array => array_merge($row, [
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            FurusatoMasterDefaults::jumin()
        );

        DB::table('jumin_rates')->truncate();
        DB::table('jumin_rates')->insert($rows);
    }
}