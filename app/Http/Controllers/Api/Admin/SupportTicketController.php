<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;

/**
 * Admin API for support tickets (submitted by clients/technicians via POST /api/support/tickets).
 * List, show, reply, update status. Auth: Bearer + admin.
 */
class SupportTicketController extends Controller
{
    /**
     * GET /api/admin/support-tickets
     * List all support tickets. Query: status (open|in_progress|resolved|closed), per_page, search.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::query()->with('user:id,name,email,phone,role');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $tickets = $query->orderByDesc('created_at')->paginate($perPage);

        $items = $tickets->getCollection()->map(fn ($t) => $this->ticketToArray($t));
        $tickets->setCollection($items);

        return ApiResponse::success('Support tickets retrieved successfully.', [
            'data' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/support-tickets/{id}
     * Get a single ticket with replies.
     */
    public function show(int $id)
    {
        $ticket = SupportTicket::with([
            'user:id,name,email,phone,role',
            'replies.user:id,name,email,role',
        ])->find($id);

        if (! $ticket) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        $data = $this->ticketToArray($ticket);
        $data['replies'] = $ticket->replies->map(fn ($r) => [
            'id' => $r->id,
            'message' => $r->message,
            'is_admin' => $r->is_admin,
            'user_id' => $r->user_id,
            'user_name' => $r->user?->name,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->values()->all();

        return ApiResponse::success('Support ticket retrieved successfully.', $data);
    }

    /**
     * POST /api/admin/support-tickets/{id}/reply
     * Admin reply. Body: message (required). Sets status to in_progress if open.
     */
    public function reply(Request $request, int $id)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $ticket = SupportTicket::with('user')->find($id);
        if (! $ticket) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
            'is_admin' => true,
        ]);

        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        try {
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
        } catch (\Throwable $e) {
            \Log::warning('Support ticket reply notification failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }

        $reply->load('user:id,name,email');
        return ApiResponse::success('Reply sent successfully.', [
            'id' => $reply->id,
            'message' => $reply->message,
            'is_admin' => true,
            'user_name' => $reply->user?->name,
            'created_at' => $reply->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * PUT /api/admin/support-tickets/{id}/status
     * Update ticket status. Body: status (open|in_progress|resolved|closed).
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate(['status' => 'required|string|in:open,in_progress,resolved,closed']);

        $ticket = SupportTicket::find($id);
        if (! $ticket) {
            return ApiResponse::error('Ticket not found.', 404);
        }

        $ticket->status = $request->input('status');
        $ticket->save();

        return ApiResponse::success('Status updated successfully.', $this->ticketToArray($ticket->fresh()));
    }

    private function ticketToArray(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'email' => $ticket->email ?? $ticket->user?->email,
            'status' => $ticket->status,
            'priority' => $ticket->priority ?? 'medium',
            'category' => $ticket->category ?? 'general',
            'user_id' => $ticket->user_id,
            'user_name' => $ticket->user?->name,
            'user_role' => $ticket->user?->role,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
