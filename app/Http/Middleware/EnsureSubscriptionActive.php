<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $companyId = (int)($user->company_id ?? 0);
        if ($companyId <= 0) {
            return $this->deny($request);
        }

        $sub = Subscription::query()->where('company_id', $companyId)->first();
        if (!$sub) {
            return $this->deny($request);
        }

        $status = (string)$sub->status;
        if ($status !== 'active') {
            return $this->deny($request);
        }

        $now = Carbon::now('Asia/Tokyo');
        $paidThroughEnd = $sub->paid_through
            ? Carbon::parse((string) $sub->paid_through, 'Asia/Tokyo')->endOfDay()
            : null;

        // 仕様: paid_through の 23:59:59 までは利用可、翌日 0:00:00 から利用不可。
        if ($paidThroughEnd !== null && $now->lessThanOrEqualTo($paidThroughEnd)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    private function deny(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Subscription is not active.'], 402);
        }

        return redirect()->route('subscription.suspended');
    }
}