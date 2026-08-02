<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VendorTaxLedgerEntry;
use App\Models\VendorTaxSchedule;
use Carbon\CarbonInterface;
use LogicException;

class VendorTaxService
{
    /**
     * Tính thuế lũy tiến theo năm. Mỗi bậc có dạng:
     * ['up_to' => int|null, 'rate_bps' => int], với 100 bps = 1%.
     *
     * @param  array<int, array{up_to:int|null, rate_bps:int}>  $brackets
     */
    public function liability(int $annualRevenue, array $brackets): int
    {
        $remaining = max(0, $annualRevenue);
        $lowerBound = 0;
        $tax = 0;

        foreach ($this->normalizeBrackets($brackets) as $bracket) {
            if ($remaining <= 0) {
                break;
            }

            $upTo = $bracket['up_to'];
            $bandSize = $upTo === null ? $remaining : max(0, $upTo - $lowerBound);
            $taxableInBand = min($remaining, $bandSize);
            $tax += intdiv($taxableInBand * $bracket['rate_bps'] + 5000, 10000);
            $remaining -= $taxableInBand;

            if ($upTo === null) {
                break;
            }
            $lowerBound = $upTo;
        }

        return $tax;
    }

    /** @return array<string, mixed> */
    public function preview(int $annualRevenue, array $brackets): array
    {
        $normalized = $this->normalizeBrackets($brackets);

        return [
            'annual_revenue' => max(0, $annualRevenue),
            'tax_amount' => $this->liability($annualRevenue, $normalized),
            'brackets' => $normalized,
            'basis' => 'Tổng tiền khách thanh toán trước commission, cộng dồn theo năm dương lịch.',
        ];
    }

    /** @return array{tax_amount:int,schedule_id:int|null,tax_year:int,snapshot:array<string,mixed>} */
    public function quoteForEarning(int $vendorId, int $taxableRevenue, CarbonInterface $occurredAt): array
    {
        $taxYear = (int) $occurredAt->year;
        $schedule = $this->effectiveSchedule($taxYear, $occurredAt);
        $previousRevenue = (int) VendorTaxLedgerEntry::where('vendor_id', $vendorId)
            ->where('tax_year', $taxYear)
            ->sum('taxable_revenue');
        $previousTax = (int) VendorTaxLedgerEntry::where('vendor_id', $vendorId)
            ->where('tax_year', $taxYear)
            ->sum('tax_amount');
        $brackets = $schedule?->brackets ?? [];
        $newRevenue = max(0, $previousRevenue + max(0, $taxableRevenue));
        $targetTax = $schedule ? $this->liability($newRevenue, $brackets) : $previousTax;
        $incrementalTax = max(0, $targetTax - $previousTax);

        return [
            'tax_amount' => $incrementalTax,
            'schedule_id' => $schedule?->id,
            'tax_year' => $taxYear,
            'snapshot' => [
                'calculation' => 'annual_progressive',
                'basis' => 'customer_total_before_commission',
                'configured' => $schedule !== null,
                'previous_annual_revenue' => $previousRevenue,
                'order_taxable_revenue' => max(0, $taxableRevenue),
                'annual_revenue_after_order' => $newRevenue,
                'previous_tax_withheld' => $previousTax,
                'annual_tax_after_order' => $targetTax,
                'incremental_tax_withheld' => $incrementalTax,
                'brackets' => $brackets,
            ],
        ];
    }

    /** @param array{tax_amount:int,schedule_id:int|null,tax_year:int,snapshot:array<string,mixed>} $quote */
    public function recordEarning(Order $order, array $quote): VendorTaxLedgerEntry
    {
        return VendorTaxLedgerEntry::firstOrCreate(
            ['operation_key' => "vendor-tax:{$order->id}"],
            [
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'vendor_tax_schedule_id' => $quote['schedule_id'],
                'tax_year' => $quote['tax_year'],
                'entry_type' => 'earning',
                'taxable_revenue' => (int) ($quote['snapshot']['order_taxable_revenue'] ?? 0),
                'tax_amount' => $quote['tax_amount'],
                'calculation_snapshot' => $quote['snapshot'],
            ]
        );
    }

    public function recordReversal(Order $order, int $returnRequestId, int $grossAmount, int $taxAmount): VendorTaxLedgerEntry
    {
        $earning = VendorTaxLedgerEntry::where('operation_key', "vendor-tax:{$order->id}")->first();
        $taxYear = $earning?->tax_year ?? (int) now()->year;

        return VendorTaxLedgerEntry::firstOrCreate(
            ['operation_key' => "vendor-tax-refund:{$returnRequestId}"],
            [
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'vendor_tax_schedule_id' => $earning?->vendor_tax_schedule_id,
                'tax_year' => $taxYear,
                'entry_type' => 'refund_reversal',
                'taxable_revenue' => -max(0, $grossAmount),
                'tax_amount' => -max(0, $taxAmount),
                'calculation_snapshot' => [
                    'calculation' => 'proportional_refund_reversal',
                    'source_return_request_id' => $returnRequestId,
                    'reversed_taxable_revenue' => max(0, $grossAmount),
                    'reversed_tax_amount' => max(0, $taxAmount),
                ],
            ]
        );
    }

    public function effectiveSchedule(int $taxYear, CarbonInterface $at): ?VendorTaxSchedule
    {
        return VendorTaxSchedule::where('tax_year', $taxYear)
            ->where('effective_at', '<=', $at)
            ->latest('effective_at')
            ->first();
    }

    /** @return array<int, array{up_to:int|null, rate_bps:int}> */
    public function normalizeBrackets(array $brackets): array
    {
        if ($brackets === []) {
            return [];
        }

        $normalized = collect($brackets)->map(function (array $bracket): array {
            $upTo = $bracket['up_to'] ?? null;
            $rateBps = array_key_exists('rate_bps', $bracket)
                ? (int) $bracket['rate_bps']
                : (int) round(((float) ($bracket['rate_percent'] ?? 0)) * 100);

            if (($upTo !== null && (! is_numeric($upTo) || (int) $upTo <= 0)) || $rateBps < 0 || $rateBps > 10000) {
                throw new LogicException('Bậc thuế không hợp lệ.');
            }

            return ['up_to' => $upTo === null ? null : (int) $upTo, 'rate_bps' => $rateBps];
        })->values()->all();

        $previous = 0;
        foreach ($normalized as $index => $bracket) {
            if ($bracket['up_to'] === null && $index !== count($normalized) - 1) {
                throw new LogicException('Chỉ bậc cuối cùng được phép không có giới hạn trên.');
            }
            if ($bracket['up_to'] !== null && $bracket['up_to'] <= $previous) {
                throw new LogicException('Giới hạn doanh thu của các bậc thuế phải tăng dần.');
            }
            $previous = $bracket['up_to'] ?? $previous;
        }

        if (end($normalized)['up_to'] !== null) {
            throw new LogicException('Bậc thuế cuối cùng phải áp dụng cho phần doanh thu còn lại.');
        }

        return $normalized;
    }
}
