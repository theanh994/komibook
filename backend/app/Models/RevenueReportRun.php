<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RevenueReportRun extends Model
{
    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'public_id', 'operation_key', 'request_fingerprint', 'requested_by', 'reason',
        'status', 'active_slot', 'window_start', 'window_end', 'as_of_at', 'payload',
        'quality', 'started_at', 'completed_at', 'failed_at', 'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'date',
            'window_end' => 'date',
            'as_of_at' => 'datetime',
            'payload' => 'array',
            'quality' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            if ($run->getOriginal('status') !== self::RUNNING) {
                throw new LogicException('Terminal revenue report runs are immutable.');
            }

            $next = $run->status;
            if (! in_array($next, [self::COMPLETED, self::FAILED], true)
                || $run->getOriginal('active_slot') === null
                || $run->active_slot !== null) {
                throw new LogicException('A running revenue report may transition once to a terminal state.');
            }

            foreach ([
                'public_id', 'operation_key', 'request_fingerprint', 'requested_by', 'reason',
                'window_start', 'window_end', 'as_of_at', 'started_at', 'created_at',
            ] as $immutable) {
                if ($run->isDirty($immutable)) {
                    throw new LogicException('Revenue report run audit identity is immutable.');
                }
            }

            $allowed = $next === self::COMPLETED
                ? ['status', 'active_slot', 'payload', 'quality', 'completed_at', 'failed_at', 'failure_code', 'updated_at']
                : ['status', 'active_slot', 'payload', 'quality', 'completed_at', 'failed_at', 'failure_code', 'updated_at'];
            if (array_diff(array_keys($run->getDirty()), $allowed) !== []) {
                throw new LogicException('Revenue report run transition contains unrelated changes.');
            }

            if ($next === self::COMPLETED) {
                if ($run->payload === null || $run->quality === null || $run->completed_at === null
                    || $run->failed_at !== null || $run->failure_code !== null) {
                    throw new LogicException('Completed revenue report runs require a complete terminal shape.');
                }
            } elseif ($run->payload !== null || $run->quality !== null || $run->completed_at !== null
                || $run->failed_at === null || blank($run->failure_code)) {
                throw new LogicException('Failed revenue report runs require a failed terminal shape.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Revenue report runs are immutable.');
        });
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
