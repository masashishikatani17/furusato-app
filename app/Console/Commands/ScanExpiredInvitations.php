<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Support\AuditLogger;
use Illuminate\Console\Command;

class ScanExpiredInvitations extends Command
{
    protected $signature = 'invitations:expire-scan';
    protected $description = 'Scan invitations and mark/log those expired (one-time via expired_at).';

    public function handle(): int
    {
        $now = now();

        Invitation::query()
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->whereNull('revoked_at')
            ->whereNull('deleted_at')
            ->whereNull('expired_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now) {
                foreach ($rows as $inv) {
                    try {
                        // 二重防止：expired_at を SoT にして1回だけ確定
                        $inv->expired_at = $now;
                        $inv->save();

                        if (class_exists(AuditLogger::class)) {
                            AuditLogger::log('invitation.expired', [
                                'invitation_id' => (int)$inv->id,
                                'company_id' => (int)$inv->company_id,
                                'email' => (string)$inv->email,
                                'role' => (string)$inv->role,
                                'guest_id' => $inv->guest_id ?? null,
                                'expires_at' => optional($inv->expires_at)->toDateTimeString(),
                                'expired_at' => optional($inv->expired_at)->toDateTimeString(),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('[InvitationsExpireScan] failed', [
                            'invitation_id' => $inv->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info('ok');
        return self::SUCCESS;
    }
}
