<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommerceFeeSchedule;
use App\Services\CommerceFeeService;
use Illuminate\Http\Request;

class CommerceFeeScheduleController extends Controller
{
    public function index(CommerceFeeService $fees)
    {
        return response()->json(['status' => 'success', 'data' => [
            'effective' => $fees->effective(),
            'history' => CommerceFeeSchedule::latest('effective_at')->paginate(20),
        ]]);
    }

    public function store(Request $request, CommerceFeeService $fees)
    {
        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
            'service_fee_rate' => 'required|numeric|min:0|max:100',
            'effective_at' => 'required|date|unique:commerce_fee_schedules,effective_at',
            'reason' => 'required|string|max:2000',
            'operation_key' => 'required|string|max:128',
        ]);

        return response()->json(['status' => 'success', 'data' => $fees->create($validated, $request->user())], 201);
    }

    public function preview(Request $request, CommerceFeeService $fees)
    {
        $validated = $request->validate([
            'base_amount' => 'required|integer|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'service_fee_rate' => 'nullable|numeric|min:0|max:100',
        ]);
        $schedule = $fees->effective();
        if (array_key_exists('commission_rate', $validated)) {
            $schedule['commission_rate'] = (float) $validated['commission_rate'];
        }
        if (array_key_exists('service_fee_rate', $validated)) {
            $schedule['service_fee_rate'] = (float) $validated['service_fee_rate'];
        }

        return response()->json(['status' => 'success', 'data' => $fees->calculate($validated['base_amount'], $schedule)]);
    }
}
