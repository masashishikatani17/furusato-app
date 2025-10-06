<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jumin_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('kihu_year');
            $table->unsignedInteger('version');
            $table->unsignedInteger('seq');
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->decimal('city_specified', 6, 3);
            $table->decimal('pref_specified', 6, 3);
            $table->decimal('city_non_specified', 6, 3);
            $table->decimal('pref_non_specified', 6, 3);
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'kihu_year', 'version', 'category', 'sub_category', 'seq'],
                'jumin_rates_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jumin_rates');
    }
};