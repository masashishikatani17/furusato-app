<?php

namespace App\Services\License;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SeatService
{
    protected static ?bool $usersHasIsActive = null;
    protected static ?bool $invitationTableExists = null;
    protected static ?string $invitationTable = null;
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

        $query = UserInvitation::query()->where('company_id', $companyId);

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

    /**
     * 席数の利用状況を配列で返す。
     *
     * @return array{active:int,reserved:int,total:int}
     */
    public function getSeatUsage(int $companyId): array
    {
        $activeUsers = $this->getActiveUsers($companyId)->count();
        $reserved = $this->countPendingInvites($companyId);

        return [
            'active' => $activeUsers,
            'reserved' => $reserved,
            'total' => $activeUsers + $reserved,
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

        if ($usage['total'] + $additional > $seatLimit) {
            throw new RuntimeException('Seat limit exceeded.');
        }
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
        if (! class_exists(UserInvitation::class)) {
            return false;
        }

        if (static::$invitationTableExists === null) {
            $model = new UserInvitation();
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
}