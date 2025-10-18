<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shotoku_rates');

        Schema::create('shotoku_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('year');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('lower');
            $table->unsignedBigInteger('upper')->nullable();
            $table->decimal('rate', 6, 3);
            $table->unsignedBigInteger('deduction_amount');
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'year', 'sort'], 'st_main');
            $table->index('year', 'st_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shotoku_rates');
    }
};