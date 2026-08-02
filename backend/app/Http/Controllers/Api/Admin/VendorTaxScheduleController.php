<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorTaxSchedule;
use App\Services\VendorTaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class VendorTaxScheduleController extends Controller
{
    public function index(VendorTaxService $taxes): JsonResponse
    {
        $year = (int) request('tax_year', now()->year);

        return response()->json(['status' => 'success', 'data' => [
            'tax_year' => $year,
            'effective' => $taxes->effectiveSchedule($year, now()),
            'history' => VendorTaxSchedule::where('tax_year', $year)->latest('effective_at')->paginate(20),
        ]]);
    }

    public function store(Request $request, VendorTaxService $taxes): JsonResponse
    {
        $validated = $request->validate([
            'tax_year' => ['required', 'integer', 'min:2020', 'max:2200'],
            'effective_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'operation_key' => ['required', 'string', 'max:128', 'unique:vendor_tax_schedules,operation_key'],
            'brackets' => ['required', 'array', 'min:1', 'max:20'],
            'brackets.*.up_to' => ['nullable', 'integer', 'min:1'],
            'brackets.*.rate_percent' => ['required_without:brackets.*.rate_bps', 'numeric', 'min:0', 'max:100'],
            'brackets.*.rate_bps' => ['required_without:brackets.*.rate_percent', 'integer', 'min:0', 'max:10000'],
        ]);

        try {
            $brackets = $taxes->normalizeBrackets($validated['brackets']);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['brackets' => $exception->getMessage()]);
        }

        $schedule = DB::transaction(fn () => VendorTaxSchedule::create([
            'tax_year' => $validated['tax_year'],
            'effective_at' => $validated['effective_at'],
            'brackets' => $brackets,
            'actor_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'operation_key' => $validated['operation_key'],
        ]));

        return response()->json(['status' => 'success', 'data' => $schedule], 201);
    }

    public function preview(Request $request, VendorTaxService $taxes): JsonResponse
    {
        $validated = $request->validate([
            'annual_revenue' => ['required', 'integer', 'min:0'],
            'brackets' => ['required', 'array', 'min:1', 'max:20'],
        ]);

        try {
            $result = $taxes->preview((int) $validated['annual_revenue'], $validated['brackets']);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['brackets' => $exception->getMessage()]);
        }

        return response()->json(['status' => 'success', 'data' => $result]);
    }
}
