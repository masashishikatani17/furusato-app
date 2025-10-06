<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shinkokutokurei_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('kihu_year');
            $table->unsignedInteger('version');
            $table->unsignedInteger('seq');
            $table->unsignedBigInteger('lower');
            $table->unsignedBigInteger('upper')->nullable();
            $table->decimal('ratio_a', 7, 3);
            $table->decimal('ratio_b', 7, 3);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'kihu_year', 'version', 'seq'], 'shinkokutokurei_rates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shinkokutokurei_rates');
    }
};