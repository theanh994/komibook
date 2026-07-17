<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTicketController extends Controller
{
    /**
     * Lấy danh sách ticket của khách hàng.
     */
    public function index()
    {
        $user = Auth::user();
        $tickets = SupportTicket::where('user_id', $user->id)->orderBy('updated_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $tickets
        ]);
    }

    /**
     * Tạo ticket hỗ trợ mới.
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'priority' => 'required|in:low,medium,high',
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $user = Auth::user();

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets', 'public');
        }

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => $request->subject,
            'category' => $request->category,
            'priority' => $request->priority,
            'status' => 'open',
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $request->message,
            'attachment' => $attachmentPath,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gửi yêu cầu hỗ trợ thành công.',
            'data' => $ticket->load('messages')
        ], 201);
    }

    /**
     * Lấy chi tiết ticket (gồm hội thoại).
     */
    public function show($id)
    {
        $user = Auth::user();
        $ticket = SupportTicket::with(['messages.sender'])->findOrFail($id);

        // Bảo mật: Chỉ chủ nhân hoặc admin mới được xem
        if ($ticket->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền xem yêu cầu hỗ trợ này.'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $ticket
        ]);
    }

    /**
     * Thêm tin nhắn phản hồi.
     */
    public function reply(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $user = Auth::user();
        $ticket = SupportTicket::findOrFail($id);

        if ($ticket->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền phản hồi yêu cầu hỗ trợ này.'
            ], 403);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets', 'public');
        }

        $message = TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $request->message,
            'attachment' => $attachmentPath,
        ]);

        // Cập nhật trạng thái ticket
        if ($user->isAdmin()) {
            $ticket->status = 'pending'; // Chờ phản hồi từ khách
        } else {
            $ticket->status = 'open'; // Mở lại cho admin xử lý
        }
        $ticket->touch(); // cập nhật updated_at
        $ticket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Gửi phản hồi thành công.',
            'data' => $message->load('sender')
        ], 201);
    }

    /**
     * ADMIN: Lấy tất cả ticket.
     */
    public function adminIndex()
    {
        $tickets = SupportTicket::with('user')->orderBy('updated_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $tickets
        ]);
    }

    /**
     * ADMIN: Nhận xử lý hoặc gán ticket.
     */
    public function adminAssign(Request $request, $id)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->assigned_admin_id = $request->admin_id;
        $ticket->status = 'pending';
        $ticket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán yêu cầu hỗ trợ thành công.',
            'data' => $ticket
        ]);
    }

    /**
     * ADMIN: Cập nhật trạng thái hỗ trợ (đóng ticket).
     */
    public function adminStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:open,pending,resolved',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $request->status;
        $ticket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật trạng thái ticket thành công.',
            'data' => $ticket
        ]);
    }
}
