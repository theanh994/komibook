<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CampaignNotificationMail;
use App\Models\NotificationCampaign;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationCampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = NotificationCampaign::orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $campaigns = $query->paginate(10);

        return response()->json($campaigns);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'image_url' => 'nullable|url|max:500',
            'target_audience' => 'required|in:all,active_readers,fiction_enthusiasts,lapsed_users',
            'scheduled_at' => 'nullable|date',
            'status' => 'required|in:draft,scheduled,sent',
        ]);

        if ($validated['target_audience'] === 'fiction_enthusiasts' && $validated['status'] === 'sent') {
            return response()->json([
                'message' => 'Phân loại khán giả fiction_enthusiasts chưa được hỗ trợ.',
            ], 422);
        }

        $shouldSend = $validated['status'] === 'sent';
        if ($shouldSend) {
            $validated['status'] = 'draft';
        }

        $campaign = NotificationCampaign::create($validated);

        if ($shouldSend) {
            try {
                $this->dispatchCampaign($campaign);
            } catch (\Throwable) {
                return response()->json(['message' => 'Không thể gửi chiến dịch thông báo.'], 422);
            }
        }

        return response()->json([
            'message' => 'Chiến dịch đã được tạo thành công.',
            'campaign' => $campaign->fresh(),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $campaign = NotificationCampaign::findOrFail($id);

        $analytics = null;
        if ($campaign->status === 'sent') {
            $sent = (int) $campaign->sent_count;
            $opened = (int) $campaign->opened_count;
            $clicked = (int) $campaign->click_count;

            $openRate = $sent > 0 ? round(($opened / $sent) * 100, 1) : 0.0;
            $clickRate = $sent > 0 ? round(($clicked / $sent) * 100, 1) : 0.0;

            $analytics = [
                'delivery_rate' => null,
                'open_rate' => $openRate,
                'click_rate' => $clickRate,
                'telemetry_available' => false,
                'hourly_opens' => [],
                'devices' => [],
                'segments' => [],
            ];
        }

        return response()->json([
            'campaign' => $campaign,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $campaign = NotificationCampaign::findOrFail($id);

        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'Không thể sửa chiến dịch đã gửi.'], 422);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'image_url' => 'nullable|url|max:500',
            'target_audience' => 'required|in:all,active_readers,fiction_enthusiasts,lapsed_users',
            'scheduled_at' => 'nullable|date',
            'status' => 'required|in:draft,scheduled,sent',
        ]);

        if ($validated['target_audience'] === 'fiction_enthusiasts' && $validated['status'] === 'sent') {
            return response()->json([
                'message' => 'Phân loại khán giả fiction_enthusiasts chưa được hỗ trợ.',
            ], 422);
        }

        $shouldSend = $validated['status'] === 'sent';
        if ($shouldSend) {
            $validated['status'] = 'draft';
        }

        $campaign->update($validated);

        if ($shouldSend) {
            try {
                $this->dispatchCampaign($campaign);
            } catch (\Throwable) {
                return response()->json(['message' => 'Không thể gửi chiến dịch thông báo.'], 422);
            }
        }

        return response()->json([
            'message' => 'Chiến dịch đã được cập nhật thành công.',
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $campaign = NotificationCampaign::findOrFail($id);
        $campaign->delete();

        return response()->json(['message' => 'Chiến dịch đã được xóa thành công.']);
    }

    /**
     * Send campaign immediately.
     */
    public function send(string $id)
    {
        $campaign = NotificationCampaign::findOrFail($id);

        if ($campaign->status === 'sent') {
            return response()->json(['message' => 'Chiến dịch này đã được gửi trước đó.'], 422);
        }

        if ($campaign->target_audience === 'fiction_enthusiasts') {
            return response()->json([
                'message' => 'Phân loại khán giả fiction_enthusiasts chưa được hỗ trợ.',
            ], 422);
        }

        try {
            $this->dispatchCampaign($campaign);
        } catch (\Throwable) {
            return response()->json(['message' => 'Không thể gửi chiến dịch thông báo.'], 422);
        }

        return response()->json([
            'message' => 'Chiến dịch đã bắt đầu gửi.',
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Helper to query target users and generate notifications.
     */
    protected function dispatchCampaign(NotificationCampaign $campaign)
    {
        if ($campaign->target_audience === 'fiction_enthusiasts') {
            throw new \InvalidArgumentException('Phân loại khán giả fiction_enthusiasts chưa được hỗ trợ.');
        }

        $usersQuery = User::where('role', 'customer');

        if ($campaign->target_audience === 'active_readers') {
            $usersQuery->whereHas('orders');
        } elseif ($campaign->target_audience === 'lapsed_users') {
            $usersQuery->whereDoesntHave('orders', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            });
        }

        $users = $usersQuery->get();
        $sentCount = 0;

        DB::transaction(function () use ($users, $campaign, &$sentCount) {
            foreach ($users as $user) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'title' => $campaign->title,
                    'content' => $campaign->message,
                    'type' => 'marketing',
                    'data' => [
                        'campaign_id' => $campaign->id,
                        'image_url' => $campaign->image_url,
                        'icon' => 'campaign',
                        'colorClass' => 'bg-indigo-100 text-indigo-600',
                    ],
                ]);
                $sentCount++;
            }

            $campaign->update([
                'status' => 'sent',
                'sent_count' => $sentCount,
            ]);
        });

        foreach ($users as $user) {
            try {
                if ($user->email) {
                    Mail::to($user->email)->queue(new CampaignNotificationMail(
                        $user,
                        $campaign->title,
                        $campaign->message,
                        $campaign->image_url
                    ));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send campaign email to {$user->email}:".$e->getMessage());
            }
        }

    }
}
