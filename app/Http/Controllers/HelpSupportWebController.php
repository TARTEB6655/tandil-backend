<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Help & Support (tickets + chat) for Technician, Supervisor, Area Manager, HR, and Vendor.
 * Same behaviour as API: list my tickets, submit ticket, view ticket with replies, reply.
 * Client uses Client\HelpSupportController; admin uses Admin\SupportTicketWebController.
 */
class HelpSupportWebController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:technician|supervisor|area_manager|hr|vendor']);
    }

    /** Route and view prefix: area_manager -> areamanager to match web routes. */
    private function routePrefix(): string
    {
        $role = Auth::user()->role ?? Auth::user()->getRoleNames()->first();
        return $role === 'area_manager' ? 'areamanager' : (string) $role;
    }

    /**
     * Help & Support index – same data as API GET /api/support/help-center + my tickets.
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
        $tagline = 'Find answers or get in touch with support. Submit a ticket and chat with admin.';

        $tickets = SupportTicket::where('user_id', Auth::id())
            ->withCount('replies')
            ->latest()
            ->get();

        $routePrefix = $this->routePrefix();
        return view("{$routePrefix}.help-support.index", compact('heading', 'tagline', 'getSupport', 'contactInfo', 'faqs', 'tickets', 'routePrefix'));
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

        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AdminNotification(
                'New Support Ticket',
                "A user submitted ticket {$ticket->ticket_number} from {$ticket->email}: {$ticket->subject}",
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

        $routePrefix = $this->routePrefix();
        return redirect()->route("{$routePrefix}.help-support.show", $ticket->id)->with('success', 'Support ticket submitted. Admin will reply soon.');
    }

    /**
     * Show one ticket thread (chat with admin).
     */
    public function show(int $id)
    {
        $ticket = SupportTicket::with(['replies.user'])->where('user_id', Auth::id())->findOrFail($id);
        $routePrefix = $this->routePrefix();
        return view("{$routePrefix}.help-support.show", compact('ticket', 'routePrefix'));
    }

    /**
     * User reply on ticket (chat message).
     */
    public function reply(Request $request, int $id)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::where('user_id', Auth::id())->findOrFail($id);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return back()->withErrors(['message' => 'This ticket is closed and cannot be replied to.']);
        }

        SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin' => false,
        ]);

        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AdminNotification(
                'User Replied on Support Ticket',
                "User replied on ticket {$ticket->ticket_number}.",
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
