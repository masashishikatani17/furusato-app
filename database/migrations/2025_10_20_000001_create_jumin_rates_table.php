<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('jumin_rates');

        Schema::create('jumin_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('category', 64);
            $table->string('sub_category', 64)->nullable();
            $table->decimal('city_specified', 6, 3);
            $table->decimal('pref_specified', 6, 3);
            $table->decimal('city_non_specified', 6, 3);
            $table->decimal('pref_non_specified', 6, 3);
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'year', 'sort'], 'jr_main');
            $table->index('year', 'jr_year');
            $table->unique([
                'company_id',
                'year',
                'category',
                'sub_category',
                'sort',
            ], 'jr_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jumin_rates');
    }
};