<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignDispatchService;
use Illuminate\Http\Request;
use LogicException;

class NotificationCampaignController extends Controller
{
    public function index(Request $request)
    {
        return NotificationCampaign::query()->latest()
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($inner) => $inner->where('title', 'like', '%'.$request->search.'%')->orWhere('message', 'like', '%'.$request->search.'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->paginate(10);
    }

    public function store(Request $request, NotificationCampaignDispatchService $dispatch)
    {
        $validated = $this->validateCampaign($request);
        $sendNow = $validated['status'] === 'sent';
        if ($sendNow) {
            $validated['status'] = 'draft';
        }
        $campaign = NotificationCampaign::create($validated);
        if ($sendNow) {
            $dispatch->start($campaign, $request->input('idempotency_key'));
        }

        return response()->json(['message' => 'Chiến dịch đã được tạo.', 'campaign' => $campaign->fresh()], 201);
    }

    public function show(string $id)
    {
        $campaign = NotificationCampaign::findOrFail($id);

        return response()->json(['campaign' => $campaign, 'analytics' => [
            'telemetry_available' => (bool) $campaign->telemetry_available,
            'delivery_rate' => null, 'open_rate' => null, 'click_rate' => null,
            'hourly_opens' => [], 'devices' => [], 'segments' => [],
            'operational' => ['audience' => $campaign->audience_count, 'queued_or_sent' => $campaign->sent_count, 'failed' => $campaign->failed_count, 'chunks' => $campaign->chunk_count, 'completed_chunks' => $campaign->completed_chunk_count, 'failed_chunks' => $campaign->failed_chunk_count],
        ]]);
    }

    public function update(Request $request, string $id, NotificationCampaignDispatchService $dispatch)
    {
        $campaign = NotificationCampaign::findOrFail($id);
        if ($campaign->status === 'sent' || $campaign->dispatch_status !== 'idle') {
            return response()->json(['message' => 'Không thể sửa chiến dịch đã bắt đầu gửi.'], 422);
        }
        $validated = $this->validateCampaign($request);
        $sendNow = $validated['status'] === 'sent';
        if ($sendNow) {
            $validated['status'] = 'draft';
        }
        $campaign->update($validated);
        if ($sendNow) {
            $dispatch->start($campaign, $request->input('idempotency_key'));
        }

        return response()->json(['message' => 'Chiến dịch đã được cập nhật.', 'campaign' => $campaign->fresh()]);
    }

    public function destroy(string $id)
    {
        $campaign = NotificationCampaign::findOrFail($id);
        if ($campaign->status === 'sent' || $campaign->dispatch_status !== 'idle') {
            return response()->json(['message' => 'Không thể xóa chiến dịch đã bắt đầu gửi; cần giữ lịch sử vận hành.'], 422);
        }
        $campaign->delete();

        return response()->json(['message' => 'Chiến dịch đã được xóa.']);
    }

    public function send(Request $request, string $id, NotificationCampaignDispatchService $dispatch)
    {
        try {
            $campaign = $dispatch->start(NotificationCampaign::findOrFail($id), $request->input('idempotency_key'));

            return response()->json(['message' => 'Chiến dịch đã được chia batch và đưa vào hàng đợi.', 'campaign' => $campaign]);
        } catch (LogicException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function retry(string $id, NotificationCampaignDispatchService $dispatch)
    {
        $campaign = NotificationCampaign::findOrFail($id);
        if (! in_array($campaign->dispatch_status, ['failed', 'partial_failed'], true)) {
            return response()->json(['message' => 'Chiến dịch không có chunk thất bại để thử lại.'], 422);
        }
        $dispatch->retryFailed($campaign);

        return response()->json(['message' => 'Các chunk thất bại đã được đưa lại vào hàng đợi.', 'campaign' => $campaign->fresh()]);
    }

    private function validateCampaign(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255', 'message' => 'required|string|max:10000', 'image_url' => 'nullable|url|max:500',
            'target_audience' => 'required|in:all,active_readers,fiction_enthusiasts,lapsed_users',
            'scheduled_at' => 'nullable|date', 'status' => 'required|in:draft,scheduled,sent', 'idempotency_key' => 'nullable|string|max:100',
        ]);
        if ($validated['status'] === 'scheduled' && empty($validated['scheduled_at'])) {
            abort(422, 'Chiến dịch lên lịch phải có scheduled_at.');
        }
        unset($validated['idempotency_key']);

        return $validated;
    }
}
