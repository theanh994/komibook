<?php

namespace App\Services\Payments;

use App\Models\PaymentProviderSetting;

class PaymentProviderService
{
    /** @return array<int, array<string, mixed>> */
    public function capabilities(): array
    {
        return collect(config('payment_providers.providers', []))
            ->map(function (array $provider, string $key): array {
                $setting = PaymentProviderSetting::where('provider', $key)->first();
                $mode = $setting?->mode ?? (string) ($provider['mode'] ?? 'disabled');
                $configured = $this->isConfigured($key, $provider, $mode);
                $enabled = $setting?->enabled_by_admin ?? ($key === 'vnpay' ? $configured : $mode === 'demo');
                $externalAllowed = (bool) config('payment_providers.external_calls_enabled', false);
                $available = $enabled && $configured && ($key === 'vnpay' || $mode === 'demo' || $externalAllowed);

                return [
                    'id' => $key,
                    'name' => $key === 'vnpay'
                        ? 'VNPAY Sandbox'
                        : ($provider['label'] ?? strtoupper($key)),
                    'mode' => $mode,
                    'enabled_by_admin' => $enabled,
                    'configured' => $configured,
                    'available' => $available,
                    'supports_qr' => (bool) ($provider['supports_qr'] ?? false),
                    'supports_refund' => (bool) ($provider['supports_refund'] ?? false),
                    'supports_reconciliation' => (bool) ($provider['supports_reconciliation'] ?? false),
                    'notice' => $key === 'demo_wallet' && $available
                        ? 'Ví nội bộ KomiBook dùng số dư phát sinh từ hoàn tiền và doanh thu; không hỗ trợ nạp tiền bên ngoài.'
                        : ($key === 'vnpay' && $available
                        ? 'Môi trường VNPAY Sandbox, không phát sinh giao dịch tiền thật.'
                        : ($mode === 'demo'
                        ? 'Mô phỏng nội bộ, không phát sinh giao dịch hoặc chi phí thật.'
                        : ($available ? 'Đã sẵn sàng.' : 'Chưa cấu hình hoặc đang bị khóa.'))),
                ];
            })
            ->values()
            ->all();
    }

    public function capability(string $provider): array
    {
        $result = collect($this->capabilities())->firstWhere('id', strtolower($provider));
        abort_unless($result, 404, 'Phương thức thanh toán không tồn tại.');

        return $result;
    }

    private function isConfigured(string $key, array $provider, string $mode): bool
    {
        if ($mode === 'disabled') {
            return false;
        }

        return match ($key) {
            'vnpay' => $mode === 'sandbox'
                && filled(config('services.vnpay.tmn_code'))
                && filled(config('services.vnpay.hash_secret'))
                && strtolower((string) parse_url((string) config('services.vnpay.url'), PHP_URL_HOST)) === 'sandbox.vnpayment.vn',
            'demo_wallet' => $mode === 'demo',
            default => false,
        };
    }
}
