<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $actor = Auth::user();
        $companyId = $actor->company_id;
        $role = strtolower((string) ($actor->role ?? 'member'));
        $isOwner = method_exists($actor, 'isOwner') && $actor->isOwner();
        $isRegistrar = ($role === 'registrar');
        $limitToGroup = ! ($isOwner || $isRegistrar);

        $users = User::query()
            ->where('users.company_id', $companyId)
            ->when($limitToGroup, fn ($q) => $q->where('users.group_id', $actor->group_id))
            ->leftJoin('groups', 'groups.id', '=', 'users.group_id')
            ->select('users.*', DB::raw('groups.name as group_name'))
            ->orderBy('users.id')
            ->paginate(20);

        $seatSvc = app(\App\Services\License\SeatService::class);
        $seatUsage = (array) $seatSvc->getSeatUsage($companyId);

        $companyOwnerId = (int) DB::table('companies')->where('id', $companyId)->value('owner_user_id');
        $invitations = [];

        return view('admin.users.index', compact('users', 'seatUsage', 'invitations', 'companyOwnerId'));
    }
}