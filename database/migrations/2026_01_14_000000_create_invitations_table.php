<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invitations')) {
            return;
        }

        Schema::create('invitations', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('invited_by')->nullable();

            $table->string('email', 255);
            $table->string('role', 50);
            $table->string('token', 100)->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
