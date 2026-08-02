<?php

namespace App\Console\Commands;

use App\Models\PaymentProviderSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnableDemoPaymentProviders extends Command
{
    protected $signature = 'payments:enable-demo';

    protected $description = 'Bật VNPAY Sandbox và ví KomiBook giả lập, đồng thời loại cấu hình MoMo/payOS';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Lệnh chỉ được phép chạy trong môi trường local hoặc testing.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            PaymentProviderSetting::whereIn('provider', ['momo', 'payos'])->delete();

            foreach (['vnpay', 'demo_wallet'] as $provider) {
                PaymentProviderSetting::updateOrCreate(
                    ['provider' => $provider],
                    [
                        'enabled_by_admin' => true,
                        'mode' => $provider === 'vnpay' ? 'sandbox' : 'demo',
                        'updated_by' => null,
                        'reason' => $provider === 'vnpay'
                            ? 'Bật VNPAY Sandbox bằng payments:enable-demo'
                            : 'Bật ví KomiBook giả lập bằng payments:enable-demo',
                    ]
                );
            }
        });

        PaymentProviderSetting::orderBy('provider')->get()->each(function (PaymentProviderSetting $setting): void {
            $this->line("{$setting->provider}: {$setting->mode} / ".($setting->enabled_by_admin ? 'enabled' : 'disabled'));
        });
        $this->info('Đã bật VNPAY Sandbox và ví KomiBook giả lập; MoMo/payOS đã bị loại khỏi cấu hình cục bộ.');

        return self::SUCCESS;
    }
}
