<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shinkokutokurei_rates');

        Schema::create('shinkokutokurei_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('lower');
            $table->unsignedBigInteger('upper')->nullable();
            $table->decimal('ratio_a', 8, 6);
            $table->decimal('ratio_b', 8, 6);
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'year', 'sort'], 'sk_main');
            $table->index('year', 'sk_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shinkokutokurei_rates');
    }
};