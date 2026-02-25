<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mail\SupportTicketReplyMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Notifications\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;

        $q = SupportTicket::query()->with('user:id,name,email,phone');

        if ($request->filled('status')) {
            $q->where('status', (string) $request->get('status'));
        }

        if ($request->filled('priority')) {
            $q->where('priority', (string) $request->get('priority'));
        }

        $tickets = $q->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::success('Support tickets retrieved successfully.', $tickets);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'user:id,name,email,phone',
            'replies.user:id,name,email,phone',
        ])->findOrFail($id);

        return ApiResponse::success('Support ticket retrieved successfully.', $ticket);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::with('user')->findOrFail($id);

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin' => true,
        ]);

        if (in_array($ticket->status, ['open'], true)) {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        // Send reply email to the ticket contact email (or fallback to client account email).
        $recipientEmail = $ticket->email ?: optional($ticket->user)->email;
        if ($recipientEmail) {
            Mail::to($recipientEmail)->send(new SupportTicketReplyMail($ticket, $reply->message));
        }

        // Notify client that admin replied to the ticket.
        if ($ticket->user) {
            $ticket->user->notify(new AdminNotification(
                'Support Ticket Reply',
                "Admin replied to your ticket {$ticket->ticket_number}.",
                [
                    'entity' => 'support_ticket',
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'action' => 'open_ticket_reply',
                ]
            ));
        }

        return ApiResponse::success('Reply sent successfully.', [
            'ticket_id' => $ticket->id,
            'ticket_status' => $ticket->status,
            'reply' => $reply->load('user:id,name,email,phone'),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->save();

        return ApiResponse::success('Support ticket status updated successfully.', $ticket);
    }
}

