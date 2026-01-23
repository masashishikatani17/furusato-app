<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();

            $table->string('action', 120)->index();               // data.created / data.copied / data.overwritten / data.deleted ...
            $table->string('subject_type', 120)->nullable()->index(); // App\Models\Data 等
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            $table->json('meta')->nullable();                     // from/to 等の付帯情報
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps(); // created_at を監査日時として利用

            $table->index(['company_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
