<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscriptions')) {
            return;
        }
        if (!Schema::hasColumn('subscriptions', 'company_id') || !Schema::hasColumn('subscriptions', 'id')) {
            return;
        }

        // 1社=1subscription に揃えるため、company_id 重複があれば「最新(id最大)だけ残して削除」
        $dupCompanyIds = DB::table('subscriptions')
            ->select('company_id')
            ->groupBy('company_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('company_id')
            ->all();

        foreach ($dupCompanyIds as $companyId) {
            $keepId = DB::table('subscriptions')
                ->where('company_id', (int)$companyId)
                ->max('id');

            DB::table('subscriptions')
                ->where('company_id', (int)$companyId)
                ->where('id', '!=', (int)$keepId)
                ->delete();
        }
    }

    public function down(): void
    {
        // 破壊的クリーンアップのためロールバックは不要（復元不能）
    }
};
