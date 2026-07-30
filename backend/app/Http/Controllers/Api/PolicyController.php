<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnPolicyVersion;
use Illuminate\Http\JsonResponse;

class PolicyController extends Controller
{
    public function returnPolicies(): JsonResponse
    {
        $policies = ReturnPolicyVersion::query()
            ->where('active_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')->orWhere('retired_at', '>', now());
            })
            ->orderBy('policy_key')
            ->orderByDesc('version')
            ->get()
            ->unique('policy_key')
            ->mapWithKeys(fn (ReturnPolicyVersion $policy) => [
                $policy->policy_key => [
                    'key' => $policy->policy_key,
                    'version' => $policy->version,
                    'applies_to' => $policy->applies_to,
                    'is_returnable' => $policy->is_returnable,
                    'return_window_days' => $policy->return_window_days,
                    'terms' => $policy->terms,
                    'active_from' => $policy->active_from?->toISOString(),
                ],
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $policies,
        ]);
    }
}
