<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RevenueReportRun;
use App\Models\User;
use App\Models\VendorEarningLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RevenueReportService
{
    public function refreshLast24Months(User $actor, string $operationKey, string $reason): array
    {
        $asOf = now();
        $start = $asOf->copy()->subMonthsNoOverflow(23)->startOfMonth();
        $fingerprint = hash('sha256', json_encode([
            'actor_id' => $actor->id,
            'reason' => trim($reason),
            'operation' => 'admin_finance_report_24_months',
        ], JSON_THROW_ON_ERROR));

        $existing = RevenueReportRun::query()->where('operation_key', $operationKey)->first();
        if ($existing) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw new RevenueReportRequestConflict('idempotency_key_conflict');
            }

            return ['run' => $existing, 'replayed' => true];
        }

        try {
            $claim = DB::transaction(function () use ($actor, $operationKey, $reason, $fingerprint, $start, $asOf): array {
                $existing = RevenueReportRun::query()->where('operation_key', $operationKey)->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                        throw new RevenueReportRequestConflict('idempotency_key_conflict');
                    }

                    return ['run' => $existing, 'claimed' => false];
                }

                if (RevenueReportRun::query()->whereNotNull('active_slot')->lockForUpdate()->exists()) {
                    throw new RevenueReportRequestConflict('refresh_in_progress');
                }

                $run = RevenueReportRun::create([
                    'public_id' => (string) Str::uuid(),
                    'operation_key' => $operationKey,
                    'request_fingerprint' => $fingerprint,
                    'requested_by' => $actor->id,
                    'reason' => trim($reason),
                    'status' => RevenueReportRun::RUNNING,
                    'active_slot' => 'admin-finance-24-months',
                    'window_start' => $start->toDateString(),
                    'window_end' => $asOf->toDateString(),
                    'as_of_at' => $asOf,
                    'started_at' => $asOf,
                ]);

                return ['run' => $run, 'claimed' => true];
            });
        } catch (RevenueReportRequestConflict $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $existing = RevenueReportRun::query()->where('operation_key', $operationKey)->first();
            if ($existing) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw new RevenueReportRequestConflict('idempotency_key_conflict');
                }

                return ['run' => $existing, 'replayed' => true];
            }

            if (RevenueReportRun::query()->whereNotNull('active_slot')->exists()) {
                throw new RevenueReportRequestConflict('refresh_in_progress');
            }

            throw $exception;
        }

        $run = $claim['run'];
        if (! $claim['claimed']) {
            return ['run' => $run, 'replayed' => true];
        }

        try {
            DB::transaction(function () use ($run, $start, $asOf): void {
                $locked = RevenueReportRun::query()->lockForUpdate()->findOrFail($run->id);
                [$payload, $quality] = $this->buildPayload($start, $asOf);
                $locked->fill([
                    'status' => RevenueReportRun::COMPLETED,
                    'active_slot' => null,
                    'payload' => $payload,
                    'quality' => $quality,
                    'completed_at' => now(),
                    'failed_at' => null,
                    'failure_code' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            $this->markFailed($run, $exception instanceof RevenueReportSourceIntegrityException ? 'source_integrity' : 'build_failed');
            throw $exception;
        }

        return ['run' => $run->fresh(), 'replayed' => false];
    }

    public function latestCompletedRun(): ?RevenueReportRun
    {
        return RevenueReportRun::query()
            ->where('status', RevenueReportRun::COMPLETED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function completedRun(string $publicId): ?RevenueReportRun
    {
        return RevenueReportRun::query()
            ->where('public_id', $publicId)
            ->where('status', RevenueReportRun::COMPLETED)
            ->first();
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function buildPayload(Carbon $start, Carbon $asOf): array
    {
        $months = collect(range(0, 23))->mapWithKeys(function (int $offset) use ($start): array {
            $month = $start->copy()->addMonthsNoOverflow($offset);

            return [$month->format('Y-m') => [
                'month' => $month->format('Y-m'),
                'gross_revenue' => 0,
                'merchandise_revenue' => 0,
                'shipping_revenue' => 0,
                'service_fee_revenue' => 0,
                'completed_orders' => 0,
                'commission_amount' => 0,
                'vendor_net_amount' => 0,
                'refund_amount' => 0,
                'commission_reversal_amount' => 0,
                'vendor_net_reversal_amount' => 0,
                'vendor_net_after_refunds' => 0,
                'platform_commission_retention' => 0,
                'platform_net_retention' => null,
                'commission_rate' => null,
                'component_coverage' => 'complete',
                'unknown_component_count' => 0,
            ]];
        });

        $vendorTotals = [];
        $earnings = VendorEarningLedger::query()
            ->whereBetween('created_at', [$start, $asOf])
            ->with(['order.checkoutSessionOrder.checkoutSession', 'order.invoiceSnapshot'])
            ->orderBy('id')->get();

        foreach ($earnings as $earning) {
            $monthKey = $earning->created_at->format('Y-m');
            $row = $months[$monthKey];
            $this->assertVnd($earning->currency, 'earning_currency');
            $gross = (int) $earning->gross_amount;
            $commission = (int) $earning->commission_amount;
            $net = (int) $earning->net_amount;
            $tax = (int) ($earning->tax_amount ?? 0);
            if ($gross < 0 || $commission < 0 || $net < 0 || $net !== $gross - $commission - $tax) {
                throw new RevenueReportSourceIntegrityException('earning_arithmetic');
            }

            $row['gross_revenue'] += $gross;
            $row['commission_amount'] += $commission;
            $row['vendor_net_amount'] += $net;
            $row['completed_orders']++;

            $order = $earning->order;
            $checkout = $order?->checkoutSessionOrder;
            $invoice = $order?->invoiceSnapshot;
            if (! $order || ! $checkout) {
                $row['component_coverage'] = 'partial';
                $row['unknown_component_count']++;
            } else {
                if ((int) $order->id !== (int) $earning->order_id
                    || (int) $order->vendor_id !== (int) $earning->vendor_id
                    || (int) $checkout->order_id !== (int) $earning->order_id
                    || (int) $checkout->vendor_id !== (int) $earning->vendor_id
                    || (int) $checkout->total_amount !== $gross
                    || (int) $checkout->commission_amount !== $commission) {
                    throw new RevenueReportSourceIntegrityException('canonical_link_mismatch');
                }
                if ($checkout->checkoutSession) {
                    $this->assertVnd($checkout->checkoutSession->currency, 'checkout_currency');
                }
                if ($invoice) {
                    if ((int) $invoice->order_id !== (int) $earning->order_id) {
                        throw new RevenueReportSourceIntegrityException('invoice_link_mismatch');
                    }
                    $this->assertVnd($invoice->currency, 'invoice_currency');
                    $seller = $invoice->seller_snapshot;
                    if (! is_array($seller) || ! array_key_exists('vendor_id', $seller)
                        || (int) $seller['vendor_id'] !== (int) $earning->vendor_id) {
                        throw new RevenueReportSourceIntegrityException('invoice_seller_vendor_mismatch');
                    }
                    if ((int) $invoice->subtotal_amount !== (int) $checkout->subtotal_amount) {
                        throw new RevenueReportSourceIntegrityException('invoice_subtotal_mismatch');
                    }
                    if ($checkout->coupon_discount_amount !== null
                        && (int) $invoice->coupon_discount_amount !== (int) $checkout->coupon_discount_amount) {
                        throw new RevenueReportSourceIntegrityException('invoice_coupon_discount_mismatch');
                    }
                    if ($checkout->membership_discount_amount !== null
                        && (int) $invoice->membership_discount_amount !== (int) $checkout->membership_discount_amount) {
                        throw new RevenueReportSourceIntegrityException('invoice_membership_discount_mismatch');
                    }
                    if ((int) $invoice->tax_amount < 0
                        || (int) $invoice->subtotal_amount - (int) $checkout->discount_amount
                            + (int) $invoice->shipping_fee_amount + (int) $invoice->service_fee_amount
                            + (int) $invoice->tax_amount !== (int) $invoice->total_amount) {
                        throw new RevenueReportSourceIntegrityException('invoice_arithmetic_mismatch');
                    }
                    if ((int) $invoice->total_amount !== $gross) {
                        throw new RevenueReportSourceIntegrityException('invoice_total_mismatch');
                    }
                }

                $merchandise = $this->component($checkout->subtotal_amount, $checkout->discount_amount);
                $shipping = $checkout->shipping_fee_amount;
                if ($shipping === null && $invoice) {
                    $shipping = $invoice->shipping_fee_amount;
                }
                $serviceFee = $checkout->fee_amount;
                if ($invoice && $checkout->shipping_fee_amount !== null
                    && (int) $invoice->shipping_fee_amount !== (int) $checkout->shipping_fee_amount) {
                    throw new RevenueReportSourceIntegrityException('shipping_fee_mismatch');
                }
                if ($invoice && $serviceFee !== null && (int) $invoice->service_fee_amount !== (int) $serviceFee) {
                    throw new RevenueReportSourceIntegrityException('service_fee_mismatch');
                }

                if ($merchandise === null || $shipping === null || $serviceFee === null) {
                    $row['component_coverage'] = 'partial';
                    $row['unknown_component_count']++;
                } else {
                    $merchandise = (int) $merchandise;
                    $shipping = (int) $shipping;
                    $serviceFee = (int) $serviceFee;
                    if ($merchandise < 0 || $shipping < 0 || $serviceFee < 0
                        || $merchandise + $shipping + $serviceFee !== $gross) {
                        throw new RevenueReportSourceIntegrityException('gross_component_mismatch');
                    }
                    $row['merchandise_revenue'] += $merchandise;
                    $row['shipping_revenue'] += $shipping;
                    $row['service_fee_revenue'] += $serviceFee;
                }
            }

            $months[$monthKey] = $row;
            $vendorId = (int) $earning->vendor_id;
            $vendorTotals[$vendorId] ??= ['id' => $vendorId, 'shop_name' => null, 'revenue' => 0, 'commission_amount' => 0, 'vendor_net_amount' => 0, 'total_orders' => 0];
            $vendorTotals[$vendorId]['revenue'] += $gross;
            $vendorTotals[$vendorId]['commission_amount'] += $commission;
            $vendorTotals[$vendorId]['vendor_net_amount'] += $net;
            $vendorTotals[$vendorId]['total_orders']++;
            if ($vendorTotals[$vendorId]['shop_name'] === null && is_array($invoice?->seller_snapshot)) {
                $vendorTotals[$vendorId]['shop_name'] = $invoice->seller_snapshot['shop_name'] ?? $invoice->seller_snapshot['name'] ?? null;
            }
        }

        foreach (DB::table('refund_transactions')->where('status', 'refunded')->whereBetween('refunded_at', [$start, $asOf])->orderBy('id')->get() as $refund) {
            $this->assertVnd($refund->currency, 'refund_currency');
            if ((int) $refund->amount < 0) {
                throw new RevenueReportSourceIntegrityException('refund_amount');
            }
            $monthKey = Carbon::parse($refund->refunded_at)->format('Y-m');
            $row = $months[$monthKey];
            $row['refund_amount'] += (int) $refund->amount;
            $months[$monthKey] = $row;
        }
        foreach (DB::table('vendor_earning_reversals')->whereBetween('created_at', [$start, $asOf])->orderBy('id')->get() as $reversal) {
            $this->assertVnd($reversal->currency, 'reversal_currency');
            $reversalGross = (int) $reversal->gross_amount;
            $reversalCommission = (int) $reversal->commission_amount;
            $reversalNet = (int) $reversal->net_amount;
            $reversalTax = (int) ($reversal->tax_amount ?? 0);
            if ($reversalGross < 0 || $reversalCommission < 0 || $reversalNet < 0
                || $reversalGross !== $reversalCommission + $reversalTax + $reversalNet) {
                throw new RevenueReportSourceIntegrityException('reversal_arithmetic');
            }
            $monthKey = Carbon::parse($reversal->created_at)->format('Y-m');
            $row = $months[$monthKey];
            $row['commission_reversal_amount'] += $reversalCommission;
            $row['vendor_net_reversal_amount'] += $reversalNet;
            $months[$monthKey] = $row;
        }

        $monthly = $months->values()->map(function (array $row): array {
            if ($row['component_coverage'] !== 'complete') {
                $row['merchandise_revenue'] = null;
                $row['shipping_revenue'] = null;
                $row['service_fee_revenue'] = null;
            }
            $row['vendor_net_after_refunds'] = $row['vendor_net_amount'] - $row['vendor_net_reversal_amount'];
            $row['platform_commission_retention'] = $row['commission_amount'] - $row['commission_reversal_amount'];

            return $row;
        })->all();

        $sum = static function (string $field) use ($monthly): ?int {
            if (collect($monthly)->contains(static fn (array $row): bool => $row[$field] === null)) {
                return null;
            }

            return (int) collect($monthly)->sum($field);
        };
        $unattributed = Order::withoutGlobalScopes()->where('status', 'completed')->whereDoesntHave('vendorEarningLedger')->count();
        $quality = [
            'status' => collect($monthly)->contains(static fn (array $row): bool => $row['component_coverage'] !== 'complete') ? 'partial' : 'complete',
            'unattributed_legacy_count' => $unattributed,
            'unknown_component_count' => (int) collect($monthly)->sum('unknown_component_count'),
            'payment_method_dimension' => 'unavailable',
            'platform_net_retention' => 'unavailable',
        ];

        return [[
            'currency' => 'VND',
            'as_of_at' => $asOf->toISOString(),
            'kpi' => [
                'total_revenue' => $sum('gross_revenue'),
                'gross_revenue' => $sum('gross_revenue'),
                'merchandise_revenue' => $sum('merchandise_revenue'),
                'shipping_revenue' => $sum('shipping_revenue'),
                'service_fee_revenue' => $sum('service_fee_revenue'),
                'monthly_revenue' => $monthly[array_key_last($monthly)]['gross_revenue'],
                'completed_orders' => $sum('completed_orders'),
                'total_commission' => $sum('commission_amount'),
                'total_vendor_net' => $sum('vendor_net_amount'),
                'total_refunds' => $sum('refund_amount'),
                'commission_reversal_amount' => $sum('commission_reversal_amount'),
                'vendor_net_reversal_amount' => $sum('vendor_net_reversal_amount'),
                'vendor_net_after_refunds' => $sum('vendor_net_after_refunds'),
                'platform_commission_retention' => $sum('platform_commission_retention'),
                'platform_net_retention' => null,
                'commission_rate' => null,
                'unattributed_legacy_count' => $unattributed,
            ],
            'revenue_by_month' => $monthly,
            'revenue_by_payment_method' => [],
            'top_vendors' => collect($vendorTotals)->map(function (array $vendor): array {
                $vendor['shop_name'] ??= 'Vendor #'.$vendor['id'];

                return $vendor;
            })->sortByDesc('revenue')->take(10)->values()->all(),
            'quality' => $quality,
            'reporting_policy' => [
                'retention_months' => 24,
                'commission_rate' => null,
                'platform_net_retention' => 'unavailable',
                'payment_method_dimension' => 'unavailable',
                'basis' => 'immutable_earning_and_financial_event_ledgers',
            ],
        ], $quality];
    }

    private function markFailed(RevenueReportRun $run, string $failureCode): void
    {
        $current = RevenueReportRun::query()->find($run->id);
        if (! $current || $current->status !== RevenueReportRun::RUNNING) {
            return;
        }
        $current->fill([
            'status' => RevenueReportRun::FAILED,
            'active_slot' => null,
            'failed_at' => now(),
            'failure_code' => $failureCode,
        ])->save();
    }

    private function component(mixed $subtotal, mixed $discount): ?int
    {
        if ($subtotal === null || $discount === null) {
            return null;
        }

        return (int) $subtotal - (int) $discount;
    }

    private function assertVnd(?string $currency, string $failure): void
    {
        if (strtoupper((string) $currency) !== 'VND') {
            throw new RevenueReportSourceIntegrityException($failure);
        }
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

class RevenueReportRequestConflict extends \RuntimeException {}

class RevenueReportSourceIntegrityException extends \RuntimeException {}
