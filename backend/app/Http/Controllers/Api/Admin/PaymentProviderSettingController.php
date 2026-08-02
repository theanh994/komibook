<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderSetting;
use App\Services\Payments\PaymentProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentProviderSettingController extends Controller
{
    public function index(PaymentProviderService $providers): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $providers->capabilities()]);
    }

    public function update(Request $request, string $provider, PaymentProviderService $providers): JsonResponse
    {
        $providers->capability($provider);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            // Chủ ý không hỗ trợ live: phạm vi được duyệt phải tuyệt đối không phát sinh phí.
            'mode' => ['required', Rule::in(['disabled', 'demo'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $mode = $validated['enabled'] ? $validated['mode'] : 'disabled';
        $demoProviders = ['demo_wallet'];
        if ($mode === 'demo' && ! in_array($provider, $demoProviders, true)) {
            abort(422, 'Nhà cung cấp này không hỗ trợ chế độ mô phỏng nội bộ.');
        }

        PaymentProviderSetting::updateOrCreate(
            ['provider' => strtolower($provider)],
            [
                'enabled_by_admin' => $validated['enabled'] && $mode === 'demo',
                'mode' => $mode,
                'updated_by' => $request->user()->id,
                'reason' => $validated['reason'],
            ]
        );

        return response()->json(['status' => 'success', 'data' => $providers->capability($provider)]);
    }
}
