<?php

namespace App\Services\License;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SeatService
{
    protected static ?bool $usersHasIsActive = null;
    protected static ?bool $invitationTableExists = null;
    protected static ?string $invitationTable = null;
    protected static ?string $invitationModelClass = null;
    protected static bool $invitationModelChecked = false;
    /** @var array<string, bool> */
    protected static array $invitationColumnCache = [];

    /**
     * 会社に属する有効ユーザー（client 以外）を返却。
     */
    public function getActiveUsers(int $companyId): EloquentCollection
    {
        $query = User::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhere('role', '!=', 'client');
            });

        if ($this->usersTableHasIsActive()) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * 招待中（未承諾・未失効）のユーザー数を取得。
     */
    public function countPendingInvites(int $companyId): int
    {
        if (! $this->invitationModelAvailable()) {
            return 0;
        }

        $modelClass = static::$invitationModelClass;
        $query = $modelClass::query()->where('company_id', $companyId);

        if ($this->invitationTableHasColumn('accepted_at')) {
            $query->whereNull('accepted_at');
        }

        if ($this->invitationTableHasColumn('expires_at')) {
            $query->where(function ($inner) {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }

        foreach (['cancelled_at', 'revoked_at', 'deleted_at'] as $column) {
            if ($this->invitationTableHasColumn($column)) {
                $query->whereNull($column);
            }
        }

        $query->where(function ($inner) {
            $inner->whereNull('role')->orWhere('role', '!=', 'client');
        });

        return (int) $query->count();
    }

    public function getActiveSeats(int $companyId): int
    {
        // 1社=1subscription 前提：quantity（契約本数）を SoT とする
        $sub = Subscription::query()
            ->where('company_id', $companyId)
            ->first();
        if (!$sub) {
            return 0;
        }
        if ((string)$sub->status !== 'active') {
            return 0;
        }
        // 契約本数(quantity)を正本とし、5席/契約で算出
        $limit = max(0, (int)($sub->quantity ?? 0) * 5);
        // 互換：旧データ保険
        if ($limit <= 0) {
            $limit = (int)($sub->seat_limit ?? 0);
        }
        if ($limit <= 0) {
            $limit = (int)($sub->seats_per_subscription ?? 0);
        }
        return max(0, $limit);
    }

    /**
     * 席数の利用状況を配列で返す。
     *
     * @return array{active_seats:int,active_users:int,pending_invites:int,remaining:int}
     */
    public function getSeatUsage(int $companyId): array
    {
        $activeUsers = $this->getActiveUsers($companyId)->count();
        $pending = $this->countPendingInvites($companyId);
        $seats = $this->getActiveSeats($companyId);

        return [
            'active_seats' => $seats,
            'active_users' => $activeUsers,
            'pending_invites' => $pending,
            'remaining' => max(0, $seats - ($activeUsers + $pending)),
        ];
    }

    /**
     * summaryFor エイリアス（既存コード互換）。
     */
    public function summaryFor(int $companyId): array
    {
        return $this->getSeatUsage($companyId);
    }

    /**
     * 席追加の検証（在籍＋予約＋追加分 <= 上限）。
     */
    public function assertCanAddUsers(int $companyId, int $seatLimit, int $additional = 1): void
    {
        if ($seatLimit < 0) {
            return;
        }

        $usage = $this->getSeatUsage($companyId);

        $need = $usage['active_users'] + $usage['pending_invites'] + $additional;
        if ($need > $seatLimit) {
            $suggest = $this->suggestPlanForHeadcount($need);
            $msg = 'Seat limit exceeded.'
                . ' need=' . $need
                . ' current_limit=' . $seatLimit
                . ' suggest_plan=' . ($suggest['label'] ?? 'n/a');
            throw new RuntimeException($msg);
        }
    }

    /**
     * 必要人数から、次に必要なプランを返す（表示用）
     * - label はUI表示に使う
     *
     * @return array{code:string,label:string,seat_limit:int,price_yen:int}
     */
    public function suggestPlanForHeadcount(int $headcount): array
    {
        // 4プラン固定
        $plans = [
            ['code' => 'p5',  'label' => '5人以下（年額3万円）',    'seat_limit' => 5,  'price_yen' => 30000],
            ['code' => 'p10', 'label' => '6〜10人（年額6万円）',  'seat_limit' => 10, 'price_yen' => 60000],
            ['code' => 'p20', 'label' => '11〜20人（年額9万円）', 'seat_limit' => 20, 'price_yen' => 90000],
            // 21人以上：上限は実務上「十分大きい値」
            ['code' => 'p21', 'label' => '21人以上（年額12万円）', 'seat_limit' => 9999, 'price_yen' => 120000],
        ];
        foreach ($plans as $p) {
            if ($headcount <= (int)$p['seat_limit']) {
                return $p;
            }
        }
        return $plans[count($plans) - 1];
    }

    /**
     * 招待時の検証（座席を1件予約）。
     */
    public function assertCanInvite(int $companyId, int $seatLimit, int $invites = 1): void
    {
        $this->assertCanAddUsers($companyId, $seatLimit, $invites);
    }

    protected function usersTableHasIsActive(): bool
    {
        if (static::$usersHasIsActive === null) {
            static::$usersHasIsActive = Schema::hasColumn('users', 'is_active');
        }

        return static::$usersHasIsActive;
    }

    protected function invitationModelAvailable(): bool
    {
        $this->resolveInvitationModelClass();

        if (static::$invitationModelClass === null) {
            return false;
        }

        if (static::$invitationTableExists === null) {
            $modelClass = static::$invitationModelClass;
            $model = new $modelClass();
            $table = $model->getTable();
            static::$invitationTable = $table;
            static::$invitationTableExists = Schema::hasTable($table);
        }

        return static::$invitationTableExists === true;
    }

    protected function invitationTableHasColumn(string $column): bool
    {
        if (! $this->invitationModelAvailable()) {
            return false;
        }

        if (! array_key_exists($column, static::$invitationColumnCache)) {
            static::$invitationColumnCache[$column] = Schema::hasColumn(static::$invitationTable, $column);
        }

        return static::$invitationColumnCache[$column];
    }

    protected function resolveInvitationModelClass(): void
    {
        if (static::$invitationModelChecked) {
            return;
        }

        $candidates = [
            'App\\Models\\Invitation',
            'App\\Models\\UserInvitation',
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                static::$invitationModelClass = $candidate;
                break;
            }
        }

        static::$invitationModelChecked = true;
    }
}