<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // 料金プラン（4段階）
            if (!Schema::hasColumn('subscriptions', 'plan_code')) {
                $table->string('plan_code')->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'seat_limit')) {
                // seats_per_subscription は既存のまま残しつつ、今後は seat_limit を SoT にする
                $table->integer('seat_limit')->default(5)->after('seats_per_subscription');
            }
            if (!Schema::hasColumn('subscriptions', 'price_yen')) {
                $table->integer('price_yen')->default(30000)->after('seat_limit');
            }

            // 入金完了必須：支払済み期限（この日付までOK）
            if (!Schema::hasColumn('subscriptions', 'paid_through')) {
                $table->date('paid_through')->nullable()->after('price_yen')->index();
            }

            // 請求管理ロボ紐付けキー（どれを使うかは運用で調整）
            if (!Schema::hasColumn('subscriptions', 'billing_robo_billing_source_id')) {
                $table->string('billing_robo_billing_source_id')->nullable()->after('paid_through')->index();
            }
            if (!Schema::hasColumn('subscriptions', 'billing_robo_billing_code')) {
                $table->string('billing_robo_billing_code')->nullable()->after('billing_robo_billing_source_id')->index();
            }

            // 最終同期時刻（API同期の診断用）
            if (!Schema::hasColumn('subscriptions', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('billing_robo_billing_code');
            }
        });

        // 1社=1行を保証
        Schema::table('subscriptions', function (Blueprint $table) {
            // 既に unique がある場合は例外になるので、事前にhasIndex的な確認ができない環境もあるため try-catch で吸収
            try {
                $table->unique('company_id', 'subscriptions_company_id_unique');
            } catch (\Throwable $e) {
                // noop
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            try {
                $table->dropUnique('subscriptions_company_id_unique');
            } catch (\Throwable $e) {
                // noop
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach ([
                'plan_code',
                'seat_limit',
                'price_yen',
                'paid_through',
                'billing_robo_billing_source_id',
                'billing_robo_billing_code',
                'last_synced_at',
            ] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
