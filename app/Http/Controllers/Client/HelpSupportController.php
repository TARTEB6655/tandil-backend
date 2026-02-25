<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpSupportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Help Center – same data as API GET /api/support/help-center (heading, tagline, get_support, contact_info, faqs).
     */
    public function index()
    {
        $contactPhone = Setting::get('contact_phone', '+1 (234) 567-8900');
        $contactEmail = Setting::get('contact_email', 'support@tandil.com');
        $supportHours = Setting::get('support_hours', '24/7 Customer Support');

        $getSupport = [
            ['type' => 'call', 'title' => 'Call Support', 'subtitle' => 'Speak with our team', 'value' => $contactPhone],
            ['type' => 'email', 'title' => 'Email Support', 'subtitle' => 'Send us an email', 'value' => $contactEmail],
            ['type' => 'live_chat', 'title' => 'Live Chat', 'subtitle' => 'Chat with us now', 'value' => null],
            ['type' => 'submit_ticket', 'title' => 'Submit Ticket', 'subtitle' => 'Create a support ticket', 'value' => null],
        ];

        $contactInfo = [
            'phone' => $contactPhone,
            'email' => $contactEmail,
            'support_hours' => $supportHours,
        ];

        $faqs = Faq::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $heading = 'How can we help you?';
        $tagline = 'Find answers to common questions or get in touch with our support team';

        $tickets = SupportTicket::where('user_id', Auth::id())
            ->withCount('replies')
            ->latest()
            ->get();

        return view('client.help-support.index', compact('heading', 'tagline', 'getSupport', 'contactInfo', 'faqs', 'tickets'));
    }

    /**
     * Submit ticket – same as API POST /api/support/tickets.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'description' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $validated['subject'],
            'email' => $validated['email'],
            'message' => $validated['description'],
            'status' => 'open',
            'priority' => 'medium',
            'category' => 'general',
        ]);

        // Notify admins when client submits a new support ticket.
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AdminNotification(
                'New Support Ticket',
                "A client submitted ticket {$ticket->ticket_number} from {$ticket->email}: {$ticket->subject}",
                [
                    'entity' => 'support_ticket',
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'email' => $ticket->email,
                    'subject' => $ticket->subject,
                    'description' => $ticket->message,
                    'action' => 'open_ticket',
                ]
            ));
        }

        return redirect()->route('client.help-support.show', $ticket->id)->with('success', 'Support ticket submitted. We will get back to you soon.');
    }

    /**
     * Show one ticket thread for the logged-in client.
     */
    public function show(int $id)
    {
        $ticket = SupportTicket::with(['replies.user'])->where('user_id', Auth::id())->findOrFail($id);
        return view('client.help-support.show', compact('ticket'));
    }

    /**
     * Client reply on existing ticket thread.
     */
    public function reply(Request $request, int $id)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::where('user_id', Auth::id())->findOrFail($id);

        if ($ticket->status === 'closed') {
            return back()->withErrors(['message' => 'This ticket is closed and cannot be replied to.']);
        }

        SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin' => false,
        ]);

        if (in_array($ticket->status, ['open', 'resolved'], true)) {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        // Notify admins about client reply.
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AdminNotification(
                'Client Replied on Support Ticket',
                "Client replied on ticket {$ticket->ticket_number}.",
                [
                    'entity' => 'support_ticket',
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'action' => 'open_ticket_reply',
                ]
            ));
        }

        return back()->with('success', 'Reply sent successfully.');
    }
}
