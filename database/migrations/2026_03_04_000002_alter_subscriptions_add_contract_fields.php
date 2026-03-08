<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 既存 subscriptions を「1社=1行の契約SoT」に拡張する。
     * 既存列（seats_per_subscription 等）は削除せず互換のため残す。
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'quantity')) {
                $table->integer('quantity')->default(1)->after('seats_per_subscription');
            }
            if (!Schema::hasColumn('subscriptions', 'term_start')) {
                $table->date('term_start')->nullable()->after('quantity')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'term_end')) {
                $table->date('term_end')->nullable()->after('term_start')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'paid_through')) {
                $table->date('paid_through')->nullable()->after('term_end')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'payment_method')) {
                $table->string('payment_method', 32)->nullable()->after('paid_through')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'billing_code')) {
                $table->string('billing_code', 64)->nullable()->after('payment_method')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('billing_code')->index();
            }

            // 期末反映（解約/口数削減）
            if (!Schema::hasColumn('subscriptions', 'quantity_next')) {
                $table->integer('quantity_next')->nullable()->after('last_synced_at');
            }
            if (!Schema::hasColumn('subscriptions', 'cancel_at_term_end')) {
                $table->boolean('cancel_at_term_end')->default(false)->after('quantity_next')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('cancel_at_term_end')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach ([
                'quantity',
                'term_start',
                'term_end',
                'paid_through',
                'payment_method',
                'billing_code',
                'last_synced_at',
                'quantity_next',
                'cancel_at_term_end',
                'requested_at',
            ] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};