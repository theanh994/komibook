<?php

namespace App\Services;

use App\Models\CommerceFeeSchedule;
use App\Models\CommerceFeeScheduleEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommerceFeeService
{
    public const DEFAULT_COMMISSION_RATE = 10.0;

    public const DEFAULT_SERVICE_FEE_RATE = 0.0;

    /** @return array{id:?int,commission_rate:float,service_fee_rate:float,effective_at:?string,source:string} */
    public function effective(?CarbonInterface $at = null): array
    {
        $at ??= now();
        $schedule = CommerceFeeSchedule::where('effective_at', '<=', $at)->latest('effective_at')->first();

        return $schedule ? [
            'id' => $schedule->id,
            'commission_rate' => (float) $schedule->commission_rate,
            'service_fee_rate' => (float) $schedule->service_fee_rate,
            'effective_at' => $schedule->effective_at->toISOString(),
            'source' => 'database',
        ] : [
            'id' => null,
            'commission_rate' => self::DEFAULT_COMMISSION_RATE,
            'service_fee_rate' => self::DEFAULT_SERVICE_FEE_RATE,
            'effective_at' => null,
            'source' => 'compatibility_default',
        ];
    }

    /** @return array{base_amount:int,service_fee_amount:int,commission_amount:int,total_amount:int,commission_rate:float,service_fee_rate:float} */
    public function calculate(int $baseAmount, array $schedule): array
    {
        $baseAmount = max(0, $baseAmount);
        $serviceFee = (int) round($baseAmount * (float) $schedule['service_fee_rate'] / 100);
        $commission = (int) round($baseAmount * (float) $schedule['commission_rate'] / 100);

        return [
            'base_amount' => $baseAmount,
            'service_fee_amount' => $serviceFee,
            'commission_amount' => $commission,
            'total_amount' => $baseAmount + $serviceFee,
            'commission_rate' => (float) $schedule['commission_rate'],
            'service_fee_rate' => (float) $schedule['service_fee_rate'],
        ];
    }

    public function create(array $attributes, User $actor): CommerceFeeSchedule
    {
        return DB::transaction(function () use ($attributes, $actor) {
            $existing = CommerceFeeScheduleEvent::where('operation_key', $attributes['operation_key'])->first();
            if ($existing) {
                $snapshot = $existing->snapshot;
                if ((float) $snapshot['commission_rate'] !== (float) $attributes['commission_rate']
                    || (float) $snapshot['service_fee_rate'] !== (float) $attributes['service_fee_rate']
                    || $snapshot['effective_at'] !== $attributes['effective_at']) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key was already used with different fee data.']);
                }

                return CommerceFeeSchedule::findOrFail($existing->commerce_fee_schedule_id);
            }

            $schedule = CommerceFeeSchedule::create([
                'commission_rate' => $attributes['commission_rate'],
                'service_fee_rate' => $attributes['service_fee_rate'],
                'effective_at' => $attributes['effective_at'],
                'actor_id' => $actor->id,
                'reason' => $attributes['reason'],
            ]);
            $schedule->refresh();
            CommerceFeeScheduleEvent::create([
                'commerce_fee_schedule_id' => $schedule->id,
                'actor_id' => $actor->id,
                'action' => 'created',
                'snapshot' => [
                    'commission_rate' => (float) $schedule->commission_rate,
                    'service_fee_rate' => (float) $schedule->service_fee_rate,
                    'effective_at' => $schedule->effective_at->toISOString(),
                    'reason' => $schedule->reason,
                ],
                'operation_key' => $attributes['operation_key'],
            ]);

            return $schedule;
        });
    }
}
