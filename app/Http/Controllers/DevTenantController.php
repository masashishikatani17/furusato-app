<?php

namespace App\Http\Controllers;

use App\Services\License\SeatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DevTenantController extends Controller
{
    public function whoami(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $company = $user->company;

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company_id' => $user->company_id,
            'group_id' => $user->group_id,
            'role' => $user->role,
            'display_role' => $user->display_role ?? null,
            'is_active' => (bool) $user->is_active,
            'is_owner' => method_exists($user, 'isOwner')
                ? $user->isOwner()
                : optional($company)->owner_user_id === $user->id,
            'company_owner_user_id' => optional($company)->owner_user_id,
        ];

        if (method_exists($user, 'getRoleNames')) {
            $data['roles'] = $user->getRoleNames()->toArray();
        }

        if (method_exists($user, 'getAllPermissions')) {
            $data['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
        }

        if ($company) {
            $data['company'] = [
                'id' => $company->id,
                'name' => $company->name ?? null,
                'owner_user_id' => $company->owner_user_id,
            ];
        }

        return response()->json($data);
    }

    public function seats(SeatService $svc): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $companyId = $user->company_id;

        if (! $companyId) {
            return response()->json(['message' => 'Company is not assigned.'], 404);
        }

        $seatUsage = $svc->getSeatUsage((int) $companyId);

        if (isset($seatUsage['active_seats'], $seatUsage['active_users'], $seatUsage['pending_invites'], $seatUsage['remaining'])) {
            return response()->json($seatUsage);
        }

        $activeSeats = (int) ($seatUsage['active_seats'] ?? $seatUsage['total'] ?? 0);
        $activeUsers = (int) ($seatUsage['active_users'] ?? $seatUsage['active'] ?? 0);
        $pendingInvites = (int) ($seatUsage['pending_invites'] ?? $seatUsage['reserved'] ?? 0);

        $normalized = [
            'active_seats' => $activeSeats,
            'active_users' => $activeUsers,
            'pending_invites' => $pendingInvites,
        ];

        $normalized['remaining'] = (int) ($seatUsage['remaining'] ?? max(0, $activeSeats - ($activeUsers + $pendingInvites)));

        return response()->json($normalized);
    }
}