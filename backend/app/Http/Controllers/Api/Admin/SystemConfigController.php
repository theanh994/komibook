<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SystemConfigController extends Controller
{
    private $configPath;

    public function __construct()
    {
        $this->configPath = storage_path('app/private/system_config.json');
    }

    /**
     * GET /api/admin/config
     */
    public function show(): JsonResponse
    {
        $defaultConfig = [
            'site_name' => 'KomiBook',
            'hotline' => '1900 1234',
            'support_email' => 'support@komibook.id.vn',
            'commission_rate' => 10,
            'service_fee' => 2000,
            'default_borrow_days' => 14,
            'maintenance_mode' => false,
            'max_upload_size' => 5,
        ];

        if (File::exists($this->configPath)) {
            $config = json_decode(File::get($this->configPath), true);
            return response()->json([
                'status' => 'success',
                'data' => array_merge($defaultConfig, $config ?? []),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $defaultConfig,
        ]);
    }

    /**
     * PUT /api/admin/config
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'hotline' => 'nullable|string|max:50',
            'support_email' => 'required|email',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'service_fee' => 'required|numeric|min:0',
            'default_borrow_days' => 'required|integer|min:1',
            'maintenance_mode' => 'required|boolean',
            'max_upload_size' => 'required|integer|min:1',
        ]);

        if (!File::exists(dirname($this->configPath))) {
            File::makeDirectory(dirname($this->configPath), 0755, true);
        }

        File::put($this->configPath, json_encode($validated, JSON_PRETTY_PRINT));

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật cấu hình hệ thống thành công.',
            'data' => $validated,
        ]);
    }
}
