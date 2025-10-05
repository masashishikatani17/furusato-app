<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furusato_inputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('data_id')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('group_id');
            $table->longText('payload');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'group_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `furusato_inputs` ADD CONSTRAINT `furusato_inputs_payload_json_check` CHECK (JSON_VALID(`payload`))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('furusato_inputs');
    }
};