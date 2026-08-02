<?php

namespace App\Services;

use App\Models\RevenueReportSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorEarningLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public function refreshLast24Months(?User $actor = null): Collection
    {
        return DB::transaction(function () use ($actor): Collection {
            $start = now()->subMonthsNoOverflow(23)->startOfMonth();

            foreach (range(0, 23) as $offset) {
                $month = $start->copy()->addMonthsNoOverflow($offset);
                $end = $month->copy()->endOfMonth();
                $orders = Order::withoutGlobalScopes()->where('status', 'completed')->whereBetween('created_at', [$month, $end]);
                $earnings = VendorEarningLedger::whereBetween('created_at', [$month, $end]);
                $reversals = DB::table('vendor_earning_reversals')->whereBetween('created_at', [$month, $end]);

                RevenueReportSnapshot::updateOrCreate(
                    ['period_month' => $month->toDateString()],
                    [
                        'gross_revenue' => (int) (clone $orders)->sum('total_amount'),
                        'completed_orders' => (int) (clone $orders)->count(),
                        'commission_amount' => (int) (clone $earnings)->sum('commission_amount'),
                        'vendor_net_amount' => (int) (clone $earnings)->sum('net_amount'),
                        'refund_amount' => (int) (clone $reversals)->sum('gross_amount'),
                        'currency' => 'VND',
                        'generated_at' => now(),
                        'generated_by' => $actor?->id,
                        'metadata' => [
                            'basis' => 'completed_orders_and_earning_ledgers',
                            'tax_calculated_or_withheld' => false,
                            'reporting_only' => true,
                        ],
                    ]
                );
            }

            RevenueReportSnapshot::where('period_month', '<', $start->toDateString())->delete();

            return $this->last24Months();
        });
    }

    public function last24Months(): Collection
    {
        return RevenueReportSnapshot::query()
            ->where('period_month', '>=', now()->subMonthsNoOverflow(23)->startOfMonth()->toDateString())
            ->orderBy('period_month')
            ->get();
    }

    /** @return array{start:Carbon,end:Carbon,label:string} */
    public function vendorPeriod(string $granularity, string $period): array
    {
        if ($granularity === 'month' && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        } elseif ($granularity === 'quarter' && preg_match('/^(\d{4})-Q([1-4])$/', $period, $matches)) {
            $start = Carbon::create((int) $matches[1], (((int) $matches[2]) - 1) * 3 + 1, 1)->startOfMonth();
        } elseif ($granularity === 'year' && preg_match('/^\d{4}$/', $period)) {
            $start = Carbon::create((int) $period, 1, 1)->startOfYear();
        } else {
            throw new \InvalidArgumentException('Kỳ báo cáo không hợp lệ.');
        }

        $end = match ($granularity) {
            'month' => $start->copy()->endOfMonth(),
            'quarter' => $start->copy()->addMonthsNoOverflow(2)->endOfMonth(),
            'year' => $start->copy()->endOfYear(),
        };

        return ['start' => $start, 'end' => $end, 'label' => $period];
    }
}
