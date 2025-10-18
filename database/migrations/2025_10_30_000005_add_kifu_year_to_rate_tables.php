<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'jumin_rates' => 'jr_kifu_year',
        'shinkokutokurei_rates' => 'str_kifu_year',
        'shotoku_rates' => 'sr_kifu_year',
        'tokurei_rates' => 'tr_kifu_year',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $index) {
            Schema::table($table, function (Blueprint $table) use ($index): void {
                $table->unsignedInteger('kifu_year')->nullable()->after('year');
                $table->index('kifu_year', $index);
            });

            if (DB::getDriverName() !== 'sqlite') {
                DB::statement(sprintf('ALTER TABLE `%s` MODIFY `year` INT UNSIGNED NULL', $table));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $index) {
            Schema::table($table, function (Blueprint $table) use ($index): void {
                $table->dropIndex($index);
                $table->dropColumn('kifu_year');
            });

            if (DB::getDriverName() !== 'sqlite') {
                DB::statement(sprintf('ALTER TABLE `%s` MODIFY `year` INT UNSIGNED NOT NULL', $table));
            }
        }
    }
};