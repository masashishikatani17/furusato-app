<?php

namespace App\Console\Commands\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyBankTransferOverdueCommand extends Command
{
    protected $signature = 'billing:notify-bank-transfer-overdue {--date=} {--limit=500}';
    protected $description = 'Notify admin when bank-transfer renewal invoice remains unpaid at term_end-2 days.';

    public function handle(): int
    {
        $tz = 'Asia/Tokyo';
        $base = $this->option('date')
            ? Carbon::parse((string)$this->option('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $targetTermEnd = $base->copy()->addDays(2)->toDateString();
        $limit = max(1, min(5000, (int)$this->option('limit')));

        $subs = Subscription::query()
            ->where('status', 'active')
            ->where('payment_method', 'bank_transfer')
            ->whereDate('term_end', $targetTermEnd)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $to = (string) env('BILLING_ALERT_TO', '');
        if ($to === '') {
            $this->warn('no recipient configured. set BILLING_ALERT_TO');
            return 0;
        }

        $notified = 0;
        foreach ($subs as $sub) {
            $nextStart = Carbon::parse((string)$sub->term_end, $tz)->addDay()->toDateString();

            $unpaid = SubscriptionInvoice::query()
                ->where('subscription_id', (int)$sub->id)
                ->where('kind', 'renewal')
                ->whereDate('period_start', $nextStart)
                ->where('status', '!=', 'paid')
                ->where('status', '!=', 'canceled')
                ->exists();

            if (!$unpaid) {
                continue;
            }

            Mail::raw(
                "[Furusato] 銀行振込の更新請求が未入金です\ncompany_id={$sub->company_id}\nsubscription_id={$sub->id}\nterm_end={$sub->term_end}",
                function ($m) use ($to) {
                    $m->to($to)->subject('[Furusato] 銀行振込の更新請求未入金通知');
                }
            );
            $notified++;
        }

        $this->info('notified=' . $notified);
        return 0;
    }
}
