<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;

class SupportTicketWebController extends Controller
{
    public function index(Request $request)
    {
        $q = SupportTicket::query()->with('user:id,name,email,phone');

        if ($request->filled('status')) {
            $q->where('status', (string) $request->get('status'));
        }
        if ($request->filled('priority')) {
            $q->where('priority', (string) $request->get('priority'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $q->where(function ($w) use ($search) {
                $w->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $tickets = $q->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.support-tickets.index', compact('tickets'));
    }

    public function show(int $id)
    {
        $ticket = SupportTicket::with([
            'user:id,name,email,phone',
            'replies.user:id,name,email,phone',
        ])->findOrFail($id);

        return view('admin.support-tickets.show', compact('ticket'));
    }

    public function reply(Request $request, int $id)
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

        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

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

        return redirect()->route('admin.support-tickets.show', $ticket->id)
            ->with('success', 'Reply sent successfully.');
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->save();

        return back()->with('success', 'Support ticket status updated.');
    }

    public function destroy(int $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->delete();

        return redirect()->route('admin.support-tickets.index')
            ->with('success', 'Support ticket deleted successfully.');
    }
}

