<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountSessionService
{
    public function list(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')->get()->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_active_at' => now()->setTimestamp((int) $session->last_activity)->toIso8601String(),
                'is_current' => hash_equals((string) $request->session()->getId(), (string) $session->id),
            ])->all();
    }

    public function revokeOtherSessions(User $user, ?string $currentSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)
                ->when($currentSessionId, fn ($query) => $query->where('id', '!=', $currentSessionId))->delete();
        }
        $user->tokens()->delete();
    }

    public function revoke(User $user, string $sessionId): bool
    {
        if (config('session.driver') !== 'database') {
            return false;
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', $sessionId)->delete() === 1;
    }
}
