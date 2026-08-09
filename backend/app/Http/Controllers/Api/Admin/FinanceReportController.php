<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RevenueReportRun;
use App\Services\RevenueReportRequestConflict;
use App\Services\RevenueReportService;
use App\Services\RevenueReportSourceIntegrityException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function index(Request $request, RevenueReportService $reports): JsonResponse
    {
        $run = $reports->latestCompletedRun();
        if (! $run) {
            return response()->json(['status' => 'unavailable', 'data' => null, 'reason' => 'no_completed_run']);
        }

        return response()->json(['status' => 'success', 'data' => $run->payload, 'run' => $this->runMeta($run)]);
    }

    public function refresh(Request $request, RevenueReportService $reports): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);
        $reason = trim($validated['reason']);
        $operationKey = trim((string) ($request->header('Idempotency-Key') ?: ($validated['idempotency_key'] ?? '')));
        if ($reason === '' || $operationKey === '') {
            return response()->json(['message' => 'reason and idempotency key are required.'], 422);
        }

        try {
            $result = $reports->refreshLast24Months($request->user(), $operationKey, $reason);
        } catch (RevenueReportRequestConflict $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getMessage() === 'refresh_in_progress' ? 409 : 422);
        } catch (RevenueReportSourceIntegrityException) {
            return response()->json(['message' => 'Immutable financial evidence could not be reconciled.'], 422);
        } catch (\Throwable) {
            return response()->json(['message' => 'The report refresh failed safely.'], 500);
        }

        $run = $result['run'];
        if ($run->status === RevenueReportRun::RUNNING) {
            return response()->json([
                'status' => 'running',
                'replayed' => true,
                'data' => null,
                'run' => $this->runMeta($run),
            ], 202);
        }
        if ($run->status === RevenueReportRun::FAILED) {
            return response()->json([
                'status' => 'failed',
                'replayed' => true,
                'data' => null,
                'failure_code' => $run->failure_code,
                'run' => $this->runMeta($run),
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'replayed' => $result['replayed'],
            'data' => $run->payload,
            'run' => $this->runMeta($run),
        ]);
    }

    public function export(Request $request, RevenueReportService $reports): StreamedResponse|JsonResponse
    {
        $run = $request->filled('run_id')
            ? $reports->completedRun((string) $request->query('run_id'))
            : $reports->latestCompletedRun();
        if (! $run) {
            return response()->json([
                'status' => 'unavailable',
                'data' => null,
                'reason' => $request->filled('run_id') ? 'completed_run_not_found' : 'no_completed_run',
            ], $request->filled('run_id') ? 404 : 409);
        }
        $months = $run->payload['revenue_by_month'] ?? [];

        return response()->streamDownload(function () use ($months): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Ky bao cao', 'Tien sach', 'Phi van chuyen', 'Phi dich vu', 'Tong thu tu khach', 'Don ghi nhan', 'Hoa hong san', 'Thu nhap nha ban', 'Hoan tien khach', 'Hoan hoa hong', 'Hoan thu nhap nha ban', 'Tien te']);
            foreach ($months as $row) {
                fputcsv($output, [
                    $row['month'], $row['merchandise_revenue'], $row['shipping_revenue'], $row['service_fee_revenue'],
                    $row['gross_revenue'], $row['completed_orders'], $row['commission_amount'], $row['vendor_net_amount'],
                    $row['refund_amount'], $row['commission_reversal_amount'], $row['vendor_net_reversal_amount'], 'VND',
                ]);
            }
            fclose($output);
        }, 'bao-cao-doanh-thu-24-thang.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function runMeta(RevenueReportRun $run): array
    {
        return [
            'id' => $run->public_id,
            'status' => $run->status,
            'window_start' => $run->window_start?->toDateString(),
            'window_end' => $run->window_end?->toDateString(),
            'as_of_at' => $run->as_of_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
            'quality' => $run->quality,
        ];
    }
}
