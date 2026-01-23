<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * 監査ログをDBへ保存
     *
     * @param string $action 例: data.created / data.copied / data.overwritten / data.deleted
     * @param array<string,mixed> $meta
     * @param object|null $subject Eloquentモデル等（subject_type/subject_id用）
     */
    public static function log(string $action, array $meta = [], ?object $subject = null): void
    {
        try {
            $actor = Auth::user();
            $companyId = (int)($actor->company_id ?? 0) ?: null;
            $actorId = (int)($actor->id ?? 0) ?: null;

            AuditLog::create([
                'company_id'   => $companyId,
                'actor_user_id'=> $actorId,
                'action'       => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject && isset($subject->id) ? (int)$subject->id : null,
                'meta'         => $meta,
                'ip'           => request()?->ip(),
                'user_agent'   => (string)(request()?->userAgent() ?? ''),
            ]);
        } catch (\Throwable $e) {
            // 監査ログでアプリを落とさない
            \Log::warning('[AuditLogger] failed', ['action'=>$action, 'error'=>$e->getMessage()]);
        }
    }
}